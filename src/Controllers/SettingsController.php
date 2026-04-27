<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Session;
use App\Security\Csrf;
use App\Security\DomainValidator;
use App\Services\SettingsStore;
use RuntimeException;

final class SettingsController extends BaseController
{
    public function __construct(
        Config $config,
        private readonly Csrf $csrf,
        private readonly SettingsStore $settingsStore
    ) {
        parent::__construct($config);
    }

    public function show(): void
    {
        $allowedDocrootBases = $this->allowedDocrootBases();

        $this->render('settings/index.php', [
            'csrfToken' => $this->csrf->token(),
            'appUrl' => (string) $this->config->get('APP_URL', 'http://localhost'),
            'appHttps' => $this->config->getBool('APP_HTTPS', false),
            'sessionName' => (string) $this->config->get('SESSION_NAME', 'VHMSESSID'),
            'sessionIdleTimeout' => (int) $this->config->get('SESSION_IDLE_TIMEOUT', 1800),
            'allowedDocrootBases' => $allowedDocrootBases,
            'defaultDocrootBase' => (string) $this->config->get('DEFAULT_DOCROOT_BASE', '/var/www'),
            'baseDomain' => (string) $this->config->get('VHOST_BASE_DOMAIN', ''),
            'cfEnabled' => $this->config->getBool('CF_ENABLED', false),
            'npmEnabled' => $this->config->getBool('NPM_ENABLED', false),
            'usersCount' => count($this->usersFromStore()),
            'cfDomainMappingsCount' => count($this->cloudflareDomainsFromStore()),
        ]);
    }

    public function saveGeneral(): void
    {
        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            Session::setFlash('error', 'Invalid CSRF token.');
            $this->redirect('settings');
        }

        $appUrl = trim((string) ($_POST['app_url'] ?? ''));
        $appHttps = $this->postBool('app_https');
        $sessionName = trim((string) ($_POST['session_name'] ?? ''));
        $sessionIdleTimeout = (int) ($_POST['session_idle_timeout'] ?? 1800);
        $allowedDocrootBasesRaw = $_POST['allowed_docroot_bases'] ?? [];
        $defaultDocrootBase = trim((string) ($_POST['default_docroot_base'] ?? ''));
        $baseDomain = strtolower(trim((string) ($_POST['vhost_base_domain'] ?? '')));

        if ($appUrl === '' || filter_var($appUrl, FILTER_VALIDATE_URL) === false) {
            Session::setFlash('error', 'App URL must be a valid URL.');
            $this->redirect('settings');
        }

        if (!preg_match('/^[A-Za-z0-9_-]{3,40}$/', $sessionName)) {
            Session::setFlash('error', 'Session name must be 3-40 characters and use letters, numbers, underscores or dashes.');
            $this->redirect('settings');
        }

        if ($sessionIdleTimeout < 300 || $sessionIdleTimeout > 86400) {
            Session::setFlash('error', 'Session idle timeout must be between 300 and 86400 seconds.');
            $this->redirect('settings');
        }

        if ($baseDomain !== '' && !DomainValidator::isValid($baseDomain)) {
            Session::setFlash('error', 'Base domain must be a valid domain name.');
            $this->redirect('settings');
        }

        $bases = $this->normalizeAllowedDocrootBases($allowedDocrootBasesRaw);
        if ($bases === []) {
            Session::setFlash('error', 'At least one allowed document root base is required.');
            $this->redirect('settings');
        }

        if ($defaultDocrootBase === '' || !in_array($defaultDocrootBase, $bases, true)) {
            Session::setFlash('error', 'Default document root base must be one of the allowed bases.');
            $this->redirect('settings');
        }

        try {
            $this->settingsStore->setMany([
                'APP_URL' => $appUrl,
                'APP_HTTPS' => $appHttps ? 'true' : 'false',
                'SESSION_NAME' => $sessionName,
                'SESSION_IDLE_TIMEOUT' => (string) $sessionIdleTimeout,
                'ALLOWED_DOCROOT_BASES' => implode(',', $bases),
                'DEFAULT_DOCROOT_BASE' => $defaultDocrootBase,
                'VHOST_BASE_DOMAIN' => $baseDomain,
            ]);
        } catch (RuntimeException $e) {
            Session::setFlash('error', $e->getMessage());
            $this->redirect('settings');
        }

        Session::setFlash('success', 'Settings saved. They will apply on the next request.');
        $this->redirect('settings');
    }

    public function docrootDetectionAction(): void
    {
        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            Session::setFlash('error', 'Invalid CSRF token.');
            $this->redirect('dashboard');
        }

        $intent = trim((string) ($_POST['intent'] ?? ''));

        try {
            if ($intent === 'disable-notifications') {
                $this->settingsStore->setMany([
                    'DOCROOT_BASES_NOTIFY' => 'false',
                ]);
                Session::setFlash('success', 'Docroot detection notifications disabled.');
                $this->redirect('dashboard');
            }

            if ($intent === 'set-default-base') {
                $selectedBase = trim((string) ($_POST['default_docroot_base'] ?? ''));
                $allowedBases = $this->allowedDocrootBases();

                if ($selectedBase === '' || !in_array($selectedBase, $allowedBases, true)) {
                    Session::setFlash('error', 'Selected default docroot base is not allowed.');
                    $this->redirect('dashboard');
                }

                $this->settingsStore->setMany([
                    'DEFAULT_DOCROOT_BASE' => $selectedBase,
                ]);
                Session::setFlash('success', 'Default docroot base updated.');
                $this->redirect('dashboard');
            }
        } catch (RuntimeException $e) {
            Session::setFlash('error', $e->getMessage());
            $this->redirect('dashboard');
        }

        Session::setFlash('error', 'Invalid docroot detection action.');
        $this->redirect('dashboard');
    }

    /**
     * @return array<int, string>
     */
    private function allowedDocrootBases(): array
    {
        $bases = $this->normalizeAllowedDocrootBases((string) $this->config->get('ALLOWED_DOCROOT_BASES', '/var/www'));

        return $bases !== [] ? $bases : ['/var/www'];
    }

    /**
     * @param mixed $raw
     * @return array<int, string>
     */
    private function normalizeAllowedDocrootBases(mixed $raw): array
    {
        $values = [];
        if (is_array($raw)) {
            $values = $raw;
        } elseif (is_string($raw)) {
            $values = explode(',', $raw);
        }

        $bases = [];
        foreach ($values as $value) {
            $base = trim((string) $value);
            if ($base === '') {
                continue;
            }

            if (!str_starts_with($base, '/')) {
                continue;
            }

            if (!in_array($base, $bases, true)) {
                $bases[] = $base;
            }
        }

        return $bases;
    }

    public function showUsers(): void
    {
        $adminUser = (string) $this->config->get('ADMIN_USER', 'admin');
        $this->render('settings/users.php', [
            'csrfToken' => $this->csrf->token(),
            'adminUser' => $adminUser,
            'users' => $this->usersFromStore(),
        ]);
    }

    public function usersAction(): void
    {
        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            Session::setFlash('error', 'Invalid CSRF token.');
            $this->redirect('settings-users');
        }

        $intent = trim((string) ($_POST['intent'] ?? ''));

        try {
            switch ($intent) {
                case 'admin-update':
                    $this->handleAdminUsernameUpdate();
                    break;
                case 'user-add':
                    $this->handleUserAdd();
                    break;
                case 'user-reset':
                    $this->handleUserPasswordReset();
                    break;
                case 'user-delete':
                    $this->handleUserDelete();
                    break;
                default:
                    throw new RuntimeException('Invalid users action.');
            }
        } catch (RuntimeException $e) {
            Session::setFlash('error', $e->getMessage());
            $this->redirect('settings-users');
        }

        Session::setFlash('success', 'Users settings updated.');
        $this->redirect('settings-users');
    }

    public function showCloudflare(): void
    {
        $this->render('settings/cloudflare.php', [
            'csrfToken' => $this->csrf->token(),
            'cfEnabled' => $this->config->getBool('CF_ENABLED', false),
            'cfApiToken' => (string) $this->config->get('CF_API_TOKEN', ''),
            'cfZoneId' => (string) $this->config->get('CF_ZONE_ID', ''),
            'cfRecordIp' => (string) $this->config->get('CF_RECORD_IP', ''),
            'cfProxied' => $this->config->getBool('CF_PROXIED', false),
            'cfTtl' => (int) $this->config->get('CF_TTL', 120),
            'mappingsCount' => count($this->cloudflareDomainsFromStore()),
        ]);
    }

    public function saveCloudflare(): void
    {
        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            Session::setFlash('error', 'Invalid CSRF token.');
            $this->redirect('settings-cloudflare');
        }

        $enabled = $this->postBool('cf_enabled');
        $apiToken = trim((string) ($_POST['cf_api_token'] ?? ''));
        $zoneId = trim((string) ($_POST['cf_zone_id'] ?? ''));
        $recordIp = trim((string) ($_POST['cf_record_ip'] ?? ''));
        $proxied = $this->postBool('cf_proxied');
        $ttl = (int) ($_POST['cf_ttl'] ?? 120);

        if ($enabled && ($apiToken === '' || $zoneId === '' || $recordIp === '')) {
            Session::setFlash('error', 'CF API token, zone ID and record IP are required when Cloudflare is enabled.');
            $this->redirect('settings-cloudflare');
        }

        if ($recordIp !== '' && filter_var($recordIp, FILTER_VALIDATE_IP) === false) {
            Session::setFlash('error', 'Cloudflare record IP must be a valid IPv4 or IPv6 address.');
            $this->redirect('settings-cloudflare');
        }

        if ($zoneId !== '' && preg_match('/^[a-f0-9]{32}$/i', $zoneId) !== 1) {
            Session::setFlash('error', 'Cloudflare zone ID must be a 32-character hexadecimal value.');
            $this->redirect('settings-cloudflare');
        }

        if ($ttl < 1 || $ttl > 86400) {
            Session::setFlash('error', 'Cloudflare TTL must be between 1 and 86400 seconds.');
            $this->redirect('settings-cloudflare');
        }

        try {
            $this->settingsStore->setMany([
                'CF_ENABLED' => $enabled ? 'true' : 'false',
                'CF_API_TOKEN' => $apiToken,
                'CF_ZONE_ID' => $zoneId,
                'CF_RECORD_IP' => $recordIp,
                'CF_PROXIED' => $proxied ? 'true' : 'false',
                'CF_TTL' => (string) $ttl,
            ]);
        } catch (RuntimeException $e) {
            Session::setFlash('error', $e->getMessage());
            $this->redirect('settings-cloudflare');
        }

        Session::setFlash('success', 'Cloudflare settings saved.');
        $this->redirect('settings-cloudflare');
    }

    public function showCloudflareDomains(): void
    {
        $this->render('settings/cloudflare-domains.php', [
            'csrfToken' => $this->csrf->token(),
            'mappings' => $this->cloudflareDomainsFromStore(),
        ]);
    }

    public function cloudflareDomainsAction(): void
    {
        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            Session::setFlash('error', 'Invalid CSRF token.');
            $this->redirect('settings-cloudflare-domains');
        }

        $intent = trim((string) ($_POST['intent'] ?? ''));
        $mappings = $this->cloudflareDomainsFromStore();

        if ($intent === 'add') {
            $domain = strtolower(trim((string) ($_POST['domain'] ?? '')));
            $zoneId = trim((string) ($_POST['zone_id'] ?? ''));
            $apiToken = trim((string) ($_POST['api_token'] ?? ''));

            if (!DomainValidator::isValid($domain)) {
                Session::setFlash('error', 'Domain must be a valid FQDN.');
                $this->redirect('settings-cloudflare-domains');
            }

            if (preg_match('/^[a-f0-9]{32}$/i', $zoneId) !== 1) {
                Session::setFlash('error', 'Zone ID must be a 32-character hexadecimal value.');
                $this->redirect('settings-cloudflare-domains');
            }

            if ($apiToken === '') {
                Session::setFlash('error', 'API token is required for domain mapping.');
                $this->redirect('settings-cloudflare-domains');
            }

            $replaced = false;
            foreach ($mappings as $index => $mapping) {
                if (($mapping['domain'] ?? '') === $domain) {
                    $mappings[$index] = ['domain' => $domain, 'zone_id' => $zoneId, 'api_token' => $apiToken];
                    $replaced = true;
                    break;
                }
            }

            if (!$replaced) {
                $mappings[] = ['domain' => $domain, 'zone_id' => $zoneId, 'api_token' => $apiToken];
            }

            usort($mappings, static fn (array $a, array $b): int => strcasecmp((string) ($a['domain'] ?? ''), (string) ($b['domain'] ?? '')));

            $this->persistCloudflareMappings($mappings);
            Session::setFlash('success', 'Cloudflare domain mapping saved.');
            $this->redirect('settings-cloudflare-domains');
        }

        if ($intent === 'delete') {
            $domain = strtolower(trim((string) ($_POST['domain'] ?? '')));
            $mappings = array_values(array_filter(
                $mappings,
                static fn (array $mapping): bool => strtolower((string) ($mapping['domain'] ?? '')) !== $domain
            ));

            $this->persistCloudflareMappings($mappings);
            Session::setFlash('success', 'Cloudflare domain mapping removed.');
            $this->redirect('settings-cloudflare-domains');
        }

        Session::setFlash('error', 'Invalid Cloudflare domains action.');
        $this->redirect('settings-cloudflare-domains');
    }

    public function showNpm(): void
    {
        $this->render('settings/npm.php', [
            'csrfToken' => $this->csrf->token(),
            'npmEnabled' => $this->config->getBool('NPM_ENABLED', false),
            'npmBaseUrl' => (string) $this->config->get('NPM_BASE_URL', 'http://localhost:81'),
            'npmIdentity' => (string) $this->config->get('NPM_IDENTITY', ''),
            'npmSecret' => (string) $this->config->get('NPM_SECRET', ''),
            'npmForwardHost' => (string) $this->config->get('NPM_FORWARD_HOST', '127.0.0.1'),
            'npmForwardPort' => (int) $this->config->get('NPM_FORWARD_PORT', 80),
        ]);
    }

    public function saveNpm(): void
    {
        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            Session::setFlash('error', 'Invalid CSRF token.');
            $this->redirect('settings-npm');
        }

        $enabled = $this->postBool('npm_enabled');
        $baseUrl = trim((string) ($_POST['npm_base_url'] ?? ''));
        $identity = trim((string) ($_POST['npm_identity'] ?? ''));
        $secret = trim((string) ($_POST['npm_secret'] ?? ''));
        $forwardHost = trim((string) ($_POST['npm_forward_host'] ?? ''));
        $forwardPort = (int) ($_POST['npm_forward_port'] ?? 80);

        if ($enabled) {
            if ($baseUrl === '' || filter_var($baseUrl, FILTER_VALIDATE_URL) === false) {
                Session::setFlash('error', 'NPM Base URL must be a valid URL.');
                $this->redirect('settings-npm');
            }
            if ($identity === '' || $secret === '') {
                Session::setFlash('error', 'NPM identity and secret are required when NPM is enabled.');
                $this->redirect('settings-npm');
            }
        }

        if ($forwardHost === '' || !preg_match('/^[a-zA-Z0-9.-]+$/', $forwardHost)) {
            Session::setFlash('error', 'NPM forward host contains invalid characters.');
            $this->redirect('settings-npm');
        }

        if ($forwardPort < 1 || $forwardPort > 65535) {
            Session::setFlash('error', 'NPM forward port must be between 1 and 65535.');
            $this->redirect('settings-npm');
        }

        try {
            $this->settingsStore->setMany([
                'NPM_ENABLED' => $enabled ? 'true' : 'false',
                'NPM_BASE_URL' => $baseUrl,
                'NPM_IDENTITY' => $identity,
                'NPM_SECRET' => $secret,
                'NPM_FORWARD_HOST' => $forwardHost,
                'NPM_FORWARD_PORT' => (string) $forwardPort,
            ]);
        } catch (RuntimeException $e) {
            Session::setFlash('error', $e->getMessage());
            $this->redirect('settings-npm');
        }

        Session::setFlash('success', 'NPM settings saved.');
        $this->redirect('settings-npm');
    }

    public function showNpmSsl(): void
    {
        $this->render('settings/npm-ssl.php', [
            'csrfToken' => $this->csrf->token(),
            'npmSslEnabled' => $this->config->getBool('NPM_SSL_ENABLED', false),
            'npmCertificateId' => (int) $this->config->get('NPM_CERTIFICATE_ID', 0),
            'npmSslForced' => $this->config->getBool('NPM_SSL_FORCED', false),
            'npmHttp2Support' => $this->config->getBool('NPM_HTTP2_SUPPORT', false),
            'npmHstsEnabled' => $this->config->getBool('NPM_HSTS_ENABLED', false),
            'npmHstsSubdomains' => $this->config->getBool('NPM_HSTS_SUBDOMAINS', false),
        ]);
    }

    public function saveNpmSsl(): void
    {
        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            Session::setFlash('error', 'Invalid CSRF token.');
            $this->redirect('settings-npm-ssl');
        }

        $sslEnabled = $this->postBool('npm_ssl_enabled');
        $certificateId = (int) ($_POST['npm_certificate_id'] ?? 0);
        $sslForced = $this->postBool('npm_ssl_forced');
        $http2Support = $this->postBool('npm_http2_support');
        $hstsEnabled = $this->postBool('npm_hsts_enabled');
        $hstsSubdomains = $this->postBool('npm_hsts_subdomains');

        if ($sslEnabled && $certificateId <= 0) {
            Session::setFlash('error', 'Certificate ID is required when NPM SSL is enabled.');
            $this->redirect('settings-npm-ssl');
        }

        try {
            $this->settingsStore->setMany([
                'NPM_SSL_ENABLED' => $sslEnabled ? 'true' : 'false',
                'NPM_CERTIFICATE_ID' => (string) $certificateId,
                'NPM_SSL_FORCED' => $sslForced ? 'true' : 'false',
                'NPM_HTTP2_SUPPORT' => $http2Support ? 'true' : 'false',
                'NPM_HSTS_ENABLED' => $hstsEnabled ? 'true' : 'false',
                'NPM_HSTS_SUBDOMAINS' => $hstsSubdomains ? 'true' : 'false',
            ]);
        } catch (RuntimeException $e) {
            Session::setFlash('error', $e->getMessage());
            $this->redirect('settings-npm-ssl');
        }

        Session::setFlash('success', 'NPM SSL settings saved.');
        $this->redirect('settings-npm-ssl');
    }

    public function changePassword(): void
    {
        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            Session::setFlash('error', 'Invalid CSRF token.');
            $this->redirect('settings');
        }

        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        $existingHash = (string) $this->config->get('ADMIN_PASSWORD_HASH', '');
        if ($existingHash !== '' && !password_verify($currentPassword, $existingHash)) {
            Session::setFlash('error', 'Current password is incorrect.');
            $this->redirect('settings');
        }

        $passwordErrors = password_policy_errors($newPassword);
        if ($passwordErrors !== []) {
            Session::setFlash('error', $passwordErrors[0]);
            $this->redirect('settings');
        }

        if ($newPassword !== $confirmPassword) {
            Session::setFlash('error', 'New password and confirmation do not match.');
            $this->redirect('settings');
        }

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        if ($hash === false) {
            Session::setFlash('error', 'Failed to generate password hash.');
            $this->redirect('settings');
        }

        try {
            $this->settingsStore->setMany([
                'ADMIN_PASSWORD_HASH' => $hash,
            ]);
        } catch (RuntimeException $e) {
            Session::setFlash('error', $e->getMessage());
            $this->redirect('settings');
        }

        Session::setFlash('success', 'Password updated successfully.');
        $this->redirect('settings');
    }

    /**
     * @return array<string, string>
     */
    private function usersFromStore(): array
    {
        $raw = (string) $this->config->get('USERS_JSON', '');
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $users = [];
        foreach ($decoded as $username => $hash) {
            $name = trim((string) $username);
            $passwordHash = trim((string) $hash);
            if ($name === '' || $passwordHash === '') {
                continue;
            }
            $users[$name] = $passwordHash;
        }

        ksort($users, SORT_NATURAL | SORT_FLAG_CASE);

        return $users;
    }

    /**
     * @return list<array{domain:string, zone_id:string, api_token:string}>
     */
    private function cloudflareDomainsFromStore(): array
    {
        $raw = (string) $this->config->get('CF_DOMAINS_JSON', '');
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $rows = [];
        foreach ($decoded as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $domain = strtolower(trim((string) ($entry['domain'] ?? '')));
            $zoneId = trim((string) ($entry['zone_id'] ?? ''));
            $apiToken = trim((string) ($entry['api_token'] ?? ''));

            if ($domain === '' || $zoneId === '' || $apiToken === '') {
                continue;
            }

            $rows[] = [
                'domain' => $domain,
                'zone_id' => $zoneId,
                'api_token' => $apiToken,
            ];
        }

        return $rows;
    }

    /**
     * @param list<array{domain:string, zone_id:string, api_token:string}> $mappings
     */
    private function persistCloudflareMappings(array $mappings): void
    {
        $this->settingsStore->setMany([
            'CF_DOMAINS_JSON' => json_encode($mappings, JSON_UNESCAPED_SLASHES) ?: '[]',
        ]);
    }

    private function handleAdminUsernameUpdate(): void
    {
        $newAdmin = trim((string) ($_POST['admin_user'] ?? ''));
        if (!preg_match('/^[a-zA-Z0-9._-]{3,64}$/', $newAdmin)) {
            throw new RuntimeException('Admin username must be 3-64 chars and use letters, numbers, dots, dashes, or underscores.');
        }

        $users = $this->usersFromStore();
        if (array_key_exists($newAdmin, $users)) {
            throw new RuntimeException('That username already exists as an additional user.');
        }

        $this->settingsStore->setMany(['ADMIN_USER' => $newAdmin]);
        $_SESSION['username'] = $newAdmin;
    }

    private function handleUserAdd(): void
    {
        $username = trim((string) ($_POST['new_user'] ?? ''));
        $password = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['new_password_confirm'] ?? '');
        $adminUser = (string) $this->config->get('ADMIN_USER', 'admin');

        if (!preg_match('/^[a-zA-Z0-9._-]{3,64}$/', $username)) {
            throw new RuntimeException('Username must be 3-64 chars and use letters, numbers, dots, dashes, or underscores.');
        }

        if ($username === $adminUser) {
            throw new RuntimeException('That username is reserved by the primary admin account.');
        }

        $passwordErrors = password_policy_errors($password);
        if ($passwordErrors !== []) {
            throw new RuntimeException($passwordErrors[0]);
        }

        if ($password !== $confirm) {
            throw new RuntimeException('Password confirmation does not match.');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        if ($hash === false) {
            throw new RuntimeException('Failed to generate password hash.');
        }

        $users = $this->usersFromStore();
        if (array_key_exists($username, $users)) {
            throw new RuntimeException('Username already exists.');
        }

        $users[$username] = $hash;
        ksort($users, SORT_NATURAL | SORT_FLAG_CASE);

        $this->settingsStore->setMany([
            'USERS_JSON' => json_encode($users, JSON_UNESCAPED_SLASHES) ?: '{}',
        ]);
    }

    private function handleUserPasswordReset(): void
    {
        $targetUser = trim((string) ($_POST['target_user'] ?? ''));
        $password = (string) ($_POST['reset_password'] ?? '');
        $confirm = (string) ($_POST['reset_password_confirm'] ?? '');
        $adminUser = (string) $this->config->get('ADMIN_USER', 'admin');

        if ($targetUser === '') {
            throw new RuntimeException('Target user is required.');
        }

        $passwordErrors = password_policy_errors($password);
        if ($passwordErrors !== []) {
            throw new RuntimeException($passwordErrors[0]);
        }

        if ($password !== $confirm) {
            throw new RuntimeException('Password confirmation does not match.');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        if ($hash === false) {
            throw new RuntimeException('Failed to generate password hash.');
        }

        if ($targetUser === $adminUser) {
            $this->settingsStore->setMany(['ADMIN_PASSWORD_HASH' => $hash]);

            return;
        }

        $users = $this->usersFromStore();
        if (!array_key_exists($targetUser, $users)) {
            throw new RuntimeException('User not found.');
        }

        $users[$targetUser] = $hash;

        $this->settingsStore->setMany([
            'USERS_JSON' => json_encode($users, JSON_UNESCAPED_SLASHES) ?: '{}',
        ]);
    }

    private function handleUserDelete(): void
    {
        $targetUser = trim((string) ($_POST['target_user'] ?? ''));
        $adminUser = (string) $this->config->get('ADMIN_USER', 'admin');

        if ($targetUser === '' || $targetUser === $adminUser) {
            throw new RuntimeException('Only additional users can be deleted.');
        }

        $users = $this->usersFromStore();
        if (!array_key_exists($targetUser, $users)) {
            throw new RuntimeException('User not found.');
        }

        unset($users[$targetUser]);

        $this->settingsStore->setMany([
            'USERS_JSON' => json_encode($users, JSON_UNESCAPED_SLASHES) ?: '{}',
        ]);
    }

    private function postBool(string $key): bool
    {
        return isset($_POST[$key]) && (string) $_POST[$key] === '1';
    }
}
