<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use PDOException;
use RuntimeException;

final class VhostRepository
{
    private ?PDO $pdo = null;

    public function __construct(
        private readonly string $dbFile,
        private readonly string $legacyJsonFile = ''
    ) {
    }

    public function initialize(): void
    {
        $pdo = $this->pdo();

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS vhosts (
                domain TEXT PRIMARY KEY,
                alias TEXT NOT NULL DEFAULT \'\',
                docroot TEXT NOT NULL DEFAULT \'\',
                cf_record_id TEXT NOT NULL DEFAULT \'\',
                cf_zone_id TEXT NOT NULL DEFAULT \'\',
                cf_record_ip TEXT NOT NULL DEFAULT \'\',
                cf_proxied INTEGER DEFAULT NULL,
                npm_proxy_id INTEGER DEFAULT NULL,
                npm_ssl_enabled INTEGER NOT NULL DEFAULT 0,
                npm_certificate_id INTEGER NOT NULL DEFAULT 0,
                npm_ssl_forced INTEGER NOT NULL DEFAULT 0,
                npm_http2_support INTEGER NOT NULL DEFAULT 0,
                npm_hsts_enabled INTEGER NOT NULL DEFAULT 0,
                npm_hsts_subdomains INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT \'\',
                updated_at TEXT NOT NULL DEFAULT \'\',
                created_by TEXT NOT NULL DEFAULT \'\',
                updated_by TEXT NOT NULL DEFAULT \'\'
            )'
        );

        $this->runMigration();
    }

    public function all(): array
    {
        $stmt = $this->pdo()->query('SELECT * FROM vhosts ORDER BY updated_at DESC, created_at DESC');
        if ($stmt === false) {
            return [];
        }

        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $domain = (string) $row['domain'];
            $result[$domain] = $this->hydrateRecord($row);
        }

        return $result;
    }

    public function put(string $domain, array $record): void
    {
        $domain = strtolower(trim($domain));

        $stmt = $this->pdo()->prepare(
            'INSERT INTO vhosts (domain, alias, docroot, cf_record_id, cf_zone_id, cf_record_ip, cf_proxied,
                npm_proxy_id, npm_ssl_enabled, npm_certificate_id, npm_ssl_forced, npm_http2_support,
                npm_hsts_enabled, npm_hsts_subdomains, created_at, updated_at, created_by, updated_by)
             VALUES (:domain, :alias, :docroot, :cf_record_id, :cf_zone_id, :cf_record_ip, :cf_proxied,
                :npm_proxy_id, :npm_ssl_enabled, :npm_certificate_id, :npm_ssl_forced, :npm_http2_support,
                :npm_hsts_enabled, :npm_hsts_subdomains, :created_at, :updated_at, :created_by, :updated_by)
             ON CONFLICT(domain) DO UPDATE SET
                alias = excluded.alias,
                docroot = excluded.docroot,
                cf_record_id = excluded.cf_record_id,
                cf_zone_id = excluded.cf_zone_id,
                cf_record_ip = excluded.cf_record_ip,
                cf_proxied = excluded.cf_proxied,
                npm_proxy_id = excluded.npm_proxy_id,
                npm_ssl_enabled = excluded.npm_ssl_enabled,
                npm_certificate_id = excluded.npm_certificate_id,
                npm_ssl_forced = excluded.npm_ssl_forced,
                npm_http2_support = excluded.npm_http2_support,
                npm_hsts_enabled = excluded.npm_hsts_enabled,
                npm_hsts_subdomains = excluded.npm_hsts_subdomains,
                updated_at = excluded.updated_at,
                updated_by = excluded.updated_by'
        );

        $cfProxied = $record['cf_proxied'] ?? null;
        $npmProxyId = $record['npm_proxy_id'] ?? null;

        $stmt->execute([
            ':domain' => $domain,
            ':alias' => (string) ($record['alias'] ?? ''),
            ':docroot' => (string) ($record['docroot'] ?? ''),
            ':cf_record_id' => (string) ($record['cf_record_id'] ?? ''),
            ':cf_zone_id' => (string) ($record['cf_zone_id'] ?? ''),
            ':cf_record_ip' => (string) ($record['cf_record_ip'] ?? ''),
            ':cf_proxied' => $cfProxied !== null ? (int) (bool) $cfProxied : null,
            ':npm_proxy_id' => $npmProxyId !== null ? (int) $npmProxyId : null,
            ':npm_ssl_enabled' => (int) !empty($record['npm_ssl_enabled']),
            ':npm_certificate_id' => (int) ($record['npm_certificate_id'] ?? 0),
            ':npm_ssl_forced' => (int) !empty($record['npm_ssl_forced']),
            ':npm_http2_support' => (int) !empty($record['npm_http2_support']),
            ':npm_hsts_enabled' => (int) !empty($record['npm_hsts_enabled']),
            ':npm_hsts_subdomains' => (int) !empty($record['npm_hsts_subdomains']),
            ':created_at' => (string) ($record['created_at'] ?? date('c')),
            ':updated_at' => (string) ($record['updated_at'] ?? date('c')),
            ':created_by' => (string) ($record['created_by'] ?? ''),
            ':updated_by' => (string) ($record['updated_by'] ?? ''),
        ]);
    }

    public function remove(string $domain): void
    {
        $stmt = $this->pdo()->prepare('DELETE FROM vhosts WHERE domain = :domain');
        $stmt->execute([':domain' => strtolower(trim($domain))]);
    }

    private function hydrateRecord(array $row): array
    {
        return [
            'domain' => (string) $row['domain'],
            'alias' => ($row['alias'] ?? '') !== '' ? (string) $row['alias'] : null,
            'docroot' => (string) $row['docroot'],
            'cf_record_id' => ($row['cf_record_id'] ?? '') !== '' ? (string) $row['cf_record_id'] : null,
            'cf_zone_id' => ($row['cf_zone_id'] ?? '') !== '' ? (string) $row['cf_zone_id'] : null,
            'cf_record_ip' => ($row['cf_record_ip'] ?? '') !== '' ? (string) $row['cf_record_ip'] : null,
            'cf_proxied' => $row['cf_proxied'] !== null ? (bool) $row['cf_proxied'] : null,
            'npm_proxy_id' => $row['npm_proxy_id'] !== null ? (int) $row['npm_proxy_id'] : null,
            'npm_ssl_enabled' => (bool) $row['npm_ssl_enabled'],
            'npm_certificate_id' => (int) $row['npm_certificate_id'],
            'npm_ssl_forced' => (bool) $row['npm_ssl_forced'],
            'npm_http2_support' => (bool) $row['npm_http2_support'],
            'npm_hsts_enabled' => (bool) $row['npm_hsts_enabled'],
            'npm_hsts_subdomains' => (bool) $row['npm_hsts_subdomains'],
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
            'created_by' => (string) $row['created_by'],
            'updated_by' => (string) ($row['updated_by'] ?? ''),
        ];
    }

    private function runMigration(): void
    {
        $stmt = $this->pdo()->query('SELECT COUNT(*) FROM vhosts');
        if ($stmt !== false && (int) $stmt->fetchColumn() > 0) {
            return;
        }

        if ($this->legacyJsonFile === '' || !is_file($this->legacyJsonFile)) {
            return;
        }

        $json = @file_get_contents($this->legacyJsonFile);
        if ($json === false || $json === '') {
            return;
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            return;
        }

        foreach ($data as $domain => $record) {
            if (!is_array($record)) {
                continue;
            }
            $record['domain'] = strtolower(trim((string) $domain));
            $this->put($record['domain'], $record);
        }
    }

    private function pdo(): PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        $dir = dirname($this->dbFile);
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException('Unable to create vhosts SQLite directory.');
        }

        try {
            $pdo = new PDO('sqlite:' . $this->dbFile);
        } catch (PDOException $e) {
            throw new RuntimeException('Unable to open vhosts SQLite database.', 0, $e);
        }

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA synchronous = NORMAL');

        $this->pdo = $pdo;

        return $this->pdo;
    }
}
