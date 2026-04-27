<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use PDOException;
use RuntimeException;

final class SettingsStore
{
    private ?PDO $pdo = null;

    public function __construct(private readonly string $dbFile)
    {
    }

    public function initialize(): void
    {
        $pdo = $this->pdo();

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS app_settings (
                key TEXT PRIMARY KEY,
                value TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        $stmt = $this->pdo()->query('SELECT key, value FROM app_settings');
        if ($stmt === false) {
            throw new RuntimeException('Failed to load application settings from SQLite.');
        }

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $settings = [];

        foreach ($rows as $row) {
            $key = (string) ($row['key'] ?? '');
            if ($key === '') {
                continue;
            }

            $settings[$key] = (string) ($row['value'] ?? '');
        }

        return $settings;
    }

    public function isEmpty(): bool
    {
        $stmt = $this->pdo()->query('SELECT 1 FROM app_settings LIMIT 1');
        if ($stmt === false) {
            throw new RuntimeException('Failed to check SQLite settings state.');
        }

        return $stmt->fetchColumn() === false;
    }

    /**
     * @param array<string, string> $values
     */
    public function setMany(array $values): void
    {
        if ($values === []) {
            return;
        }

        $pdo = $this->pdo();

        $statement = $pdo->prepare(
            'INSERT INTO app_settings (key, value, updated_at)
             VALUES (:key, :value, :updated_at)
             ON CONFLICT(key) DO UPDATE SET
                value = excluded.value,
                updated_at = excluded.updated_at'
        );

        if ($statement === false) {
            throw new RuntimeException('Failed to prepare SQLite settings upsert statement.');
        }

        $now = date('c');

        $pdo->beginTransaction();

        try {
            foreach ($values as $key => $value) {
                $normalizedKey = trim((string) $key);
                if ($normalizedKey === '') {
                    continue;
                }

                $statement->execute([
                    ':key' => $normalizedKey,
                    ':value' => (string) $value,
                    ':updated_at' => $now,
                ]);
            }

            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            throw new RuntimeException('Failed to save application settings to SQLite.', 0, $e);
        }
    }

    /**
     * @param array<string, mixed> $envValues
     * @param list<string> $keys
     */
    public function bootstrapFromEnv(array $envValues, array $keys): void
    {
        $existing = $this->all();
        $toInsert = [];

        foreach ($keys as $key) {
            if ($key === '' || array_key_exists($key, $existing)) {
                continue;
            }

            $raw = $envValues[$key] ?? null;
            if (!is_scalar($raw)) {
                continue;
            }

            $value = trim((string) $raw);
            if ($value === '') {
                continue;
            }

            // Avoid importing known placeholder hashes.
            if ($key === 'ADMIN_PASSWORD_HASH' && str_contains(strtolower($value), 'replace_with_generated_password_hash')) {
                continue;
            }

            $toInsert[$key] = $value;
        }

        if ($toInsert !== []) {
            $this->setMany($toInsert);
        }
    }

    private function pdo(): PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        $dir = dirname($this->dbFile);
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException('Unable to create settings SQLite directory.');
        }

        try {
            $pdo = new PDO('sqlite:' . $this->dbFile);
        } catch (PDOException $e) {
            throw new RuntimeException('Unable to open settings SQLite database.', 0, $e);
        }

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA synchronous = NORMAL');

        $this->pdo = $pdo;

        return $this->pdo;
    }
}
