<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Session;
use App\Security\Csrf;
use App\Services\ApacheModulesService;
use App\Services\HttpClient;
use App\Services\IntegrationRegistry;
use App\Services\Logger;
use App\Services\NpmService;
use App\Security\DomainValidator;
use App\Services\SettingsStore;
use RuntimeException;

final class SettingsController extends BaseController
{
    public function __construct(
        \App\Core\Config $config,
        private readonly Csrf $csrf,
        private readonly SettingsStore $settingsStore,
        private readonly ApacheModulesService $apacheModules,
        private readonly HttpClient $httpClient
    ) {
        parent::__construct($config);
    }

    public function show(): void
    {
        $allowedDocrootBases = $this->allowedDocrootBases();
        $apacheModules = [];
        $additionalUsers = $this->usersFromStore();
        $integrations = $this->integrationsFromStore();

        try {
            $apacheModules = $this->apacheModules->listModules();
        } catch (RuntimeException) {
            $apacheModules = [];
        }

        $enabledModuleCount = count(array_filter(
            $apacheModules,
            static fn (array $module): bool => !empty($module['enabled'])
        ));

        $this->render('settings/index.php', [
            'csrfToken' => $this->csrf->token(),
            'appUrl' => (string) $this->config->get('APP_URL', 'http://localhost'),
            'appHttps' => $this->config->getBool('APP_HTTPS', false),
            'allowedDocrootBases' => $allowedDocrootBases,
            'defaultDocrootBase' => (string) $this->config->get('DEFAULT_DOCROOT_BASE', '/var/www'),
            'baseDomain' => (string) $this->config->get('VHOST_BASE_DOMAIN', ''),
            'domainOptions' => $this->domainOptionsFromStore(),
            'adminUser' => (string) $this->config->get('ADMIN_USER', 'admin@example.com'),
            'additionalUsers' => $additionalUsers,
            'cfEnabled' => $this->config->getBool('CF_ENABLED', false),
            'npmEnabled' => $this->config->getBool('NPM_ENABLED', false),
            'usersCount' => count($additionalUsers),
            'cfDomainMappingsCount' => count($this->cloudflareDomainsFromStore()),
            'apacheModulesCount' => count($apacheModules),
            'apacheModules' => $apacheModules,
            'enabledModuleCount' => $enabledModuleCount,
            'integrations' => $integrations,
        ]);
    }

    public function showIntegrations(): void
    {
        $integrations = $this->integrationsFromStore();

        $this->render('settings/integrations.php', [
            'csrfToken' => $this->csrf->token(),
            'providers' => IntegrationRegistry::providers(),
            'integrations' => $integrations,
            'proxyIntegrations' => IntegrationRegistry::filterByCategory($integrations, 'proxy'),
            'dnsIntegrations' => IntegrationRegistry::filterByCategory($integrations, 'dns'),
        ]);
    }

    public function integrationsAction(): void
    {
        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            Session::setFlash('error', 'Invalid CSRF token.');
            $this->redirect('settings-integrations');
        }

        $integrations = $this->integrationsFromStore();
        $providers = IntegrationRegistry::providers();
        $intent = trim((string) ($_POST['intent'] ?? ''));

        try {
            switch ($intent) {
                case 'add':
                    $providerKey = trim((string) ($_POST['provider'] ?? ''));
                    if (!isset($providers[$providerKey])) {
                        throw new RuntimeException('Invalid integration provider.');
                    }

                    if ($providerKey === 'cloudflare') {
                        throw new RuntimeException('Use the Cloudflare enable flow from the integration modal.');
                    }

                    $name = trim((string) ($_POST['name'] ?? ''));
                    if ($name === '') {
                        throw new RuntimeException('Integration name is required.');
                    }

                    $integrations[] = [
                        'id' => str_replace('.', '', uniqid($providerKey . '_', true)),
                        'name' => $name,
                        'provider' => $providerKey,
                        'category' => (string) $providers[$providerKey]['category'],
                        'settings' => $this->normalizeIntegrationSettings(
                            $providerKey,
                            is_array($_POST['settings'] ?? null) ? $_POST['settings'] : []
                        ),
                    ];
                    Session::setFlash('success', 'Integration added.');
                    break;

                case 'update':
                    $id = trim((string) ($_POST['id'] ?? ''));
                    $name = trim((string) ($_POST['name'] ?? ''));
                    if ($id === '') {
                        throw new RuntimeException('Integration ID is required.');
                    }
                    if ($name === '') {
                        throw new RuntimeException('Integration name is required.');
                    }

                    $index = null;
                    foreach ($integrations as $candidateIndex => $integration) {
                        if ((string) ($integration['id'] ?? '') === $id) {
                            $index = $candidateIndex;
                            break;
                        }
                    }

                    if ($index === null) {
                        throw new RuntimeException('Integration not found.');
                    }

                    $existing = $integrations[$index];
                    $providerKey = (string) ($existing['provider'] ?? '');
                    if (!isset($providers[$providerKey])) {
                        throw new RuntimeException('Invalid integration provider.');
                    }

                    $integrations[$index] = [
                        'id' => $id,
                        'name' => $name,
                        'provider' => $providerKey,
                        'category' => (string) $providers[$providerKey]['category'],
                        'settings' => $this->normalizeIntegrationSettings(
                            $providerKey,
                            is_array($_POST['settings'] ?? null) ? $_POST['settings'] : [],
                            is_array($existing['settings'] ?? null) ? $existing['settings'] : []
                        ),
                    ];
                    Session::setFlash('success', 'Integration updated.');
                    break;

                case 'delete':
                    $id = trim((string) ($_POST['id'] ?? ''));
                    if ($id === '') {
                        throw new RuntimeException('Integration ID is required.');
                    }

                    $remaining = array_values(array_filter(
                        $integrations,
                        static fn (array $integration): bool => (string) ($integration['id'] ?? '') !== $id
                    ));

                    if (count($remaining) === count($integrations)) {
                        throw new RuntimeException('Integration not found.');
                    }

                    $integrations = $remaining;
                    Session::setFlash('success', 'Integration removed.');
                    break;

                default:
                    throw new RuntimeException('Invalid integrations action.');
            }

            usort($integrations, static function (array $a, array $b): int {
                $categoryCompare = strcmp((string) ($a['category'] ?? ''), (string) ($b['category'] ?? ''));
                if ($categoryCompare !== 0) {
                    return $categoryCompare;
                }

                return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
            });

            $this->persistIntegrations($integrations);
            $this->syncLegacyIntegrationSettings($integrations);
        } catch (RuntimeException $e) {
            Session::setFlash('error', $e->getMessage());
        }

        $this->redirect('settings-integrations');
    }

    public function integrationsTestAction(): void
    {
        header('Content-Type: application/json');

        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Invalid CSRF token.']);
            return;
        }

        $id = trim((string) ($_POST['id'] ?? ''));
        foreach ($this->integrationsFromStore() as $integration) {
            if ((string) ($integration['id'] ?? '') !== $id) {
                continue;
            }

            try {
                $this->testIntegration($integration);
                echo json_encode(['ok' => true]);
            } catch (RuntimeException $e) {
                http_response_code(422);
                echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
            }

            return;
        }

        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Integration not found.']);
    }

    public function integrationsNpmBootstrapAction(): void
    {
        header('Content-Type: application/json');

        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Invalid CSRF token.']);
            return;
        }

        $baseUrl = trim((string) ($_POST['base_url'] ?? ''));
        $adminIdentity = strtolower(trim((string) ($_POST['admin_identity'] ?? '')));
        $adminSecret = trim((string) ($_POST['admin_secret'] ?? ''));

        try {
            if ($baseUrl === '' || filter_var($baseUrl, FILTER_VALIDATE_URL) === false) {
                throw new RuntimeException('NPM Base URL must be a valid URL.');
            }
            if (filter_var($adminIdentity, FILTER_VALIDATE_EMAIL) === false || $adminSecret === '') {
                throw new RuntimeException('Admin email and password are required.');
            }

            $provisioned = $this->provisionNpmServiceAccount($baseUrl, $adminIdentity, $adminSecret);
            $bootstrapKey = $this->storeNpmBootstrapCredentials($provisioned['identity'], $provisioned['secret']);

            echo json_encode([
                'ok' => true,
                'bootstrap_key' => $bootstrapKey,
                'runtime_identity' => $provisioned['identity'],
            ]);
        } catch (RuntimeException $e) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    public function integrationsEnableCloudflareAction(): void
    {
        header('Content-Type: application/json');

        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Invalid CSRF token.']);
            return;
        }

        try {
            $name = trim((string) ($_POST['name'] ?? ''));
            if ($name === '') {
                $name = 'Cloudflare';
            }

            $integrations = $this->integrationsFromStore();
            $existingIndex = null;
            foreach ($integrations as $idx => $row) {
                if (($row['provider'] ?? '') === 'cloudflare') {
                    $existingIndex = $idx;
                    break;
                }
            }

            $entry = [
                'id' => $existingIndex !== null ? (string) ($integrations[$existingIndex]['id'] ?? 'cloudflare_enabled') : 'cloudflare_enabled',
                'name' => $name,
                'provider' => 'cloudflare',
                'category' => 'dns',
                'settings' => [],
            ];

            if ($existingIndex !== null) {
                $integrations[$existingIndex] = $entry;
            } else {
                $integrations[] = $entry;
            }

            $this->persistIntegrations($integrations);
            $this->settingsStore->setMany(['CF_ENABLED' => 'true']);

            echo json_encode(['ok' => true]);
        } catch (RuntimeException $e) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    public function showApacheModules(): void
    {
        try {
            $modules = $this->apacheModules->listModules();
        } catch (RuntimeException $e) {
            Session::setFlash('error', $e->getMessage());
            $modules = [];
        }

        $requiredCount = count(array_filter($modules, static fn (array $module): bool => !empty($module['required'])));

        $this->render('settings/apache-modules.php', [
            'csrfToken' => $this->csrf->token(),
            'modules' => $modules,
            'requiredCount' => $requiredCount,
        ]);
    }

    public function apacheModulesAction(): void
    {
        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            Session::setFlash('error', 'Invalid CSRF token.');
            $this->redirect('settings-apache-modules');
        }

        $module = trim((string) ($_POST['module'] ?? ''));
        $enabled = isset($_POST['enabled']) && (string) $_POST['enabled'] === '1';

        try {
            $this->apacheModules->setEnabled($module, $enabled);
        } catch (RuntimeException $e) {
            Session::setFlash('error', $e->getMessage());
            $this->redirect('settings-apache-modules');
        }

        Session::setFlash('success', sprintf('Apache module %s %s.', $module, $enabled ? 'enabled' : 'disabled'));
        $this->redirect('settings-apache-modules');
    }

    public function saveGeneral(): void
    {
        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            Session::setFlash('error', 'Invalid CSRF token.');
            $this->redirect('settings');
        }

        $appUrl = trim((string) ($_POST['app_url'] ?? ''));
        $appHttps = $this->postBool('app_https');
        $allowedDocrootBasesRaw = $_POST['allowed_docroot_bases'] ?? [];
        $defaultDocrootBase = trim((string) ($_POST['default_docroot_base'] ?? ''));
        $baseDomain = strtolower(trim((string) ($_POST['vhost_base_domain'] ?? '')));

        if ($appUrl === '' || filter_var($appUrl, FILTER_VALIDATE_URL) === false) {
            Session::setFlash('error', 'App URL must be a valid URL.');
            $this->redirect('settings');
        }

        if ($baseDomain !== '' && !DomainValidator::isValid($baseDomain)) {
            Session::setFlash('error', 'Base domain must be a valid domain name.');
            $this->redirect('settings');
        }

        $domainOptions = $this->domainOptionsFromStore();
        if ($baseDomain !== '' && $domainOptions !== [] && !in_array($baseDomain, $domainOptions, true)) {
            Session::setFlash('error', 'Default base domain must be selected from the available domain list.');
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
        $adminUser = strtolower(trim((string) $this->config->get('ADMIN_USER', 'admin@example.com')));
        $this->render('settings/users.php', [
            'csrfToken' => $this->csrf->token(),
            'adminUser' => $adminUser,
            'currentUser' => strtolower(trim((string) ($_SESSION['username'] ?? ''))),
            'userRecords' => $this->userRecordsFromStore(),
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
                case 'user-update':
                    $this->handleUserUpdateProfile();
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
                case 'user-toggle-status':
                    $this->handleUserToggleStatus();
                    break;
                case 'user-toggle-role':
                    $this->handleUserToggleRole();
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
        $this->redirect('settings-integrations');
    }

    public function saveCloudflare(): void
    {
        $this->redirect('settings-integrations');
    }

    public function showCloudflareDomains(): void
    {
        $this->redirect('domains');
    }

    public function cloudflareDomainsAction(): void
    {
        $this->redirect('domains');
    }

    public function showNpm(): void
    {
        $this->redirect('settings-integrations');
    }

    public function saveNpm(): void
    {
        $this->redirect('settings-integrations');
    }

    public function showNpmSsl(): void
    {
        $this->redirect('settings-integrations');
    }

    public function saveNpmSsl(): void
    {
        $this->redirect('settings-integrations');
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
            $name = strtolower(trim((string) $username));
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
     * @return list<array{id:string,name:string,provider:string,category:string,settings:array<string,string>}>
     */
    private function integrationsFromStore(): array
    {
        $raw = (string) $this->config->get('INTEGRATIONS_JSON', '');
        if ($raw === '') {
            $migrated = $this->migrateOldIntegrations();
            if ($migrated !== []) {
                $this->persistIntegrations($migrated);
            }

            return $migrated;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $providers = IntegrationRegistry::providers();
        $integrations = [];

        foreach ($decoded as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $id = trim((string) ($entry['id'] ?? ''));
            $name = trim((string) ($entry['name'] ?? ''));
            $providerKey = trim((string) ($entry['provider'] ?? ''));
            if ($id === '' || $name === '' || !isset($providers[$providerKey])) {
                continue;
            }

            $integrations[] = [
                'id' => $id,
                'name' => $name,
                'provider' => $providerKey,
                'category' => (string) $providers[$providerKey]['category'],
                'settings' => $this->normalizeIntegrationSettings(
                    $providerKey,
                    is_array($entry['settings'] ?? null) ? $entry['settings'] : [],
                    []
                ),
            ];
        }

        return $integrations;
    }

    /**
     * @return list<array{id:string,name:string,provider:string,category:string,settings:array<string,string>}>
     */
    private function migrateOldIntegrations(): array
    {
        $providers = IntegrationRegistry::providers();
        $integrations = [];

        $npmBaseUrl = trim((string) $this->config->get('NPM_BASE_URL', ''));
        $npmIdentity = trim((string) $this->config->get('NPM_IDENTITY', ''));
        $npmSecret = trim((string) $this->config->get('NPM_SECRET', ''));
        if ($npmBaseUrl !== '' && $npmIdentity !== '' && $npmSecret !== '') {
            $integrations[] = [
                'id' => 'npm_legacy',
                'name' => 'Nginx Proxy Manager',
                'provider' => 'npm',
                'category' => (string) $providers['npm']['category'],
                'settings' => [
                    'base_url' => $npmBaseUrl,
                    'identity' => $npmIdentity,
                    'secret' => $npmSecret,
                    'forward_host' => trim((string) $this->config->get('NPM_FORWARD_HOST', '127.0.0.1')),
                    'forward_port' => (string) $this->config->get('NPM_FORWARD_PORT', '80'),
                ],
            ];
        }

        $cfApiToken = trim((string) $this->config->get('CF_API_TOKEN', ''));
        $cfZoneId = trim((string) $this->config->get('CF_ZONE_ID', ''));
        $cfRecordIp = trim((string) $this->config->get('CF_RECORD_IP', ''));
        if ($cfApiToken !== '' && $cfZoneId !== '' && $cfRecordIp !== '') {
            $integrations[] = [
                'id' => 'cloudflare_legacy',
                'name' => 'Cloudflare',
                'provider' => 'cloudflare',
                'category' => (string) $providers['cloudflare']['category'],
                'settings' => [
                    'api_token' => $cfApiToken,
                    'zone_id' => $cfZoneId,
                    'record_ip' => $cfRecordIp,
                    'ttl' => (string) $this->config->get('CF_TTL', '120'),
                    'proxied' => $this->config->getBool('CF_PROXIED', false) ? '1' : '0',
                ],
            ];
        }

        return $integrations;
    }

    /**
     * @param list<array{id:string,name:string,provider:string,category:string,settings:array<string,string>}> $integrations
     */
    private function persistIntegrations(array $integrations): void
    {
        $this->settingsStore->setMany([
            'INTEGRATIONS_JSON' => json_encode($integrations, JSON_UNESCAPED_SLASHES) ?: '[]',
        ]);
    }

    /**
     * @param list<array{id:string,name:string,provider:string,category:string,settings:array<string,string>}> $integrations
     */
    private function syncLegacyIntegrationSettings(array $integrations): void
    {
        $firstNpm = null;
        $hasCloudflare = false;

        foreach ($integrations as $integration) {
            if (($integration['provider'] ?? '') === 'npm' && $firstNpm === null) {
                $firstNpm = is_array($integration['settings'] ?? null) ? $integration['settings'] : [];
            }
            if (($integration['provider'] ?? '') === 'cloudflare') {
                $hasCloudflare = true;
            }
        }

        $settings = [
            'CF_ENABLED' => $hasCloudflare ? 'true' : 'false',
            'NPM_ENABLED' => $firstNpm !== null ? 'true' : 'false',
        ];

        if ($firstNpm !== null) {
            $settings['NPM_BASE_URL'] = (string) ($firstNpm['base_url'] ?? '');
            $settings['NPM_IDENTITY'] = (string) ($firstNpm['identity'] ?? '');
            $settings['NPM_SECRET'] = (string) ($firstNpm['secret'] ?? '');
            $settings['NPM_FORWARD_HOST'] = (string) ($firstNpm['forward_host'] ?? '127.0.0.1');
            $settings['NPM_FORWARD_PORT'] = (string) ($firstNpm['forward_port'] ?? '80');
        }

        $this->settingsStore->setMany($settings);
    }

    private function storeNpmBootstrapCredentials(string $identity, string $secret): string
    {
        $key = bin2hex(random_bytes(16));
        $store = $_SESSION['npm_integration_bootstrap'] ?? [];
        if (!is_array($store)) {
            $store = [];
        }

        $store[$key] = [
            'identity' => $identity,
            'secret' => $secret,
            'created_at' => time(),
        ];

        $_SESSION['npm_integration_bootstrap'] = $store;

        return $key;
    }

    /**
     * @return array{identity:string,secret:string}|null
     */
    private function consumeNpmBootstrapCredentials(string $key): ?array
    {
        $store = $_SESSION['npm_integration_bootstrap'] ?? [];
        if (!is_array($store) || !isset($store[$key]) || !is_array($store[$key])) {
            return null;
        }

        $entry = $store[$key];
        unset($store[$key]);
        $_SESSION['npm_integration_bootstrap'] = $store;

        $identity = trim((string) ($entry['identity'] ?? ''));
        $secret = trim((string) ($entry['secret'] ?? ''));
        if ($identity === '' || $secret === '') {
            return null;
        }

        return [
            'identity' => $identity,
            'secret' => $secret,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed>|null $existingSettings
     * @return array<string, string>
     */
    private function normalizeIntegrationSettings(string $providerKey, array $input, ?array $existingSettings = null): array
    {
        $providers = IntegrationRegistry::providers();
        if (!isset($providers[$providerKey])) {
            throw new RuntimeException('Invalid integration provider.');
        }

        $settings = [];
        foreach ($providers[$providerKey]['fields'] as $field) {
            $fieldName = (string) $field['name'];

            if ((string) $field['type'] === 'checkbox') {
                $value = isset($input[$fieldName]) && (string) $input[$fieldName] === '1' ? '1' : '0';
            } else {
                $value = trim((string) ($input[$fieldName] ?? ''));
                if ((string) $field['type'] === 'password' && $value === '' && $existingSettings !== null) {
                    $value = trim((string) ($existingSettings[$fieldName] ?? ''));
                }
                if ($value === '' && isset($field['default'])) {
                    $value = (string) $field['default'];
                }
            }

            if (!empty($field['required']) && $value === '') {
                throw new RuntimeException(sprintf('%s is required.', (string) $field['label']));
            }

            $settings[$fieldName] = $value;
        }

        if ($providerKey === 'npm') {
            if (filter_var($settings['base_url'] ?? '', FILTER_VALIDATE_URL) === false) {
                throw new RuntimeException('NPM Base URL must be a valid URL.');
            }

            $bootstrapKey = trim((string) ($settings['bootstrap_key'] ?? ''));
            if ($bootstrapKey !== '') {
                $bootstrapCreds = $this->consumeNpmBootstrapCredentials($bootstrapKey);
                if ($bootstrapCreds === null) {
                    throw new RuntimeException('NPM bootstrap session expired. Use Next again to provision the runtime account.');
                }

                $settings['identity'] = $bootstrapCreds['identity'];
                $settings['secret'] = $bootstrapCreds['secret'];
            }

            if ((string) ($settings['identity'] ?? '') === '' || (string) ($settings['secret'] ?? '') === '') {
                if ($existingSettings !== null) {
                    $settings['identity'] = (string) ($existingSettings['identity'] ?? '');
                    $settings['secret'] = (string) ($existingSettings['secret'] ?? '');
                }
            }

            if (filter_var($settings['identity'] ?? '', FILTER_VALIDATE_EMAIL) === false) {
                throw new RuntimeException('NPM runtime identity must be a valid email address.');
            }

            if ((string) ($settings['secret'] ?? '') === '') {
                throw new RuntimeException('NPM runtime secret is missing. Use Next to create a VHM account first.');
            }

            if (($settings['forward_host'] ?? '') === '' || preg_match('/^[a-zA-Z0-9.-]+$/', $settings['forward_host']) !== 1) {
                throw new RuntimeException('NPM forward host contains invalid characters.');
            }

            $forwardPort = (int) ($settings['forward_port'] ?? 0);
            if ($forwardPort < 1 || $forwardPort > 65535) {
                throw new RuntimeException('NPM forward port must be between 1 and 65535.');
            }
            $settings['forward_port'] = (string) $forwardPort;
            $settings['bootstrap_key'] = '';
        }

        if ($providerKey === 'cloudflare') {
            return [];
        }

        return $settings;
    }

    /**
     * @param array{id:string,name:string,provider:string,category:string,settings:array<string,string>} $integration
     */
    private function testIntegration(array $integration): void
    {
        $settings = is_array($integration['settings'] ?? null) ? $integration['settings'] : [];
        $verifySsl = $this->config->getBool('CURL_VERIFY_SSL', true);

        if (($integration['provider'] ?? '') === 'npm') {
            $service = new NpmService(
                new Config([
                    'NPM_BASE_URL' => (string) ($settings['base_url'] ?? ''),
                    'NPM_IDENTITY' => (string) ($settings['identity'] ?? ''),
                    'NPM_SECRET' => (string) ($settings['secret'] ?? ''),
                    'NPM_FORWARD_HOST' => (string) ($settings['forward_host'] ?? '127.0.0.1'),
                    'NPM_FORWARD_PORT' => (string) ($settings['forward_port'] ?? '80'),
                    'NPM_SSL_ENABLED' => 'false',
                    'NPM_CERTIFICATE_ID' => '0',
                    'NPM_SSL_FORCED' => 'false',
                    'NPM_HTTP2_SUPPORT' => 'false',
                    'NPM_HSTS_ENABLED' => 'false',
                    'NPM_HSTS_SUBDOMAINS' => 'false',
                ]),
                new HttpClient($verifySsl),
                new Logger((string) $this->config->get('LOG_FILE', __DIR__ . '/../../storage/logs/app.log'))
            );
            $service->listCertificates();

            return;
        }

        if (($integration['provider'] ?? '') === 'cloudflare') {
            if (!$this->config->getBool('CF_ENABLED', false)) {
                throw new RuntimeException('Cloudflare integration is currently disabled. Enable it from the Integrations modal.');
            }

            return;
        }

        throw new RuntimeException('Unsupported integration provider.');
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
        $newAdmin = strtolower(trim((string) ($_POST['admin_user'] ?? '')));
        $newAdminFullName = trim((string) ($_POST['admin_full_name'] ?? ''));
        if ($newAdmin === '' || filter_var($newAdmin, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Admin email must be a valid email address.');
        }

        if ($newAdminFullName === '' || strlen($newAdminFullName) < 2 || strlen($newAdminFullName) > 120) {
            throw new RuntimeException('Admin full name must be between 2 and 120 characters.');
        }

        $users = $this->usersFromStore();
        if (array_key_exists($newAdmin, $users)) {
            throw new RuntimeException('That email already exists as an additional user.');
        }

        $this->settingsStore->setMany([
            'ADMIN_USER' => $newAdmin,
            'ADMIN_FULL_NAME' => $newAdminFullName,
        ]);
        $_SESSION['username'] = $newAdmin;
        $_SESSION['display_name'] = $newAdminFullName;
        $_SESSION['account_role'] = 'Primary Admin';
    }

    private function handleUserUpdateProfile(): void
    {
        $targetUser = strtolower(trim((string) ($_POST['target_user'] ?? '')));
        $newEmail = strtolower(trim((string) ($_POST['new_user_email'] ?? '')));
        $newFullName = trim((string) ($_POST['new_user_full_name'] ?? ''));
        $adminUser = strtolower(trim((string) $this->config->get('ADMIN_USER', 'admin@example.com')));

        if ($targetUser === '') {
            throw new RuntimeException('Target user is required.');
        }

        if ($targetUser === $adminUser) {
            throw new RuntimeException('Primary admin profile updates use the admin profile form.');
        }

        if ($newEmail === '' || filter_var($newEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('User email must be a valid email address.');
        }

        if ($newEmail === $adminUser) {
            throw new RuntimeException('That email is reserved by the primary admin account.');
        }

        if ($newFullName === '' || strlen($newFullName) < 2 || strlen($newFullName) > 120) {
            throw new RuntimeException('Full name must be between 2 and 120 characters.');
        }

        $users = $this->usersFromStore();
        if (!array_key_exists($targetUser, $users)) {
            throw new RuntimeException('User not found.');
        }

        if ($newEmail !== $targetUser && array_key_exists($newEmail, $users)) {
            throw new RuntimeException('Email already exists.');
        }

        $usersMeta = $this->usersMetaFromStore();
        $existingMeta = $usersMeta[$targetUser] ?? [
            'full_name' => '',
            'account_type' => 'user',
            'active' => true,
            'created_at' => date('c'),
            'last_login_at' => '',
        ];

        if ($newEmail !== $targetUser) {
            $users[$newEmail] = $users[$targetUser];
            unset($users[$targetUser]);

            unset($usersMeta[$targetUser]);
            $usersMeta[$newEmail] = $existingMeta;
        }

        $usersMeta[$newEmail]['full_name'] = $newFullName;
        $usersMeta[$newEmail]['account_type'] = (string) ($usersMeta[$newEmail]['account_type'] ?? 'user') === 'admin' ? 'admin' : 'user';
        $usersMeta[$newEmail]['active'] = !array_key_exists('active', $usersMeta[$newEmail]) || (bool) $usersMeta[$newEmail]['active'];
        $usersMeta[$newEmail]['created_at'] = (string) ($usersMeta[$newEmail]['created_at'] ?? date('c'));
        $usersMeta[$newEmail]['last_login_at'] = (string) ($usersMeta[$newEmail]['last_login_at'] ?? '');

        ksort($users, SORT_NATURAL | SORT_FLAG_CASE);

        $this->settingsStore->setMany([
            'USERS_JSON' => json_encode($users, JSON_UNESCAPED_SLASHES) ?: '{}',
            'USERS_META_JSON' => json_encode($usersMeta, JSON_UNESCAPED_SLASHES) ?: '{}',
        ]);

        $sessionUser = strtolower(trim((string) ($_SESSION['username'] ?? '')));
        if ($sessionUser !== '' && $sessionUser === $targetUser) {
            $_SESSION['username'] = $newEmail;
            $_SESSION['display_name'] = $newFullName;
        }
    }

    private function handleUserAdd(): void
    {
        $username = strtolower(trim((string) ($_POST['new_user'] ?? '')));
        $fullName = trim((string) ($_POST['new_user_full_name'] ?? ''));
        $password = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['new_password_confirm'] ?? '');
        $role = strtolower(trim((string) ($_POST['new_user_role'] ?? 'user')));
        $adminUser = strtolower(trim((string) $this->config->get('ADMIN_USER', 'admin@example.com')));

        if ($username === '' || filter_var($username, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('User email must be a valid email address.');
        }

        if ($username === $adminUser) {
            throw new RuntimeException('That email is reserved by the primary admin account.');
        }

        if ($fullName === '' || strlen($fullName) < 2 || strlen($fullName) > 120) {
            throw new RuntimeException('Full name must be between 2 and 120 characters.');
        }

        if (!in_array($role, ['admin', 'user'], true)) {
            throw new RuntimeException('Account type must be admin or user.');
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
            throw new RuntimeException('Email already exists.');
        }

        $users[$username] = $hash;
        ksort($users, SORT_NATURAL | SORT_FLAG_CASE);

        $usersMeta = $this->usersMetaFromStore();
        $usersMeta[$username] = [
            'full_name' => $fullName,
            'account_type' => $role,
            'active' => true,
            'created_at' => date('c'),
            'last_login_at' => '',
        ];

        $this->settingsStore->setMany([
            'USERS_JSON' => json_encode($users, JSON_UNESCAPED_SLASHES) ?: '{}',
            'USERS_META_JSON' => json_encode($usersMeta, JSON_UNESCAPED_SLASHES) ?: '{}',
        ]);
    }

    private function handleUserPasswordReset(): void
    {
        $targetUser = strtolower(trim((string) ($_POST['target_user'] ?? '')));
        $password = (string) ($_POST['reset_password'] ?? '');
        $confirm = (string) ($_POST['reset_password_confirm'] ?? '');
        $adminUser = strtolower(trim((string) $this->config->get('ADMIN_USER', 'admin@example.com')));

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
        $targetUser = strtolower(trim((string) ($_POST['target_user'] ?? '')));
        $adminUser = strtolower(trim((string) $this->config->get('ADMIN_USER', 'admin@example.com')));

        if ($targetUser === '' || $targetUser === $adminUser) {
            throw new RuntimeException('Only additional users can be deleted.');
        }

        $records = $this->userRecordsFromStore();
        if (!isset($records[$targetUser])) {
            throw new RuntimeException('User not found.');
        }

        if (($records[$targetUser]['account_type'] ?? 'user') === 'admin' && $this->activeAdminCount($records) <= 1) {
            throw new RuntimeException('At least one active admin account is required.');
        }

        $users = $this->usersFromStore();
        if (!array_key_exists($targetUser, $users)) {
            throw new RuntimeException('User not found.');
        }

        unset($users[$targetUser]);

        $usersMeta = $this->usersMetaFromStore();
        unset($usersMeta[$targetUser]);

        $this->settingsStore->setMany([
            'USERS_JSON' => json_encode($users, JSON_UNESCAPED_SLASHES) ?: '{}',
            'USERS_META_JSON' => json_encode($usersMeta, JSON_UNESCAPED_SLASHES) ?: '{}',
        ]);
    }

    private function handleUserToggleStatus(): void
    {
        $targetUser = strtolower(trim((string) ($_POST['target_user'] ?? '')));
        if ($targetUser === '') {
            throw new RuntimeException('Target user is required.');
        }

        $adminUser = strtolower(trim((string) $this->config->get('ADMIN_USER', 'admin@example.com')));
        if ($targetUser === $adminUser) {
            throw new RuntimeException('Primary admin account cannot be disabled.');
        }

        $records = $this->userRecordsFromStore();
        if (!isset($records[$targetUser])) {
            throw new RuntimeException('User not found.');
        }

        $records[$targetUser]['active'] = !($records[$targetUser]['active'] ?? true);
        if (($records[$targetUser]['account_type'] ?? 'user') === 'admin' && !($records[$targetUser]['active'] ?? false) && $this->activeAdminCount($records) < 1) {
            throw new RuntimeException('At least one active admin account is required.');
        }

        $usersMeta = $this->usersMetaFromStore();
        $usersMeta[$targetUser] = [
            'full_name' => (string) ($records[$targetUser]['full_name'] ?? ''),
            'account_type' => (string) ($records[$targetUser]['account_type'] ?? 'user'),
            'active' => (bool) ($records[$targetUser]['active'] ?? true),
            'created_at' => (string) ($records[$targetUser]['created_at'] ?? date('c')),
            'last_login_at' => (string) ($records[$targetUser]['last_login_at'] ?? ''),
        ];

        $this->settingsStore->setMany([
            'USERS_META_JSON' => json_encode($usersMeta, JSON_UNESCAPED_SLASHES) ?: '{}',
        ]);
    }

    private function handleUserToggleRole(): void
    {
        $targetUser = strtolower(trim((string) ($_POST['target_user'] ?? '')));
        if ($targetUser === '') {
            throw new RuntimeException('Target user is required.');
        }

        $adminUser = strtolower(trim((string) $this->config->get('ADMIN_USER', 'admin@example.com')));
        if ($targetUser === $adminUser) {
            throw new RuntimeException('Primary admin account role cannot be changed.');
        }

        $records = $this->userRecordsFromStore();
        if (!isset($records[$targetUser])) {
            throw new RuntimeException('User not found.');
        }

        $currentRole = (string) ($records[$targetUser]['account_type'] ?? 'user');
        $nextRole = $currentRole === 'admin' ? 'user' : 'admin';
        $records[$targetUser]['account_type'] = $nextRole;

        if ($currentRole === 'admin' && ($records[$targetUser]['active'] ?? true) && $this->activeAdminCount($records) < 1) {
            throw new RuntimeException('At least one active admin account is required.');
        }

        $usersMeta = $this->usersMetaFromStore();
        $usersMeta[$targetUser] = [
            'full_name' => (string) ($records[$targetUser]['full_name'] ?? ''),
            'account_type' => $nextRole,
            'active' => (bool) ($records[$targetUser]['active'] ?? true),
            'created_at' => (string) ($records[$targetUser]['created_at'] ?? date('c')),
            'last_login_at' => (string) ($records[$targetUser]['last_login_at'] ?? ''),
        ];

        $this->settingsStore->setMany([
            'USERS_META_JSON' => json_encode($usersMeta, JSON_UNESCAPED_SLASHES) ?: '{}',
        ]);
    }

    /**
     * @return array<string, array{full_name:string,account_type:string,active:bool,created_at:string,last_login_at:string}>
     */
    private function usersMetaFromStore(): array
    {
        $raw = (string) $this->config->get('USERS_META_JSON', '');
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $rows = [];
        foreach ($decoded as $email => $meta) {
            if (!is_array($meta)) {
                continue;
            }

            $normalized = strtolower(trim((string) $email));
            if ($normalized === '') {
                continue;
            }

            $rows[$normalized] = [
                'full_name' => trim((string) ($meta['full_name'] ?? '')),
                'account_type' => (string) ($meta['account_type'] ?? 'user') === 'admin' ? 'admin' : 'user',
                'active' => !array_key_exists('active', $meta) || (bool) $meta['active'],
                'created_at' => trim((string) ($meta['created_at'] ?? '')),
                'last_login_at' => trim((string) ($meta['last_login_at'] ?? '')),
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, array{email:string,full_name:string,account_type:string,active:bool,created_at:string,last_login_at:string,is_primary:bool,is_online:bool}>
     */
    private function userRecordsFromStore(): array
    {
        $adminEmail = strtolower(trim((string) $this->config->get('ADMIN_USER', 'admin@example.com')));
        $adminFullName = trim((string) $this->config->get('ADMIN_FULL_NAME', ''));
        $adminCreatedAt = trim((string) $this->config->get('ADMIN_CREATED_AT', ''));
        $adminLastLoginAt = trim((string) $this->config->get('ADMIN_LAST_LOGIN_AT', ''));

        $records = [
            $adminEmail => [
                'email' => $adminEmail,
                'full_name' => $adminFullName !== '' ? $adminFullName : $adminEmail,
                'account_type' => 'admin',
                'active' => true,
                'created_at' => $adminCreatedAt,
                'last_login_at' => $adminLastLoginAt,
                'is_primary' => true,
                'is_online' => false,
            ],
        ];

        $users = $this->usersFromStore();
        $usersMeta = $this->usersMetaFromStore();

        foreach ($users as $email => $hash) {
            unset($hash);
            $meta = $usersMeta[$email] ?? [
                'full_name' => '',
                'account_type' => 'user',
                'active' => true,
                'created_at' => '',
                'last_login_at' => '',
            ];

            $records[$email] = [
                'email' => $email,
                'full_name' => trim((string) ($meta['full_name'] ?? '')) !== '' ? (string) $meta['full_name'] : $email,
                'account_type' => (string) ($meta['account_type'] ?? 'user') === 'admin' ? 'admin' : 'user',
                'active' => !array_key_exists('active', $meta) || (bool) $meta['active'],
                'created_at' => (string) ($meta['created_at'] ?? ''),
                'last_login_at' => (string) ($meta['last_login_at'] ?? ''),
                'is_primary' => false,
                'is_online' => false,
            ];
        }

        $currentUser = strtolower(trim((string) ($_SESSION['username'] ?? '')));
        if ($currentUser !== '' && isset($records[$currentUser])) {
            $records[$currentUser]['is_online'] = true;
        }

        uasort($records, static function (array $a, array $b): int {
            if (($a['is_primary'] ?? false) !== ($b['is_primary'] ?? false)) {
                return ($a['is_primary'] ?? false) ? -1 : 1;
            }

            return strcasecmp((string) ($a['email'] ?? ''), (string) ($b['email'] ?? ''));
        });

        return $records;
    }

    /**
     * @param array<string, array{email:string,full_name:string,account_type:string,active:bool,created_at:string,last_login_at:string,is_primary:bool}> $records
     */
    private function activeAdminCount(array $records): int
    {
        return count(array_filter(
            $records,
            static fn (array $record): bool => ($record['account_type'] ?? 'user') === 'admin' && !empty($record['active'])
        ));
    }

    /**
     * @return list<string>
     */
    private function domainOptionsFromStore(): array
    {
        $raw = (string) $this->config->get('DOMAINS_JSON', '');
        $decoded = json_decode($raw, true);
        $domains = [];

        if (is_array($decoded)) {
            foreach ($decoded as $entry) {
                if (is_string($entry)) {
                    $domain = strtolower(trim($entry));
                } elseif (is_array($entry)) {
                    $domain = strtolower(trim((string) ($entry['domain'] ?? '')));
                } else {
                    continue;
                }

                if ($domain !== '' && DomainValidator::isValid($domain) && !in_array($domain, $domains, true)) {
                    $domains[] = $domain;
                }
            }
        }

        foreach ($this->cloudflareDomainsFromStore() as $mapping) {
            $domain = strtolower(trim((string) ($mapping['domain'] ?? '')));
            if ($domain !== '' && DomainValidator::isValid($domain) && !in_array($domain, $domains, true)) {
                $domains[] = $domain;
            }
        }

        $currentBaseDomain = strtolower(trim((string) $this->config->get('VHOST_BASE_DOMAIN', '')));
        if ($currentBaseDomain !== '' && DomainValidator::isValid($currentBaseDomain) && !in_array($currentBaseDomain, $domains, true)) {
            $domains[] = $currentBaseDomain;
        }

        sort($domains, SORT_NATURAL | SORT_FLAG_CASE);

        return $domains;
    }

    private function postBool(string $key): bool
    {
        return isset($_POST[$key]) && (string) $_POST[$key] === '1';
    }

    /**
     * @return array{identity:string,secret:string}
     */
    private function provisionNpmServiceAccount(string $baseUrl, string $adminIdentity, string $adminSecret): array
    {
        $tokenUrl = rtrim($baseUrl, '/') . '/api/tokens';
        $adminToken = $this->resolveNpmAdminToken($baseUrl, $adminIdentity, $adminSecret);

        $serviceIdentityBase = $this->buildNpmServiceIdentity($adminIdentity);
        $serviceIdentity = $serviceIdentityBase;
        $headers = ["Authorization: Bearer {$adminToken}"];

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $serviceSecret = bin2hex(random_bytes(32));
            $createUserResult = $this->createNpmUserWithFallback(
                rtrim($baseUrl, '/') . '/api/users',
                $headers,
                $serviceIdentity,
                $serviceSecret,
                false
            );

            if (!in_array((int) ($createUserResult['status'] ?? 0), [200, 201], true)) {
                if ($this->isNpmDuplicateUserError($createUserResult)) {
                    $serviceIdentity = $this->withNpmIdentitySuffix($serviceIdentityBase);
                    continue;
                }

                $body = json_encode($createUserResult['body'] ?? [], JSON_UNESCAPED_SLASHES);
                throw new RuntimeException('NPM service account could not be created: ' . $body);
            }

            $serviceTokenResult = $this->httpClient->post($tokenUrl, [
                'identity' => $serviceIdentity,
                'secret' => $serviceSecret,
            ]);

            if ((int) ($serviceTokenResult['status'] ?? 0) !== 200 || trim((string) ($serviceTokenResult['body']['token'] ?? '')) === '') {
                throw new RuntimeException('NPM service account was created but token verification failed.');
            }

            return [
                'identity' => $serviceIdentity,
                'secret' => $serviceSecret,
            ];
        }

        throw new RuntimeException('NPM service account creation failed because generated runtime identities already exist.');
    }

    private function resolveNpmAdminToken(string $baseUrl, string $adminIdentity, string $adminSecret): string
    {
        $tokenUrl = rtrim($baseUrl, '/') . '/api/tokens';

        try {
            $authResult = $this->httpClient->post($tokenUrl, [
                'identity' => $adminIdentity,
                'secret' => $adminSecret,
            ]);
        } catch (RuntimeException $e) {
            throw new RuntimeException('NPM admin authentication request failed: ' . $e->getMessage());
        }

        if ((int) ($authResult['status'] ?? 0) === 200 && is_array($authResult['body'] ?? null)) {
            $adminToken = trim((string) ($authResult['body']['token'] ?? ''));
            if ($adminToken !== '') {
                return $adminToken;
            }
        }

        // Allow pasting an existing bearer token into the secret field.
        if (substr_count($adminSecret, '.') === 2) {
            try {
                $meResult = $this->httpClient->get(rtrim($baseUrl, '/') . '/api/users/me', [
                    'Authorization: Bearer ' . $adminSecret,
                ]);

                if ((int) ($meResult['status'] ?? 0) === 200) {
                    return $adminSecret;
                }
            } catch (RuntimeException) {
                // Fall through to detailed auth error.
            }
        }

        $status = (int) ($authResult['status'] ?? 0);
        $detail = $this->extractNpmErrorMessage($authResult['body'] ?? null);
        if ($detail !== '') {
            throw new RuntimeException('NPM admin authentication failed while provisioning service account: ' . $detail);
        }

        throw new RuntimeException('NPM admin authentication failed while provisioning service account (HTTP ' . $status . ').');
    }

    private function extractNpmErrorMessage(mixed $body): string
    {
        if (is_array($body)) {
            $nested = trim((string) (($body['error']['message'] ?? '') ?: ''));
            if ($nested !== '') {
                return $nested;
            }

            $top = trim((string) ($body['message'] ?? ''));
            if ($top !== '') {
                return $top;
            }

            $encoded = json_encode($body, JSON_UNESCAPED_SLASHES);
            return is_string($encoded) ? $encoded : '';
        }

        if (is_string($body)) {
            return trim($body);
        }

        return '';
    }

    /**
     * @param list<string> $headers
     * @return array{status:int,body:mixed}
     */
    private function createNpmUserWithFallback(
        string $usersUrl,
        array $headers,
        string $identity,
        string $secret,
        bool $isAdmin
    ): array {
        $payloads = [
            [
                'name' => 'Vhost Manager',
                'nickname' => 'VHM',
                'email' => $identity,
                'roles' => [],
                'is_disabled' => false,
                'auth' => [
                    'type' => 'password',
                    'secret' => $secret,
                ],
            ],
            [
                'name' => 'Vhost Manager',
                'nickname' => 'VHM',
                'email' => $identity,
                'auth' => [
                    'type' => 'password',
                    'secret' => $secret,
                ],
            ],
            [
                'email' => $identity,
                'password' => $secret,
                'is_admin' => $isAdmin,
            ],
            [
                'name' => 'Vhost Manager',
                'nickname' => 'VHM',
                'email' => $identity,
                'is_admin' => $isAdmin,
                'auth' => [
                    'type' => 'password',
                    'secret' => $secret,
                ],
            ],
        ];

        $lastResult = ['status' => 0, 'body' => ['message' => 'No request attempted']];

        foreach ($payloads as $payload) {
            $result = $this->httpClient->post($usersUrl, $payload, $headers);
            $lastResult = [
                'status' => (int) ($result['status'] ?? 0),
                'body' => $result['body'] ?? [],
            ];

            if (in_array((int) ($lastResult['status'] ?? 0), [200, 201], true)) {
                return $lastResult;
            }

            $status = (int) ($lastResult['status'] ?? 0);
            if ($status >= 400 && $status < 500 && !$this->isNpmSchemaValidationError($lastResult)) {
                return $lastResult;
            }
            if ($status >= 500) {
                return $lastResult;
            }
        }

        return $lastResult;
    }

    /**
     * @param array{status:int,body:mixed} $result
     */
    private function isNpmDuplicateUserError(array $result): bool
    {
        $body = json_encode($result['body'] ?? [], JSON_UNESCAPED_SLASHES);
        if (!is_string($body) || $body === '') {
            return false;
        }

        $text = strtolower($body);

        return (str_contains($text, 'already') || str_contains($text, 'exists'))
            && (str_contains($text, 'email') || str_contains($text, 'user'));
    }

    /**
     * @param array{status:int,body:mixed} $result
     */
    private function isNpmSchemaValidationError(array $result): bool
    {
        $body = json_encode($result['body'] ?? [], JSON_UNESCAPED_SLASHES);
        if (!is_string($body) || $body === '') {
            return false;
        }

        $text = strtolower($body);

        return str_contains($text, 'additional properties')
            || str_contains($text, 'required property')
            || str_contains($text, 'must have required property')
            || str_contains($text, 'must not have additional properties');
    }

    private function withNpmIdentitySuffix(string $identity): string
    {
        $atPos = strpos($identity, '@');
        if ($atPos === false) {
            return $identity . '-' . substr(bin2hex(random_bytes(2)), 0, 4);
        }

        $local = substr($identity, 0, $atPos);
        $domain = substr($identity, $atPos + 1);

        return $local . '-' . substr(bin2hex(random_bytes(2)), 0, 4) . '@' . $domain;
    }

    private function buildNpmServiceIdentity(string $email): string
    {
        $localPart = strtolower(trim((string) strtok($email, '@')));
        $localPart = preg_replace('/[^a-z0-9._-]/', '', $localPart);
        $localPart = is_string($localPart) ? trim($localPart, '._-') : '';

        if ($localPart === '') {
            $localPart = 'vhm';
        }

        return $localPart . '@vhost-manager.npm';
    }
}
