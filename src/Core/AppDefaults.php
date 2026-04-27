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
            'ADMIN_USER' => '',
            'ADMIN_PASSWORD_HASH' => '',
            'USERS_JSON' => '{}',
            'DATA_DIR' => $dataDir,
            'LOG_FILE' => $logsDir . '/app.log',
            'MANAGED_VHOSTS_FILE' => $dataDir . '/vhosts.json',
            'LOGIN_ATTEMPTS_FILE' => $dataDir . '/login_attempts.json',
            'SETTINGS_DB_FILE' => $dataDir . '/settings.sqlite',
            'DOCROOT_BASES_NOTIFY' => 'true',
            'DOCROOT_BASES_LAST_SEEN' => '',
            'PRIV_HELPER' => '/usr/local/sbin/vhost-admin-helper',
            'ALLOWED_DOCROOT_BASES' => getenv('VHM_ALLOWED_DOCROOT_BASES') ?: '/var/www',
            'DEFAULT_DOCROOT_BASE' => getenv('VHM_DEFAULT_DOCROOT_BASE') ?: '/var/www',
            'APACHE_VHOST_TEMPLATE' => '/etc/vhost-manager/vhost.conf.tpl',
            'VHOST_BASE_DOMAIN' => '',
            'CURL_VERIFY_SSL' => 'true',
            'CF_ENABLED' => 'false',
            'CF_API_TOKEN' => '',
            'CF_ZONE_ID' => '',
            'CF_RECORD_IP' => '',
            'CF_PROXIED' => 'false',
            'CF_TTL' => '120',
            'CF_DOMAINS_JSON' => '[]',
            'NPM_ENABLED' => 'false',
            'NPM_BASE_URL' => 'http://localhost:81',
            'NPM_IDENTITY' => '',
            'NPM_SECRET' => '',
            'NPM_FORWARD_HOST' => '127.0.0.1',
            'NPM_FORWARD_PORT' => '80',
            'NPM_SSL_ENABLED' => 'false',
            'NPM_CERTIFICATE_ID' => '0',
            'NPM_SSL_FORCED' => 'false',
            'NPM_HTTP2_SUPPORT' => 'false',
            'NPM_HSTS_ENABLED' => 'false',
            'NPM_HSTS_SUBDOMAINS' => 'false',
            'PROXY_MODE' => 'builtin_npm',
        ];
    }

    private static function defaultStorageDir(): string
    {
        $envStorage = trim((string) (getenv('VHM_STORAGE_DIR') ?: ''));
        if ($envStorage !== '') {
            return rtrim($envStorage, '/');
        }

        if (is_dir('/opt/vhost-manager')) {
            return '/opt/vhost-manager/storage';
        }

        return dirname(__DIR__, 2) . '/storage';
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
