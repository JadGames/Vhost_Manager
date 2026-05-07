<?php

declare(strict_types=1);

namespace App\Core;

final class AppDefaults
{
    /**
     * @return array<string, string>
     */
    public static function values(): array
    {
        $storageDir = self::defaultStorageDir();
        $dataDir = $storageDir . '/data';
        $logsDir = $storageDir . '/logs';

        return [
            'APP_ENV' => 'production',
            'APP_VERSION' => self::defaultAppVersion(),
            'APP_TIMEZONE' => getenv('VHM_TIMEZONE') ?: 'Australia/Brisbane',
            'TRUSTED_PROXIES' => getenv('VHM_TRUSTED_PROXIES') ?: '127.0.0.1,::1,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16',
            'APP_URL' => 'http://localhost:8080',
            'APP_HTTPS' => 'false',
            'SESSION_NAME' => 'VHMSESSID',
            'SESSION_IDLE_TIMEOUT' => '1800',
            'DATA_DIR' => $dataDir,
            'LOG_FILE' => $logsDir . '/app.log',
            'MANAGED_VHOSTS_FILE' => $dataDir . '/vhosts.json',
            'LOGIN_ATTEMPTS_FILE' => $dataDir . '/login_attempts.json',
            'SETTINGS_DB_FILE' => $dataDir . '/settings.sqlite',
            'NOTIFICATIONS_POLL_SECONDS' => getenv('VHM_NOTIFICATIONS_POLL_SECONDS') ?: '120',
            'DOCROOT_BASES_NOTIFY' => 'true',
            'DOCROOT_BASES_LAST_SEEN' => '',
            'PRIV_HELPER' => '/usr/local/sbin/vhost-admin-helper',
            'ALLOWED_DOCROOT_BASES' => getenv('VHM_ALLOWED_DOCROOT_BASES') ?: '/var/www',
            'DEFAULT_DOCROOT_BASE' => getenv('VHM_DEFAULT_DOCROOT_BASE') ?: '/var/www',
            'APACHE_VHOST_TEMPLATE' => '/etc/vhost-manager/vhost.conf.tpl',
            'VHOST_BASE_DOMAIN' => '',
            'CURL_VERIFY_SSL' => 'true',
            'PASSWORD_POLICY_LEVEL' => (int) (getenv('VHM_PASSWORD_POLICY_LEVEL') ?: '3'),
            'ENABLE_INTEGRATIONS' => (bool) filter_var(getenv('VHM_ENABLE_INTEGRATIONS') ?? 'true', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true,
        ];
    }

    private static function defaultStorageDir(): string
    {
        $envStorage = trim((string) (getenv('VHM_STORAGE_DIR') ?: ''));
        if ($envStorage !== '') {
            $normalized = rtrim($envStorage, '/');
            if (self::isUsableStorageDir($normalized)) {
                return $normalized;
            }
        }

        $candidates = [];

        if (is_dir('/opt/vhost-manager')) {
            $candidates[] = '/opt/vhost-manager/storage';
        }

        $candidates[] = dirname(__DIR__, 2) . '/storage';

        foreach ($candidates as $candidate) {
            if (self::isUsableStorageDir($candidate)) {
                return rtrim($candidate, '/');
            }
        }

        $tmpStorage = rtrim(sys_get_temp_dir(), '/') . '/vhost-manager-' . get_current_user();
        self::isUsableStorageDir($tmpStorage);

        return $tmpStorage;
    }

    private static function isUsableStorageDir(string $storageDir): bool
    {
        $storageDir = rtrim($storageDir, '/');
        if ($storageDir === '') {
            return false;
        }

        if (!is_dir($storageDir) && !@mkdir($storageDir, 0750, true) && !is_dir($storageDir)) {
            return false;
        }

        if (!is_writable($storageDir)) {
            return false;
        }

        foreach (['data', 'logs'] as $segment) {
            $path = $storageDir . '/' . $segment;
            if (!is_dir($path) && !@mkdir($path, 0750, true) && !is_dir($path)) {
                return false;
            }

            if (!is_writable($path)) {
                return false;
            }
        }

        $writeSensitiveFiles = [
            $storageDir . '/data/settings.sqlite',
            $storageDir . '/data/settings.sqlite-wal',
            $storageDir . '/data/settings.sqlite-shm',
            $storageDir . '/data/login_attempts.json',
        ];

        foreach ($writeSensitiveFiles as $filePath) {
            if (is_file($filePath) && !is_writable($filePath)) {
                return false;
            }
        }

        return true;
    }

    private static function defaultAppVersion(): string
    {
        $envVersion = trim((string) (getenv('VHM_VERSION') ?: ''));
        if ($envVersion !== '') {
            return $envVersion;
        }

        $versionFile = '/opt/vhost-manager/.vhm-version';
        if (is_file($versionFile) && is_readable($versionFile)) {
            $fileVersion = trim((string) @file_get_contents($versionFile));
            if ($fileVersion !== '') {
                return $fileVersion;
            }
        }

        return 'dev';
    }
}
