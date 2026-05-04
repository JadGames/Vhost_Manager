<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Session;
use App\Security\Csrf;
use App\Services\ApacheModulesService;
use App\Services\HttpClient;
use App\Services\IntegrationRegistry;
use App\Security\DomainValidator;
use App\Services\SettingsStore;
use RuntimeException;

final class SettingsController extends BaseController
{
    public function __construct(
        \App\Core\Config $config,
        private readonly Csrf $csrf,
        SettingsStore $settingsStore,
        private readonly ApacheModulesService $apacheModules,
        private readonly HttpClient $httpClient
    ) {
        parent::__construct($config, $settingsStore);
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
        $cloudflareEnabledDomains = array_values(array_map(
            static fn (array $mapping): string => (string) ($mapping['domain'] ?? ''),
            $this->cloudflareDomainsFromStore()
        ));
        $cloudflareEnabledDomains = array_values(array_filter(
            $cloudflareEnabledDomains,
            static fn (string $domain): bool => $domain !== ''
        ));

        $this->render('settings/integrations.php', [
            'csrfToken' => $this->csrf->token(),
            'providers' => IntegrationRegistry::providers(),
            'integrations' => $integrations,
            'proxyIntegrations' => IntegrationRegistry::filterByCategory($integrations, 'proxy'),
            'dnsIntegrations' => IntegrationRegistry::filterByCategory($integrations, 'dns'),
            'cloudflareEnabledDomains' => $cloudflareEnabledDomains,
        ]);
    }

    public function integrationsAction(): void
    {
        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            Session::setFlash('error', 'Invalid CSRF token.');
            $this->redirect('settings-integrations');
        }

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

                    $this->settingsStore->integrationUpsert([
                        'id' => str_replace('.', '', uniqid($providerKey . '_', true)),
                        'name' => $name,
                        'provider' => $providerKey,
                        'category' => (string) $providers[$providerKey]['category'],
                        'settings' => $this->normalizeIntegrationSettings(
                            $providerKey,
                            is_array($_POST['settings'] ?? null) ? $_POST['settings'] : []
                        ),
                    ]);
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

                    $existing = $this->settingsStore->integrationGet($id);
                    if ($existing === null) {
                        throw new RuntimeException('Integration not found.');
                    }

                    $providerKey = (string) ($existing['provider'] ?? '');
                    if (!isset($providers[$providerKey])) {
                        throw new RuntimeException('Invalid integration provider.');
                    }

                    $this->settingsStore->integrationUpsert([
                        'id' => $id,
                        'name' => $name,
                        'provider' => $providerKey,
                        'category' => (string) $providers[$providerKey]['category'],
                        'settings' => $this->normalizeIntegrationSettings(
                            $providerKey,
                            is_array($_POST['settings'] ?? null) ? $_POST['settings'] : [],
                            is_array($existing['settings'] ?? null) ? $existing['settings'] : []
                        ),
                    ]);
                    Session::setFlash('success', 'Integration updated.');
                    break;

                case 'delete':
                    $id = trim((string) ($_POST['id'] ?? ''));
                    if ($id === '') {
                        throw new RuntimeException('Integration ID is required.');
                    }

                    if ($this->settingsStore->integrationGet($id) === null) {
                        throw new RuntimeException('Integration not found.');
                    }

                    $this->settingsStore->integrationDelete($id);
                    Session::setFlash('success', 'Integration removed.');
                    break;

                default:
                    throw new RuntimeException('Invalid integrations action.');
            }

            $this->syncLegacyIntegrationSettings($this->integrationsFromStore());
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
                $result = $this->testIntegration($integration);
                echo json_encode(array_merge(['ok' => true], $result));
            } catch (RuntimeException $e) {
                http_response_code(422);
                echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
            }

            return;
        }

        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Integration not found.']);
    }

    public function integrationsCloudflareDomainTestAction(): void
    {
        header('Content-Type: application/json');

        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Invalid CSRF token.']);
            return;
        }

        $domain = strtolower(trim((string) ($_POST['domain'] ?? '')));
        if ($domain === '') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'Domain is required.']);
            return;
        }

        $mapping = null;
        foreach ($this->cloudflareDomainsFromStore() as $row) {
            if (strtolower(trim((string) ($row['domain'] ?? ''))) !== $domain) {
                continue;
            }

            $mapping = $row;
            break;
        }

        if ($mapping === null) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'message' => 'Cloudflare mapping not found for domain.']);
            return;
        }

        $zoneId = trim((string) ($mapping['zone_id'] ?? ''));
        $token = trim((string) ($mapping['api_token'] ?? ''));
        if ($zoneId === '' || $token === '') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'Cloudflare zone/token is missing for this domain.']);
            return;
        }

        try {
            $result = (new HttpClient($this->config->getBool('CURL_VERIFY_SSL', true)))->get(
                'https://api.cloudflare.com/client/v4/zones/' . rawurlencode($zoneId),
                ['Authorization: Bearer ' . $token]
            );

            $ok = $result['status'] === 200 && !empty($result['body']['success']);
            if (!$ok) {
                $message = 'Cloudflare test failed.';
                if (is_array($result['body'] ?? null) && !empty($result['body']['errors'][0]['message'])) {
                    $message = (string) $result['body']['errors'][0]['message'];
                }

                http_response_code(422);
                echo json_encode(['ok' => false, 'message' => $message]);
                return;
            }

            echo json_encode(['ok' => true, 'message' => 'Cloudflare credentials are valid for this domain.']);
        } catch (RuntimeException $e) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
        }
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

            $existingId = 'cloudflare_enabled';
            foreach ($this->settingsStore->integrationGetAll() as $row) {
                if (($row['provider'] ?? '') === 'cloudflare') {
                    $existingId = (string) $row['id'];
                    break;
                }
            }

            $entry = [
                'id' => $existingId,
                'name' => $name,
                'provider' => 'cloudflare',
                'category' => 'dns',
                'settings' => [],
            ];

            $this->settingsStore->integrationUpsert($entry);

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
            $adminEmail = strtolower(trim((string) $this->config->get('ADMIN_USER', '')));
            if ($adminEmail !== '') {
                $this->settingsStore->userUpsert(['email' => $adminEmail, 'password_hash' => $hash]);
            }
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
        $users = [];
        foreach ($this->settingsStore->userGetAll() as $user) {
            if ($user['is_primary']) {
                continue;
            }
            $users[$user['email']] = $user['password_hash'];
        }

        return $users;
    }

    /**
     * @return list<array{id:string,name:string,provider:string,category:string,settings:array<string,string>}>
     */
    private function integrationsFromStore(): array
    {
        return $this->settingsStore->integrationGetAll();
    }

    /**
     * @param list<array{id:string,name:string,provider:string,category:string,settings:array<string,string>}> $integrations
     */
    private function persistIntegrations(array $integrations): void
    {
        // Determine which IDs are in the new list
        $newIds = array_column($integrations, 'id');
        $existing = $this->settingsStore->integrationGetAll();

        // Delete removed integrations
        foreach ($existing as $old) {
            if (!in_array($old['id'], $newIds, true)) {
                $this->settingsStore->integrationDelete($old['id']);
            }
        }

        // Upsert each integration in the new list
        foreach ($integrations as $integration) {
            $this->settingsStore->integrationUpsert($integration);
        }
    }

    /**
     * @param list<array{id:string,name:string,provider:string,category:string,settings:array<string,string>}> $integrations
     */
    private function syncLegacyIntegrationSettings(array $integrations): void
    {
        unset($integrations);
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
    private function testIntegration(array $integration): array
    {
        $settings = is_array($integration['settings'] ?? null) ? $integration['settings'] : [];
        $verifySsl = $this->config->getBool('CURL_VERIFY_SSL', true);

        if (($integration['provider'] ?? '') === 'npm') {
            $baseUrl = rtrim((string) ($settings['base_url'] ?? ''), '/');
            if ($baseUrl === '') {
                throw new RuntimeException('NPM base URL is not configured.');
            }

            $result = (new HttpClient($verifySsl))->get($baseUrl . '/api');
            $body = $result['body'] ?? null;
            $status = is_array($body) ? ($body['status'] ?? '') : '';

            if ($status !== 'OK') {
                throw new RuntimeException('NPM API did not return OK status.');
            }

            return [
                'type' => 'generic',
                'message' => 'Connection successful.',
            ];
        }

        if (($integration['provider'] ?? '') === 'cloudflare') {
            $domains = array_values(array_map(
                static fn (array $mapping): string => (string) ($mapping['domain'] ?? ''),
                $this->cloudflareDomainsFromStore()
            ));
            $domains = array_values(array_filter($domains, static fn (string $domain): bool => $domain !== ''));

            return [
                'type' => 'cloudflare-domains',
                'domains' => $domains,
                'message' => $domains === []
                    ? 'Cloudflare is enabled, but no domains currently have Cloudflare DNS configured.'
                    : 'Cloudflare is enabled for one or more domains.',
            ];
        }

        throw new RuntimeException('Unsupported integration provider.');
    }

    /**
     * @return list<array{domain:string, zone_id:string, api_token:string}>
     */
    private function cloudflareDomainsFromStore(): array
    {
        return $this->settingsStore->domainGetCfMappings();
    }

    /**
     * @param list<array{domain:string, zone_id:string, api_token:string}> $mappings
     */
    private function persistCloudflareMappings(array $mappings): void
    {
        // Rebuild domains table from mappings and sync CF_DOMAINS_JSON
        foreach ($mappings as $mapping) {
            $this->settingsStore->domainUpsert([
                'domain' => $mapping['domain'],
                'cf_zone_id' => $mapping['zone_id'],
                'cf_api_token' => $mapping['api_token'],
            ]);
        }
        $this->settingsStore->syncCfDomainsJson();
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

        $oldAdmin = strtolower(trim((string) $this->config->get('ADMIN_USER', 'admin@example.com')));

        $this->settingsStore->setMany([
            'ADMIN_USER' => $newAdmin,
            'ADMIN_FULL_NAME' => $newAdminFullName,
        ]);

        if ($oldAdmin !== '' && $oldAdmin !== $newAdmin) {
            $this->settingsStore->userUpdateEmail($oldAdmin, $newAdmin);
        }

        $this->settingsStore->userUpsert([
            'email' => $newAdmin,
            'full_name' => $newAdminFullName,
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

        $existing = $this->settingsStore->userGet($targetUser);
        if ($existing === null || (bool) $existing['is_primary']) {
            throw new RuntimeException('User not found.');
        }

        if ($newEmail !== $targetUser) {
            if ($this->settingsStore->userGet($newEmail) !== null) {
                throw new RuntimeException('Email already exists.');
            }
            $this->settingsStore->userUpdateEmail($targetUser, $newEmail);
        }

        $this->settingsStore->userUpsert([
            'email' => $newEmail,
            'full_name' => $newFullName,
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

        if ($this->settingsStore->userGet($username) !== null) {
            throw new RuntimeException('Email already exists.');
        }

        $this->settingsStore->userUpsert([
            'email' => $username,
            'password_hash' => $hash,
            'full_name' => $fullName,
            'account_type' => $role,
            'is_primary' => 0,
            'active' => 1,
            'created_at' => date('c'),
            'last_login_at' => '',
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

        $existing = $this->settingsStore->userGet($targetUser);
        if ($existing === null || (bool) $existing['is_primary']) {
            throw new RuntimeException('User not found.');
        }

        $this->settingsStore->userUpsert(['email' => $targetUser, 'password_hash' => $hash]);
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

        $this->settingsStore->userDelete($targetUser);
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

        $this->settingsStore->userUpsert([
            'email' => $targetUser,
            'active' => (int) (bool) ($records[$targetUser]['active'] ?? true),
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

        $this->settingsStore->userUpsert([
            'email' => $targetUser,
            'account_type' => $nextRole,
        ]);
    }

    /**
     * @return array<string, array{full_name:string,account_type:string,active:bool,created_at:string,last_login_at:string}>
     */
    private function usersMetaFromStore(): array
    {
        $meta = [];
        foreach ($this->settingsStore->userGetAll() as $user) {
            if ($user['is_primary']) {
                continue;
            }
            $meta[$user['email']] = [
                'full_name' => $user['full_name'],
                'account_type' => $user['account_type'],
                'active' => (bool) $user['active'],
                'created_at' => $user['created_at'],
                'last_login_at' => $user['last_login_at'],
            ];
        }

        return $meta;
    }

    /**
     * @return array<string, array{email:string,full_name:string,account_type:string,active:bool,created_at:string,last_login_at:string,is_primary:bool,is_online:bool}>
     */
    private function userRecordsFromStore(): array
    {
        $records = [];
        foreach ($this->settingsStore->userGetAll() as $user) {
            $email = $user['email'];
            $fullName = trim((string) ($user['full_name'] ?? ''));
            $records[$email] = [
                'email' => $email,
                'full_name' => $fullName !== '' ? $fullName : $email,
                'account_type' => (string) ($user['account_type'] ?? 'user') === 'admin' ? 'admin' : 'user',
                'active' => (bool) $user['active'],
                'created_at' => (string) ($user['created_at'] ?? ''),
                'last_login_at' => (string) ($user['last_login_at'] ?? ''),
                'is_primary' => (bool) ($user['is_primary'] ?? false),
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
        $domains = [];
        foreach ($this->settingsStore->domainGetAll() as $domainRecord) {
            $domain = (string) ($domainRecord['domain'] ?? '');
            if ($domain !== '') {
                $domains[] = $domain;
            }
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
        unset($email);

        return strtolower(bin2hex(random_bytes(3))) . '@vhost-manager.npm';
    }
}
