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

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS users (
                email TEXT PRIMARY KEY,
                password_hash TEXT NOT NULL DEFAULT \'\',
                full_name TEXT NOT NULL DEFAULT \'\',
                account_type TEXT NOT NULL DEFAULT \'user\',
                is_primary INTEGER NOT NULL DEFAULT 0,
                active INTEGER NOT NULL DEFAULT 1,
                created_at TEXT NOT NULL DEFAULT \'\',
                last_login_at TEXT NOT NULL DEFAULT \'\',
                updated_at TEXT NOT NULL DEFAULT \'\'
            )'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS integrations (
                id TEXT PRIMARY KEY,
                name TEXT NOT NULL DEFAULT \'\',
                provider TEXT NOT NULL DEFAULT \'\',
                category TEXT NOT NULL DEFAULT \'\',
                settings_json TEXT NOT NULL DEFAULT \'{}\',
                created_at TEXT NOT NULL DEFAULT \'\',
                updated_at TEXT NOT NULL DEFAULT \'\'
            )'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS domains (
                domain TEXT PRIMARY KEY,
                dns_integration_id TEXT NOT NULL DEFAULT \'\',
                updated_at TEXT NOT NULL DEFAULT \'\'
            )'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS cloudflare_domain_settings (
                domain TEXT NOT NULL,
                dns_integration_id TEXT NOT NULL,
                zone_id TEXT NOT NULL DEFAULT \'\',
                api_token TEXT NOT NULL DEFAULT \'\',
                record_ip TEXT NOT NULL DEFAULT \'\',
                proxied INTEGER NOT NULL DEFAULT 0,
                ttl INTEGER NOT NULL DEFAULT 120,
                updated_at TEXT NOT NULL DEFAULT \'\'
            )'
        );

        $pdo->exec(
            'CREATE UNIQUE INDEX IF NOT EXISTS idx_cf_domain_settings_domain_integration
             ON cloudflare_domain_settings (domain, dns_integration_id)'
        );

        $this->runMigrations();
    }

    // =========================================================================
    // General key-value methods
    // =========================================================================

    public function get(string $key, ?string $default = null): ?string
    {
        $stmt = $this->pdo()->prepare('SELECT value FROM app_settings WHERE key = :key');
        $stmt->execute([':key' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? (string) $row['value'] : $default;
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
     * @param list<string> $keys
     */
    public function deleteKeys(array $keys): void
    {
        $normalized = [];
        foreach ($keys as $key) {
            $k = trim((string) $key);
            if ($k !== '') {
                $normalized[] = $k;
            }
        }

        if ($normalized === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($normalized), '?'));
        $stmt = $this->pdo()->prepare('DELETE FROM app_settings WHERE key IN (' . $placeholders . ')');
        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare SQLite settings delete statement.');
        }

        $stmt->execute($normalized);
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

    // =========================================================================
    // Users table
    // =========================================================================

    /**
     * @return list<array{email:string,password_hash:string,full_name:string,account_type:string,is_primary:bool,active:bool,created_at:string,last_login_at:string,updated_at:string}>
     */
    public function userGetAll(): array
    {
        $stmt = $this->pdo()->query('SELECT * FROM users ORDER BY is_primary DESC, email ASC');
        if ($stmt === false) {
            return [];
        }

        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[] = $this->hydrateUser($row);
        }

        return $rows;
    }

    /**
     * @return array{email:string,password_hash:string,full_name:string,account_type:string,is_primary:bool,active:bool,created_at:string,last_login_at:string,updated_at:string}|null
     */
    public function userGetPrimary(): ?array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM users WHERE is_primary = 1 LIMIT 1');
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $this->hydrateUser($row) : null;
    }

    /**
     * @return array{email:string,password_hash:string,full_name:string,account_type:string,is_primary:bool,active:bool,created_at:string,last_login_at:string,updated_at:string}|null
     */
    public function userGet(string $email): ?array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->execute([':email' => strtolower(trim($email))]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $this->hydrateUser($row) : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function userUpsert(array $data): void
    {
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        if ($email === '') {
            throw new RuntimeException('User email is required.');
        }

        $now = date('c');
        $existing = $this->userGet($email);

        $stmt = $this->pdo()->prepare(
            'INSERT INTO users (email, password_hash, full_name, account_type, is_primary, active, created_at, last_login_at, updated_at)
             VALUES (:email, :password_hash, :full_name, :account_type, :is_primary, :active, :created_at, :last_login_at, :updated_at)
             ON CONFLICT(email) DO UPDATE SET
                password_hash = CASE WHEN excluded.password_hash != \'\' THEN excluded.password_hash ELSE password_hash END,
                full_name = excluded.full_name,
                account_type = excluded.account_type,
                is_primary = excluded.is_primary,
                active = excluded.active,
                last_login_at = excluded.last_login_at,
                updated_at = excluded.updated_at'
        );

        $newHash = (string) ($data['password_hash'] ?? '');
        $stmt->execute([
            ':email' => $email,
            ':password_hash' => $newHash !== '' ? $newHash : (string) ($existing['password_hash'] ?? ''),
            ':full_name' => (string) ($data['full_name'] ?? ($existing['full_name'] ?? '')),
            ':account_type' => (string) ($data['account_type'] ?? ($existing['account_type'] ?? 'user')),
            ':is_primary' => (int) ($data['is_primary'] ?? ($existing['is_primary'] ?? false)),
            ':active' => isset($data['active']) ? (int) ((bool) $data['active']) : (int) ($existing['active'] ?? true),
            ':created_at' => (string) ($data['created_at'] ?? ($existing['created_at'] ?? $now)),
            ':last_login_at' => (string) ($data['last_login_at'] ?? ($existing['last_login_at'] ?? '')),
            ':updated_at' => $now,
        ]);
    }

    public function userDelete(string $email): void
    {
        $stmt = $this->pdo()->prepare('DELETE FROM users WHERE email = :email AND is_primary = 0');
        $stmt->execute([':email' => strtolower(trim($email))]);
    }

    public function userUpdateEmail(string $oldEmail, string $newEmail): void
    {
        $stmt = $this->pdo()->prepare(
            'UPDATE users SET email = :new_email, updated_at = :now WHERE email = :old_email'
        );
        $stmt->execute([
            ':new_email' => strtolower(trim($newEmail)),
            ':old_email' => strtolower(trim($oldEmail)),
            ':now' => date('c'),
        ]);
    }

    // =========================================================================
    // Integrations table
    // =========================================================================

    /**
     * @return list<array{id:string,name:string,provider:string,category:string,settings:array<string,mixed>,created_at:string,updated_at:string}>
     */
    public function integrationGetAll(): array
    {
        $stmt = $this->pdo()->query('SELECT * FROM integrations ORDER BY category ASC, name ASC');
        if ($stmt === false) {
            return [];
        }

        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[] = $this->hydrateIntegration($row);
        }

        return $rows;
    }

    /**
     * @return array{id:string,name:string,provider:string,category:string,settings:array<string,mixed>,created_at:string,updated_at:string}|null
     */
    public function integrationGet(string $id): ?array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM integrations WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $this->hydrateIntegration($row) : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function integrationUpsert(array $data): void
    {
        $id = trim((string) ($data['id'] ?? ''));
        if ($id === '') {
            throw new RuntimeException('Integration ID is required.');
        }

        $now = date('c');
        $existing = $this->integrationGet($id);

        $settings = $data['settings'] ?? ($existing['settings'] ?? []);
        if (!is_array($settings)) {
            $settings = [];
        }

        $stmt = $this->pdo()->prepare(
            'INSERT INTO integrations (id, name, provider, category, settings_json, created_at, updated_at)
             VALUES (:id, :name, :provider, :category, :settings_json, :created_at, :updated_at)
             ON CONFLICT(id) DO UPDATE SET
                name = excluded.name,
                provider = excluded.provider,
                category = excluded.category,
                settings_json = excluded.settings_json,
                updated_at = excluded.updated_at'
        );

        $stmt->execute([
            ':id' => $id,
            ':name' => (string) ($data['name'] ?? ($existing['name'] ?? '')),
            ':provider' => (string) ($data['provider'] ?? ($existing['provider'] ?? '')),
            ':category' => (string) ($data['category'] ?? ($existing['category'] ?? '')),
            ':settings_json' => json_encode($settings, JSON_UNESCAPED_SLASHES) ?: '{}',
            ':created_at' => $existing !== null ? ($existing['created_at'] ?? $now) : $now,
            ':updated_at' => $now,
        ]);
    }

    public function integrationDelete(string $id): void
    {
        $stmt = $this->pdo()->prepare('DELETE FROM integrations WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    // =========================================================================
    // Domains table
    // =========================================================================

    /**
     * @return list<array{domain:string,updated_at:string,cloudflare?:array{zone_id:string,api_token:string,record_ip:string,proxied:bool,ttl:int}}>
     */
    public function domainGetAll(): array
    {
        $stmt = $this->pdo()->query(
            'SELECT d.domain, d.dns_integration_id, d.updated_at,
                    c.zone_id AS cf_zone_id, c.api_token AS cf_api_token,
                    c.record_ip AS cf_record_ip, c.proxied AS cf_proxied,
                    c.ttl AS cf_ttl
             FROM domains d
             LEFT JOIN cloudflare_domain_settings c
               ON c.domain = d.domain AND c.dns_integration_id = d.dns_integration_id
             ORDER BY d.domain ASC'
        );
        if ($stmt === false) {
            return [];
        }

        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[] = $this->hydrateDomain($row);
        }

        return $rows;
    }

    /**
     * @return array{domain:string,updated_at:string,cloudflare?:array{zone_id:string,api_token:string,record_ip:string,proxied:bool,ttl:int}}|null
     */
    public function domainGet(string $domain): ?array
    {
                $stmt = $this->pdo()->prepare(
                        'SELECT d.domain, d.dns_integration_id, d.updated_at,
                                        c.zone_id AS cf_zone_id, c.api_token AS cf_api_token,
                                        c.record_ip AS cf_record_ip, c.proxied AS cf_proxied,
                                        c.ttl AS cf_ttl
                         FROM domains d
                         LEFT JOIN cloudflare_domain_settings c
                             ON c.domain = d.domain AND c.dns_integration_id = d.dns_integration_id
                         WHERE d.domain = :domain'
                );
        $stmt->execute([':domain' => strtolower(trim($domain))]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $this->hydrateDomain($row) : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function domainUpsert(array $data): void
    {
        $domain = strtolower(trim((string) ($data['domain'] ?? '')));
        if ($domain === '') {
            throw new RuntimeException('Domain is required.');
        }

        $dnsIntegrationId = trim((string) ($data['dns_integration_id'] ?? ''));
        $zoneId = trim((string) ($data['cf_zone_id'] ?? ''));
        $apiToken = trim((string) ($data['cf_api_token'] ?? ''));
        $hasCloudflareDetails = ($zoneId !== '' || $apiToken !== '');
        if ($hasCloudflareDetails && $dnsIntegrationId === '') {
            $dnsIntegrationId = $this->firstIntegrationIdByProvider('cloudflare');
            if ($dnsIntegrationId === '') {
                $dnsIntegrationId = 'cloudflare_enabled';
                $this->integrationUpsert([
                    'id' => $dnsIntegrationId,
                    'name' => 'Cloudflare',
                    'provider' => 'cloudflare',
                    'category' => 'dns',
                    'settings' => [],
                ]);
            }
        }

        if ($hasCloudflareDetails && $dnsIntegrationId === '') {
            throw new RuntimeException('Cloudflare details require an enabled Cloudflare DNS integration.');
        }

        $pdo = $this->pdo();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO domains (domain, dns_integration_id, updated_at)
                 VALUES (:domain, :dns_integration_id, :updated_at)
                 ON CONFLICT(domain) DO UPDATE SET
                    dns_integration_id = excluded.dns_integration_id,
                    updated_at = excluded.updated_at'
            );

            $stmt->execute([
                ':domain' => $domain,
                ':dns_integration_id' => $dnsIntegrationId,
                ':updated_at' => date('c'),
            ]);

            $cfDelete = $pdo->prepare('DELETE FROM cloudflare_domain_settings WHERE domain = :domain');
            $cfDelete->execute([':domain' => $domain]);

            if ($hasCloudflareDetails && $dnsIntegrationId !== '') {
                $cfStmt = $pdo->prepare(
                    'INSERT INTO cloudflare_domain_settings
                        (domain, dns_integration_id, zone_id, api_token, record_ip, proxied, ttl, updated_at)
                     VALUES
                        (:domain, :dns_integration_id, :zone_id, :api_token, :record_ip, :proxied, :ttl, :updated_at)
                     ON CONFLICT(domain, dns_integration_id) DO UPDATE SET
                        zone_id = excluded.zone_id,
                        api_token = excluded.api_token,
                        record_ip = excluded.record_ip,
                        proxied = excluded.proxied,
                        ttl = excluded.ttl,
                        updated_at = excluded.updated_at'
                );

                $cfStmt->execute([
                    ':domain' => $domain,
                    ':dns_integration_id' => $dnsIntegrationId,
                    ':zone_id' => $zoneId,
                    ':api_token' => $apiToken,
                    ':record_ip' => (string) ($data['cf_record_ip'] ?? ''),
                    ':proxied' => (int) (bool) ($data['cf_proxied'] ?? false),
                    ':ttl' => max(1, (int) ($data['cf_ttl'] ?? 120)),
                    ':updated_at' => date('c'),
                ]);
            }

            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            throw new RuntimeException('Failed to save domain settings.', 0, $e);
        }
    }

    public function domainDelete(string $domain): void
    {
        $normalized = strtolower(trim($domain));
        $pdo = $this->pdo();
        $pdo->beginTransaction();

        try {
            $cfStmt = $pdo->prepare('DELETE FROM cloudflare_domain_settings WHERE domain = :domain');
            $cfStmt->execute([':domain' => $normalized]);

            $stmt = $pdo->prepare('DELETE FROM domains WHERE domain = :domain');
            $stmt->execute([':domain' => $normalized]);

            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            throw new RuntimeException('Failed to delete domain settings.', 0, $e);
        }
    }

    /**
     * Returns CF domain mappings in the format expected by CloudflareService.
     * @return list<array{domain:string,zone_id:string,api_token:string}>
     */
    public function domainGetCfMappings(): array
    {
        $stmt = $this->pdo()->query(
                        "SELECT d.domain, c.zone_id AS zone_id, c.api_token AS api_token
                         FROM domains d
                         JOIN cloudflare_domain_settings c
                             ON c.domain = d.domain AND c.dns_integration_id = d.dns_integration_id
                         JOIN integrations i ON i.id = d.dns_integration_id
                         WHERE i.provider = 'cloudflare' AND c.zone_id != '' AND c.api_token != ''
                         ORDER BY d.domain ASC"
        );
        if ($stmt === false) {
            return [];
        }

        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[] = [
                'domain' => (string) $row['domain'],
                'zone_id' => (string) $row['zone_id'],
                'api_token' => (string) $row['api_token'],
            ];
        }

        return $rows;
    }

    /**
     * Syncs CF_DOMAINS_JSON in app_settings from the domains table.
     * Required for CloudflareService which reads CF_DOMAINS_JSON via Config.
     */
    public function syncCfDomainsJson(): void
    {
        // No-op: Cloudflare mappings now come directly from relational tables.
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * @param array<string, mixed> $row
     * @return array{email:string,password_hash:string,full_name:string,account_type:string,is_primary:bool,active:bool,created_at:string,last_login_at:string,updated_at:string}
     */
    private function hydrateUser(array $row): array
    {
        return [
            'email' => (string) $row['email'],
            'password_hash' => (string) $row['password_hash'],
            'full_name' => (string) $row['full_name'],
            'account_type' => (string) $row['account_type'],
            'is_primary' => (bool) $row['is_primary'],
            'active' => (bool) $row['active'],
            'created_at' => (string) $row['created_at'],
            'last_login_at' => (string) $row['last_login_at'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array{id:string,name:string,provider:string,category:string,settings:array<string,mixed>,created_at:string,updated_at:string}
     */
    private function hydrateIntegration(array $row): array
    {
        $settings = [];
        $settingsJson = (string) ($row['settings_json'] ?? '{}');
        if ($settingsJson !== '') {
            $decoded = json_decode($settingsJson, true);
            if (is_array($decoded)) {
                $settings = $decoded;
            }
        }

        return [
            'id' => (string) $row['id'],
            'name' => (string) $row['name'],
            'provider' => (string) $row['provider'],
            'category' => (string) $row['category'],
            'settings' => $settings,
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array{domain:string,updated_at:string,cloudflare?:array{zone_id:string,api_token:string,record_ip:string,proxied:bool,ttl:int}}
     */
    private function hydrateDomain(array $row): array
    {
        $cfZoneId = (string) ($row['cf_zone_id'] ?? '');
        $cfApiToken = (string) ($row['cf_api_token'] ?? '');

        $result = [
            'domain' => (string) $row['domain'],
            'dns_integration_id' => (string) ($row['dns_integration_id'] ?? ''),
            'updated_at' => (string) $row['updated_at'],
        ];

        if ($cfZoneId !== '' || $cfApiToken !== '') {
            $result['cloudflare'] = [
                'zone_id' => $cfZoneId,
                'api_token' => $cfApiToken,
                'record_ip' => (string) ($row['cf_record_ip'] ?? ''),
                'proxied' => (bool) ($row['cf_proxied'] ?? false),
                'ttl' => max(1, (int) ($row['cf_ttl'] ?? 120)),
            ];
        }

        return $result;
    }

    // =========================================================================
    // Migrations
    // =========================================================================

    private function runMigrations(): void
    {
        if ($this->get('_schema_v2_migrated') === null) {
            $this->migrateLegacyUsers();
            $this->migrateLegacyIntegrations();
            $this->migrateLegacyDomains();

            $this->setMany(['_schema_v2_migrated' => date('c')]);
        }

        if ($this->get('_schema_v3_domains_normalized') === null) {
            $this->migrateDomainsToIntegrationScopedTables();
            $this->setMany(['_schema_v3_domains_normalized' => date('c')]);
        }

        if ($this->get('_schema_v3_settings_trimmed') === null) {
            $this->trimDeprecatedSettingsKeys();
            $this->setMany(['_schema_v3_settings_trimmed' => date('c')]);
        }
    }

    private function migrateDomainsToIntegrationScopedTables(): void
    {
        $pdo = $this->pdo();

        if (!$this->columnExists('domains', 'dns_integration_id')) {
            $pdo->exec("ALTER TABLE domains ADD COLUMN dns_integration_id TEXT NOT NULL DEFAULT ''");
        }

        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_domains_dns_integration
             ON domains (dns_integration_id)'
        );

        $cloudflareIntegrationId = $this->ensureCloudflareIntegrationForDomainData();
        if ($cloudflareIntegrationId === '') {
            return;
        }

        if ($this->columnExists('domains', 'cf_zone_id') && $this->columnExists('domains', 'cf_api_token')) {
            $stmt = $pdo->query(
                "SELECT domain, cf_zone_id, cf_api_token, cf_record_ip, cf_proxied, cf_ttl, updated_at
                 FROM domains
                 WHERE cf_zone_id != '' OR cf_api_token != ''"
            );

            if ($stmt !== false) {
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $domain = strtolower(trim((string) ($row['domain'] ?? '')));
                    if ($domain === '') {
                        continue;
                    }

                    $upsert = $pdo->prepare(
                        'INSERT INTO cloudflare_domain_settings
                            (domain, dns_integration_id, zone_id, api_token, record_ip, proxied, ttl, updated_at)
                         VALUES
                            (:domain, :dns_integration_id, :zone_id, :api_token, :record_ip, :proxied, :ttl, :updated_at)
                         ON CONFLICT(domain, dns_integration_id) DO UPDATE SET
                            zone_id = excluded.zone_id,
                            api_token = excluded.api_token,
                            record_ip = excluded.record_ip,
                            proxied = excluded.proxied,
                            ttl = excluded.ttl,
                            updated_at = excluded.updated_at'
                    );

                    $upsert->execute([
                        ':domain' => $domain,
                        ':dns_integration_id' => $cloudflareIntegrationId,
                        ':zone_id' => (string) ($row['cf_zone_id'] ?? ''),
                        ':api_token' => (string) ($row['cf_api_token'] ?? ''),
                        ':record_ip' => (string) ($row['cf_record_ip'] ?? ''),
                        ':proxied' => (int) (bool) ($row['cf_proxied'] ?? false),
                        ':ttl' => max(1, (int) ($row['cf_ttl'] ?? 120)),
                        ':updated_at' => (string) ($row['updated_at'] ?? date('c')),
                    ]);

                    $link = $pdo->prepare('UPDATE domains SET dns_integration_id = :dns_integration_id WHERE domain = :domain');
                    $link->execute([
                        ':dns_integration_id' => $cloudflareIntegrationId,
                        ':domain' => $domain,
                    ]);
                }
            }
        }
    }

    private function trimDeprecatedSettingsKeys(): void
    {
        $this->deleteKeys([
            'ADMIN_USER',
            'ADMIN_FULL_NAME',
            'ADMIN_PASSWORD_HASH',
            'ADMIN_CREATED_AT',
            'ADMIN_LAST_LOGIN_AT',
            'USERS_JSON',
            'USERS_META_JSON',
            'DOMAINS_JSON',
            'CF_DOMAINS_JSON',
        ]);
    }

    private function ensureCloudflareIntegrationForDomainData(): string
    {
        $existing = $this->firstIntegrationIdByProvider('cloudflare');
        if ($existing !== '') {
            return $existing;
        }

        if (!$this->columnExists('domains', 'cf_zone_id') || !$this->columnExists('domains', 'cf_api_token')) {
            return '';
        }

        $stmt = $this->pdo()->query(
            "SELECT 1 FROM domains WHERE (cf_zone_id != '' OR cf_api_token != '') LIMIT 1"
        );

        if ($stmt === false || $stmt->fetchColumn() === false) {
            return '';
        }

        $id = 'cloudflare_enabled';
        $this->integrationUpsert([
            'id' => $id,
            'name' => 'Cloudflare',
            'provider' => 'cloudflare',
            'category' => 'dns',
            'settings' => [],
        ]);

        return $id;
    }

    private function firstIntegrationIdByProvider(string $provider): string
    {
        $stmt = $this->pdo()->prepare('SELECT id FROM integrations WHERE provider = :provider ORDER BY updated_at DESC, created_at DESC LIMIT 1');
        $stmt->execute([':provider' => trim($provider)]);
        $value = $stmt->fetchColumn();

        return is_string($value) ? $value : '';
    }

    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->pdo()->query('PRAGMA table_info(' . $table . ')');
        if ($stmt === false) {
            return false;
        }

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (strcasecmp((string) ($row['name'] ?? ''), $column) === 0) {
                return true;
            }
        }

        return false;
    }

    private function migrateLegacyUsers(): void
    {
        $adminEmail = strtolower(trim((string) ($this->get('ADMIN_USER') ?? '')));
        if ($adminEmail !== '' && $this->userGet($adminEmail) === null) {
            $this->userUpsert([
                'email' => $adminEmail,
                'password_hash' => (string) ($this->get('ADMIN_PASSWORD_HASH') ?? ''),
                'full_name' => (string) ($this->get('ADMIN_FULL_NAME') ?? ''),
                'account_type' => 'admin',
                'is_primary' => 1,
                'active' => 1,
                'created_at' => (string) ($this->get('ADMIN_CREATED_AT') ?? date('c')),
                'last_login_at' => (string) ($this->get('ADMIN_LAST_LOGIN_AT') ?? ''),
            ]);
        }

        $usersRaw = (string) ($this->get('USERS_JSON') ?? '');
        $metaRaw = (string) ($this->get('USERS_META_JSON') ?? '');

        $users = [];
        if ($usersRaw !== '') {
            $decoded = json_decode($usersRaw, true);
            if (is_array($decoded)) {
                $users = $decoded;
            }
        }

        $meta = [];
        if ($metaRaw !== '') {
            $decoded = json_decode($metaRaw, true);
            if (is_array($decoded)) {
                $meta = $decoded;
            }
        }

        foreach ($users as $email => $hash) {
            $email = strtolower(trim((string) $email));
            if ($email === '' || $email === $adminEmail || $this->userGet($email) !== null) {
                continue;
            }

            $userMeta = is_array($meta[$email] ?? null) ? $meta[$email] : [];
            $this->userUpsert([
                'email' => $email,
                'password_hash' => (string) $hash,
                'full_name' => (string) ($userMeta['full_name'] ?? ''),
                'account_type' => (string) ($userMeta['account_type'] ?? 'user'),
                'is_primary' => 0,
                'active' => !array_key_exists('active', $userMeta) || (bool) $userMeta['active'],
                'created_at' => (string) ($userMeta['created_at'] ?? date('c')),
                'last_login_at' => (string) ($userMeta['last_login_at'] ?? ''),
            ]);
        }
    }

    private function migrateLegacyIntegrations(): void
    {
        $raw = (string) ($this->get('INTEGRATIONS_JSON') ?? '');
        if ($raw === '') {
            return;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return;
        }

        foreach ($decoded as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $id = trim((string) ($entry['id'] ?? ''));
            if ($id === '' || $this->integrationGet($id) !== null) {
                continue;
            }

            $this->integrationUpsert([
                'id' => $id,
                'name' => (string) ($entry['name'] ?? ''),
                'provider' => (string) ($entry['provider'] ?? ''),
                'category' => (string) ($entry['category'] ?? ''),
                'settings' => is_array($entry['settings'] ?? null) ? $entry['settings'] : [],
                'created_at' => date('c'),
            ]);
        }
    }

    private function migrateLegacyDomains(): void
    {
        $domainsRaw = (string) ($this->get('DOMAINS_JSON') ?? '');
        $cfDomainsRaw = (string) ($this->get('CF_DOMAINS_JSON') ?? '');

        $domainRecords = [];

        if ($domainsRaw !== '' && $domainsRaw !== '[]') {
            $decoded = json_decode($domainsRaw, true);
            if (is_array($decoded)) {
                foreach ($decoded as $entry) {
                    if (is_string($entry)) {
                        $d = strtolower(trim($entry));
                        if ($d !== '') {
                            $domainRecords[$d] = ['domain' => $d, 'cf_zone_id' => '', 'cf_api_token' => '', 'cf_record_ip' => '', 'cf_proxied' => false, 'cf_ttl' => 120];
                        }
                        continue;
                    }
                    if (!is_array($entry)) {
                        continue;
                    }
                    $d = strtolower(trim((string) ($entry['domain'] ?? '')));
                    if ($d === '') {
                        continue;
                    }
                    $cf = is_array($entry['cloudflare'] ?? null) ? $entry['cloudflare'] : [];
                    $domainRecords[$d] = [
                        'domain' => $d,
                        'cf_zone_id' => (string) ($cf['zone_id'] ?? ''),
                        'cf_api_token' => (string) ($cf['api_token'] ?? ''),
                        'cf_record_ip' => (string) ($cf['record_ip'] ?? ''),
                        'cf_proxied' => !empty($cf['proxied']),
                        'cf_ttl' => max(1, (int) ($cf['ttl'] ?? 120)),
                    ];
                }
            }
        }

        if ($cfDomainsRaw !== '' && $cfDomainsRaw !== '[]') {
            $decoded = json_decode($cfDomainsRaw, true);
            if (is_array($decoded)) {
                foreach ($decoded as $entry) {
                    if (!is_array($entry)) {
                        continue;
                    }
                    $d = strtolower(trim((string) ($entry['domain'] ?? '')));
                    if ($d === '') {
                        continue;
                    }
                    if (!isset($domainRecords[$d])) {
                        $domainRecords[$d] = ['domain' => $d, 'cf_zone_id' => '', 'cf_api_token' => '', 'cf_record_ip' => '', 'cf_proxied' => false, 'cf_ttl' => 120];
                    }
                    if ((string) ($entry['zone_id'] ?? '') !== '') {
                        $domainRecords[$d]['cf_zone_id'] = (string) $entry['zone_id'];
                    }
                    if ((string) ($entry['api_token'] ?? '') !== '') {
                        $domainRecords[$d]['cf_api_token'] = (string) $entry['api_token'];
                    }
                }
            }
        }

        foreach ($domainRecords as $record) {
            if ($this->domainGet($record['domain']) === null) {
                $this->domainUpsert($record);
            }
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
