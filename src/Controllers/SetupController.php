<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Session;
use App\Security\Csrf;
use App\Services\HttpClient;
use App\Services\IntegrationRegistry;
use App\Services\SettingsStore;
use RuntimeException;

final class SetupController extends BaseController
{
    public function __construct(
        Config $config,
        private readonly Csrf $csrf,
        SettingsStore $settingsStore,
        private readonly HttpClient $httpClient
    ) {
        parent::__construct($config, $settingsStore);
    }

    /**
     * Show page 1 of setup: Admin credentials
     */
    public function show(): void
    {
        if ($this->isSetupComplete()) {
            $this->redirect(Session::isAuthenticated() ? 'dashboard' : 'login');
        }

        $pendingSetup = $_SESSION['setup_pending'] ?? null;
        $pendingSetup = is_array($pendingSetup) ? $pendingSetup : [];

        $allowedDocrootBases = $this->normalizeAllowedDocrootBases((string) ($pendingSetup['ALLOWED_DOCROOT_BASES'] ?? ''));
        if ($allowedDocrootBases === []) {
            $allowedDocrootBases = $this->allowedDocrootBases();
        }

        $appUrl = trim((string) ($pendingSetup['APP_URL'] ?? $this->config->get('APP_URL', 'http://localhost:8080')));
        [$appUrlScheme, $appUrlHostPath] = $this->splitAppUrl($appUrl);

        $this->render('auth/setup.php', [
            'csrfToken' => $this->csrf->token(),
            'setupAdminEmail' => (string) ($pendingSetup['ADMIN_USER'] ?? ''),
            'setupAdminFullName' => (string) ($pendingSetup['ADMIN_FULL_NAME'] ?? ''),
            'appUrlScheme' => $appUrlScheme,
            'appUrlHostPath' => $appUrlHostPath,
            'allowedDocrootBases' => $allowedDocrootBases,
            'defaultDocrootBase' => (string) ($pendingSetup['DEFAULT_DOCROOT_BASE'] ?? $this->config->get('DEFAULT_DOCROOT_BASE', '/var/www')),
            'hasPendingPassword' => trim((string) ($pendingSetup['ADMIN_PASSWORD_HASH'] ?? '')) !== '',
            'fieldErrors' => Session::consumeFieldErrors(),
            'passwordPolicyLevel' => (int) $this->config->get('PASSWORD_POLICY_LEVEL', 3),
            'passwordPolicyRequirements' => \App\Core\password_policy_requirements((int) $this->config->get('PASSWORD_POLICY_LEVEL', 3)),
        ]);
    }

    /**
     * Process page 1: validate credentials and basics, store in session
     */
    public function complete(): void
    {
        if ($this->isSetupComplete()) {
            $this->redirect('login');
        }

        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            Session::setFlash('error', 'Invalid CSRF token.');
            $this->redirect('setup');
        }

        $adminEmail = strtolower(trim((string) ($_POST['admin_email'] ?? '')));
        $adminFullName = trim((string) ($_POST['admin_full_name'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
        $appUrlScheme = strtolower(trim((string) ($_POST['app_url_scheme'] ?? 'http')));
        $appUrlHostPath = trim((string) ($_POST['app_url_hostpath'] ?? ''));
        if ($appUrlHostPath === '') {
            $appUrlHostPath = trim((string) ($_POST['app_url'] ?? ''));
        }
        $allowedDocrootBasesRaw = $_POST['allowed_docroot_bases'] ?? [];
        $defaultDocrootBase = trim((string) ($_POST['default_docroot_base'] ?? ''));
        $pendingSetup = $_SESSION['setup_pending'] ?? null;
        $pendingSetup = is_array($pendingSetup) ? $pendingSetup : [];

        // Persist submitted values immediately so they survive error redirects.
        $cleanHostPath = ltrim((string) (preg_replace('#^https?://#i', '', $appUrlHostPath) ?? $appUrlHostPath), '/');
        $_SESSION['setup_pending'] = array_merge($pendingSetup, [
            'ADMIN_USER' => $adminEmail,
            'ADMIN_FULL_NAME' => $adminFullName,
            'APP_URL' => $appUrlScheme . '://' . $cleanHostPath,
        ]);
        $pendingSetup = $_SESSION['setup_pending'];

        // Collect all field errors before any redirect so the user sees everything at once.
        $fieldErrors = [];

        if (!in_array($appUrlScheme, ['http', 'https'], true)) {
            $fieldErrors['app_url'] = 'Protocol must be http or https.';
        }

        if ($adminEmail === '' || filter_var($adminEmail, FILTER_VALIDATE_EMAIL) === false) {
            $fieldErrors['admin_email'] = 'Must be a valid email address.';
        }

        if ($adminFullName === '' || strlen($adminFullName) < 2 || strlen($adminFullName) > 120) {
            $fieldErrors['admin_full_name'] = 'Full name must be between 2 and 120 characters.';
        }

        $keepPendingPassword =
            $password === ''
            && $confirmPassword === ''
            && trim((string) ($pendingSetup['ADMIN_PASSWORD_HASH'] ?? '')) !== '';

        if (!$keepPendingPassword) {
            $policyLevel = (int) $this->config->get('PASSWORD_POLICY_LEVEL', 3);
            $passwordErrors = password_policy_errors($password, $policyLevel);
            if ($passwordErrors !== []) {
                $fieldErrors['password'] = $passwordErrors[0];
            } elseif (!hash_equals($password, $confirmPassword)) {
                $fieldErrors['confirm_password'] = 'Passwords do not match.';
            }
        }

        $appUrlHostPath = $cleanHostPath;
        $appUrl = $appUrlScheme . '://' . $appUrlHostPath;

        if (!isset($fieldErrors['app_url']) && ($appUrl === '' || filter_var($appUrl, FILTER_VALIDATE_URL) === false)) {
            $fieldErrors['app_url'] = 'Must be a valid URL (e.g. localhost:8080).';
        }

        $allowedBases = $this->normalizeAllowedDocrootBases($allowedDocrootBasesRaw);
        if ($allowedBases === []) {
            $fieldErrors['allowed_docroot_bases'] = 'At least one allowed document root base is required.';
        } elseif ($defaultDocrootBase === '' || !in_array($defaultDocrootBase, $allowedBases, true)) {
            $fieldErrors['default_docroot_base'] = 'Default docroot base must be one of the allowed bases.';
        }

        if ($fieldErrors !== []) {
            Session::setFieldErrors($fieldErrors);
            $this->redirect('setup');
        }

        // No validation errors – process and store.
        $passwordHash = '';
        if ($keepPendingPassword) {
            $passwordHash = (string) $pendingSetup['ADMIN_PASSWORD_HASH'];
        } else {
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            if (!is_string($passwordHash) || $passwordHash === '') {
                Session::setFlash('error', 'Failed to hash password.');
                $this->redirect('setup');
            }
        }

        $scheme = (string) parse_url($appUrl, PHP_URL_SCHEME);

        // Store page 1 data in session for page 2 & 3.
        $_SESSION['setup_pending'] = [
            'ADMIN_USER' => $adminEmail,
            'ADMIN_FULL_NAME' => $adminFullName,
            'ADMIN_PASSWORD_HASH' => $passwordHash,
            'APP_URL' => $appUrl,
            'APP_HTTPS' => strtolower($scheme) === 'https' ? 'true' : 'false',
            'ALLOWED_DOCROOT_BASES' => implode(',', $allowedBases),
            'DEFAULT_DOCROOT_BASE' => $defaultDocrootBase,
        ];

        if (!$keepPendingPassword) {
            $_SESSION['setup_pending_admin_password'] = $password;
        }

        // Skip to domain setup if integrations are disabled
        if (!$this->config->getBool('ENABLE_INTEGRATIONS', true)) {
            $this->redirect('setup-domain');
        }

        $this->redirect('setup-proxy');
    }

    /**
     * @return array{0:string,1:string}
     */
    private function splitAppUrl(string $appUrl): array
    {
        $scheme = strtolower((string) parse_url($appUrl, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            $scheme = 'http';
        }

        $hostPath = preg_replace('#^https?://#i', '', trim($appUrl));
        $hostPath = is_string($hostPath) ? ltrim($hostPath, '/') : '';

        return [$scheme, $hostPath !== '' ? $hostPath : 'localhost:8080'];
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

    /**
     * Show page 2 of setup: Proxy integration (NPM)
     */
    public function showProxy(): void
    {
        if ($this->isSetupComplete()) {
            $this->redirect(Session::isAuthenticated() ? 'dashboard' : 'login');
        }

        // Skip proxy setup if integrations disabled
        if (!$this->config->getBool('ENABLE_INTEGRATIONS', true)) {
            $this->redirect('setup-domain');
        }

        $pendingSetup = $_SESSION['setup_pending'] ?? null;
        if (!is_array($pendingSetup)) {
            $this->redirect('setup');
        }

        $pending = $_SESSION['setup_pending_proxy'] ?? null;
        $pending = is_array($pending) ? $pending : [];

        $proxyProviders = $this->setupProvidersByCategory('proxy');
        $selectedProxyProvider = (string) ($pending['provider'] ?? (array_key_first($proxyProviders) ?: ''));
        $proxyStep = (string) ($pending['step'] ?? '1');
        if (!in_array($proxyStep, ['1', '2'], true)) {
            $proxyStep = '1';
        }
        $fieldErrors = Session::consumeFieldErrors();

        $this->render('auth/setup-proxy.php', [
            'csrfToken'         => $this->csrf->token(),
            'name'              => (string) ($pending['name'] ?? ''),
            'proxyProviders'    => $proxyProviders,
            'selectedProxyProvider' => $selectedProxyProvider,
            'proxyStep'         => $proxyStep,
            'fieldErrors'       => $fieldErrors,
            'npmBaseUrlScheme'  => (string) ($pending['base_url_scheme'] ?? 'http'),
            'npmBaseUrlInput'   => (string) ($pending['base_url_input'] ?? ''),
            'npmAdminIdentity'  => (string) ($pending['admin_identity'] ?? ''),
            'npmRuntimeIdentity'=> (string) ($pending['identity'] ?? ''),
            'npmForwardHost'    => (string) ($pending['forward_host'] ?? ''),
            'npmForwardPort'    => (string) ($pending['forward_port'] ?? '80'),
        ]);
    }

    /**
     * Process page 2: validate proxy integration, provision service account, store in session
     */
    public function completeProxy(): void
    {
        if ($this->isSetupComplete()) {
            $this->redirect('login');
        }

        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            Session::setFlash('error', 'Invalid CSRF token.');
            $this->redirect('setup-proxy');
        }

        $pendingSetup = $_SESSION['setup_pending'] ?? null;
        if (!is_array($pendingSetup)) {
            $this->redirect('setup');
        }

        if (isset($_POST['skip'])) {
            unset($_SESSION['setup_pending_proxy']);
            $this->redirect('setup-dns');
        }

        if (isset($_POST['back_step'])) {
            $pending = $_SESSION['setup_pending_proxy'] ?? [];
            if (!is_array($pending)) {
                $pending = [];
            }
            $pending['step'] = '1';
            $_SESSION['setup_pending_proxy'] = $pending;
            $this->redirect('setup-proxy');
        }

        $proxyProviders = $this->setupProvidersByCategory('proxy');
        $providerKey = trim((string) ($_POST['proxy_provider'] ?? ''));
        if ($providerKey === '' && $proxyProviders !== []) {
            $providerKey = (string) array_key_first($proxyProviders);
        }

        if (!isset($proxyProviders[$providerKey])) {
            Session::setFlash('error', 'Invalid proxy provider selected.');
            $this->redirect('setup-proxy');
        }

        $pending = $_SESSION['setup_pending_proxy'] ?? [];
        if (!is_array($pending)) {
            $pending = [];
        }

        $step = trim((string) ($_POST['proxy_step'] ?? ($pending['step'] ?? '1')));
        if (!in_array($step, ['1', '2'], true)) {
            $step = '1';
        }

        $name              = trim((string) ($_POST['name'] ?? ''));
        $npmBaseUrlScheme  = strtolower(trim((string) ($_POST['npm_base_url_scheme'] ?? 'http')));
        if (!in_array($npmBaseUrlScheme, ['http', 'https'], true)) {
            $npmBaseUrlScheme = 'http';
        }
        $npmBaseUrlInput   = trim((string) ($_POST['npm_base_url_input'] ?? ''));
        $npmBaseUrlInput   = preg_replace('#^https?://#i', '', $npmBaseUrlInput);
        $npmBaseUrlInput   = is_string($npmBaseUrlInput) ? ltrim($npmBaseUrlInput, '/') : '';
        $npmBaseUrl        = $npmBaseUrlInput !== '' ? $npmBaseUrlScheme . '://' . $npmBaseUrlInput : '';
        $npmAdminIdentity  = strtolower(trim((string) ($_POST['npm_admin_identity'] ?? '')));
        $npmAdminSecret    = trim((string) ($_POST['npm_admin_secret'] ?? ''));
        $npmForwardHost    = trim((string) ($_POST['npm_forward_host'] ?? ''));
        $npmForwardPort    = (int) ($_POST['npm_forward_port'] ?? 80);

        $fieldErrors = [];
        if ($name === '') {
            $fieldErrors['name'] = 'Integration name is required.';
        }

        // Persist form values so they survive error redirects.
        $_SESSION['setup_pending_proxy'] = [
            'provider'        => $providerKey,
            'name'            => $name,
            'step'            => $step,
            'base_url_scheme' => $npmBaseUrlScheme,
            'base_url_input'  => $npmBaseUrlInput,
            'admin_identity'  => $npmAdminIdentity,
            'forward_host'    => $npmForwardHost,
            'forward_port'    => (string) $npmForwardPort,
            'identity'        => (string) ($pending['identity'] ?? ''),
            'secret'          => (string) ($pending['secret'] ?? ''),
            'base_url'        => (string) ($pending['base_url'] ?? ''),
        ];

        // Non-NPM providers are included automatically in setup dropdowns.
        // They can be enabled in setup and configured in detail later from Integrations.
        if ($providerKey !== 'npm') {
            if ($fieldErrors !== []) {
                Session::setFieldErrors($fieldErrors);
                $this->redirect('setup-proxy');
            }

            $_SESSION['setup_pending_proxy'] = [
                'provider' => $providerKey,
                'name' => $name,
                'settings' => [],
            ];
            $this->redirect('setup-dns');
        }

        if ($step === '1') {
            if ($npmBaseUrlInput === '' || filter_var($npmBaseUrl, FILTER_VALIDATE_URL) === false) {
                $fieldErrors['npm_base_url_input'] = 'NPM base URL must be a valid URL.';
            }

            if ($npmAdminIdentity === '' || filter_var($npmAdminIdentity, FILTER_VALIDATE_EMAIL) === false) {
                $fieldErrors['npm_admin_identity'] = 'NPM admin email must be a valid email address.';
            }

            if ($npmAdminSecret === '') {
                $fieldErrors['npm_admin_secret'] = 'NPM admin password or API token is required.';
            }

            if ($fieldErrors !== []) {
                Session::setFieldErrors($fieldErrors);
                $this->redirect('setup-proxy');
            }

            $testError = $this->testExternalNpmConnection($npmBaseUrl, $npmAdminIdentity, $npmAdminSecret);
            if ($testError !== null) {
                Session::setFieldErrors([
                    'npm_step_1' => $testError,
                ]);
                $this->redirect('setup-proxy');
            }

            try {
                $serviceAccount = $this->resolveSetupNpmServiceAccount($name, $npmBaseUrl, $npmAdminIdentity, $npmAdminSecret);
            } catch (RuntimeException $e) {
                Session::setFieldErrors([
                    'npm_step_1' => $e->getMessage(),
                ]);
                $this->redirect('setup-proxy');
            }

            $_SESSION['setup_pending_proxy'] = [
                'provider'     => $providerKey,
                'name'         => $name,
                'step'         => '2',
                'base_url'     => $npmBaseUrl,
                'identity'     => $serviceAccount['identity'],
                'secret'       => $serviceAccount['secret'],
                'base_url_scheme' => $npmBaseUrlScheme,
                'base_url_input' => $npmBaseUrlInput,
                'admin_identity' => $npmAdminIdentity,
                'forward_host' => $npmForwardHost,
                'forward_port' => (string) $npmForwardPort,
            ];

            $this->redirect('setup-proxy');
        }

        if ((string) ($pending['identity'] ?? '') === '' || (string) ($pending['secret'] ?? '') === '' || (string) ($pending['base_url'] ?? '') === '') {
            Session::setFlash('error', 'NPM setup step 1 must be completed before step 2.');
            $this->redirect('setup-proxy');
        }

        if ($npmForwardHost === '') {
            $fieldErrors['npm_forward_host'] = 'Forward URL/host is required.';
        }

        if ($npmForwardPort < 1 || $npmForwardPort > 65535) {
            $fieldErrors['npm_forward_port'] = 'Forward port must be between 1 and 65535.';
        }

        if ($fieldErrors !== []) {
            Session::setFieldErrors($fieldErrors);
            $this->redirect('setup-proxy');
        }

        $_SESSION['setup_pending_proxy'] = [
            'provider'     => $providerKey,
            'name'         => $name,
            'base_url'     => (string) $pending['base_url'],
            'identity'     => (string) $pending['identity'],
            'secret'       => (string) $pending['secret'],
            'forward_host' => $npmForwardHost,
            'forward_port' => (string) $npmForwardPort,
        ];

        $this->redirect('setup-dns');
    }

    /**
     * Show page 3 of setup: DNS integration (Cloudflare)
     */
    public function showDns(): void
    {
        if ($this->isSetupComplete()) {
            $this->redirect(Session::isAuthenticated() ? 'dashboard' : 'login');
        }

        $pendingSetup = $_SESSION['setup_pending'] ?? null;
        if (!is_array($pendingSetup)) {
            $this->redirect('setup');
        }

        $pending = $_SESSION['setup_pending_dns'] ?? null;
        $pending = is_array($pending) ? $pending : [];

        $dnsProviders = $this->setupProvidersByCategory('dns');
        $selectedDnsProvider = (string) ($pending['provider'] ?? (array_key_first($dnsProviders) ?: ''));

        $this->render('auth/setup-dns.php', [
            'csrfToken' => $this->csrf->token(),
            'name' => (string) ($pending['name'] ?? ''),
            'dnsProviders' => $dnsProviders,
            'selectedDnsProvider' => $selectedDnsProvider,
            'fieldErrors' => Session::consumeFieldErrors(),
        ]);
    }

    /**
     * Process page 3: validate DNS integration, store in session
     */
    public function completeDns(): void
    {
        if ($this->isSetupComplete()) {
            $this->redirect('login');
        }

        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            Session::setFlash('error', 'Invalid CSRF token.');
            $this->redirect('setup-dns');
        }

        // Skip DNS setup if integrations disabled
        if (!$this->config->getBool('ENABLE_INTEGRATIONS', true)) {
            $this->redirect('setup-domain');
        }

        $pendingSetup = $_SESSION['setup_pending'] ?? null;
        if (!is_array($pendingSetup)) {
            $this->redirect('setup');
        }

        if (isset($_POST['skip'])) {
            unset($_SESSION['setup_pending_dns']);
            $this->redirect('setup-domain');
        }

        $dnsProviders = $this->setupProvidersByCategory('dns');
        $providerKey = trim((string) ($_POST['dns_provider'] ?? ''));
        if ($providerKey === '' && $dnsProviders !== []) {
            $providerKey = (string) array_key_first($dnsProviders);
        }

        if (!isset($dnsProviders[$providerKey])) {
            Session::setFieldErrors(['dns_provider' => 'Invalid DNS provider selected.']);
            $this->redirect('setup-dns');
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            Session::setFieldErrors(['name' => 'Integration name is required.']);
            $this->redirect('setup-dns');
        }

        $_SESSION['setup_pending_dns'] = ['name' => $name, 'provider' => $providerKey];
        $this->redirect('setup-domain');
    }

    /**
     * Show page 4 of setup: Add First Domain
     */
    public function showDomain(): void
    {
        if ($this->isSetupComplete()) {
            $this->redirect(Session::isAuthenticated() ? 'dashboard' : 'login');
        }

        $pendingSetup = $_SESSION['setup_pending'] ?? null;
        if (!is_array($pendingSetup)) {
            $this->redirect('setup');
        }

        $pending = $_SESSION['setup_pending_domain'] ?? [];
        if (!is_array($pending)) { $pending = []; }

        $setupDomains = $pending['domains'] ?? [];
        if (!is_array($setupDomains)) { $setupDomains = []; }

        $this->render('auth/setup-domain.php', [
            'csrfToken'      => $this->csrf->token(),
            'fieldErrors'    => Session::consumeFieldErrors(),
            'setupDomains'   => $setupDomains,
        ]);
    }

    /**
     * Process page 4: collect domains, store in session, redirect to confirm
     */
    public function completeDomain(): void
    {
        if ($this->isSetupComplete()) {
            $this->redirect('login');
        }

        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            Session::setFlash('error', 'Invalid CSRF token.');
            $this->redirect('setup-domain');
        }

        if (isset($_POST['skip'])) {
            $_SESSION['setup_pending_domain'] = ['domains' => []];
            $this->redirect('setup-confirm');
        }

        $domainsRaw = $_POST['domains'] ?? [];
        $domainsRaw = is_array($domainsRaw) ? $domainsRaw : [];

        $setupDomains = [];
        foreach ($domainsRaw as $domainInput) {
            $domain = strtolower(trim((string) $domainInput));
            if ($domain === '') {
                continue;
            }

            if (!\App\Security\DomainValidator::isValid($domain)) {
                Session::setFieldErrors(['domain' => "Domain '$domain' is not a valid FQDN (e.g. example.com)."]);
                $this->redirect('setup-domain');
            }

            if (!in_array($domain, $setupDomains, true)) {
                $setupDomains[] = $domain;
            }
        }

        $_SESSION['setup_pending_domain'] = ['domains' => $setupDomains];
        $this->redirect('setup-confirm');
    }

    /**
     * Show page 5 of setup: Confirmation review
     */
    public function showConfirm(): void
    {
        if ($this->isSetupComplete()) {
            $this->redirect(Session::isAuthenticated() ? 'dashboard' : 'login');
        }

        $pendingSetup = $_SESSION['setup_pending'] ?? null;
        if (!is_array($pendingSetup)) {
            $this->redirect('setup');
        }

        $proxyIntegration = isset($_SESSION['setup_pending_proxy']) && is_array($_SESSION['setup_pending_proxy'])
            ? $_SESSION['setup_pending_proxy'] : null;
        $dnsIntegration = isset($_SESSION['setup_pending_dns']) && is_array($_SESSION['setup_pending_dns'])
            ? $_SESSION['setup_pending_dns'] : null;

        $providers = IntegrationRegistry::providers();
        $proxyProviderLabel = 'Not configured';
        $dnsProviderLabel = 'Not configured';
        if (is_array($proxyIntegration)) {
            $proxyProviderKey = (string) ($proxyIntegration['provider'] ?? '');
            if ($proxyProviderKey !== '' && isset($providers[$proxyProviderKey])) {
                $proxyProviderLabel = (string) ($providers[$proxyProviderKey]['label'] ?? $proxyProviderKey);
            }
        }
        if (is_array($dnsIntegration)) {
            $dnsProviderKey = (string) ($dnsIntegration['provider'] ?? '');
            if ($dnsProviderKey !== '' && isset($providers[$dnsProviderKey])) {
                $dnsProviderLabel = (string) ($providers[$dnsProviderKey]['label'] ?? $dnsProviderKey);
            }
        }

        $summary = [
            'admin_email'          => $pendingSetup['ADMIN_USER'] ?? '',
            'admin_full_name'      => $pendingSetup['ADMIN_FULL_NAME'] ?? '',
            'admin_password'       => $_SESSION['setup_pending_admin_password'] ?? '',
            'app_url'              => $pendingSetup['APP_URL'] ?? '',
            'app_https'            => ($pendingSetup['APP_HTTPS'] ?? 'false') === 'true',
            'allowed_docroot_bases'=> $pendingSetup['ALLOWED_DOCROOT_BASES'] ?? '',
            'default_docroot_base' => $pendingSetup['DEFAULT_DOCROOT_BASE'] ?? '',
            'setup_domains'        => ($_SESSION['setup_pending_domain']['domains'] ?? []),
            'proxy_integration'    => $proxyIntegration,
            'dns_integration'      => $dnsIntegration,
            'proxy_provider_label' => $proxyProviderLabel,
            'dns_provider_label' => $dnsProviderLabel,
        ];

        $this->render('auth/setup-confirm.php', [
            'csrfToken' => $this->csrf->token(),
            'summary'   => $summary,
        ]);
    }

    /**
     * Process page 5: Save all settings and auto-login
     */
    public function completeConfirm(): void
    {
        if ($this->isSetupComplete()) {
            $this->redirect('login');
        }

        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            Session::setFlash('error', 'Invalid CSRF token.');
            $this->redirect('setup-confirm');
        }

        $pendingSetup = $_SESSION['setup_pending'] ?? null;
        if (!is_array($pendingSetup)) {
            $this->redirect('setup');
        }

        $settings = $pendingSetup;
        unset(
            $settings['ADMIN_USER'],
            $settings['ADMIN_FULL_NAME'],
            $settings['ADMIN_PASSWORD_HASH'],
            $settings['ADMIN_CREATED_AT'],
            $settings['ADMIN_LAST_LOGIN_AT'],
            $settings['USERS_JSON'],
            $settings['USERS_META_JSON'],
            $settings['DOMAINS_JSON'],
            $settings['CF_DOMAINS_JSON']
        );

        $adminSettings = $pendingSetup;
        if (trim((string) ($adminSettings['ADMIN_CREATED_AT'] ?? '')) === '') {
            $adminSettings['ADMIN_CREATED_AT'] = date('c');
        }
        if (trim((string) ($adminSettings['ADMIN_FULL_NAME'] ?? '')) === '') {
            $adminSettings['ADMIN_FULL_NAME'] = (string) ($adminSettings['ADMIN_USER'] ?? 'Admin User');
        }

        $proxyIntegration = isset($_SESSION['setup_pending_proxy']) && is_array($_SESSION['setup_pending_proxy'])
            ? $_SESSION['setup_pending_proxy'] : null;
        $dnsIntegration = isset($_SESSION['setup_pending_dns']) && is_array($_SESSION['setup_pending_dns'])
            ? $_SESSION['setup_pending_dns'] : null;
        $providers = IntegrationRegistry::providers();

        try {
            $this->settingsStore->setMany($settings);

            // Write admin to the users table as the primary user.
            $adminEmail = strtolower(trim((string) ($adminSettings['ADMIN_USER'] ?? '')));
            if ($adminEmail !== '') {
                $this->settingsStore->userUpsert([
                    'email'        => $adminEmail,
                    'password_hash'=> (string) ($adminSettings['ADMIN_PASSWORD_HASH'] ?? ''),
                    'full_name'    => (string) ($adminSettings['ADMIN_FULL_NAME'] ?? ''),
                    'account_type' => 'admin',
                    'is_primary'   => 1,
                    'active'       => 1,
                    'created_at'   => (string) ($adminSettings['ADMIN_CREATED_AT'] ?? date('c')),
                    'last_login_at'=> '',
                ]);
            }

            // Save proxy (NPM) integration to integrations table.
            if ($proxyIntegration !== null) {
                $proxyProviderKey = (string) ($proxyIntegration['provider'] ?? '');
                if ($proxyProviderKey !== '' && isset($providers[$proxyProviderKey])) {
                    $proxySettings = [];
                    if ($proxyProviderKey === 'npm' && isset($proxyIntegration['base_url'])) {
                        $proxySettings = [
                            'base_url' => (string) ($proxyIntegration['base_url'] ?? ''),
                            'identity' => (string) ($proxyIntegration['identity'] ?? ''),
                            'secret' => (string) ($proxyIntegration['secret'] ?? ''),
                            'forward_host' => (string) ($proxyIntegration['forward_host'] ?? ''),
                            'forward_port' => (string) ($proxyIntegration['forward_port'] ?? '80'),
                            'bootstrap_key' => '',
                        ];
                    } elseif (is_array($proxyIntegration['settings'] ?? null)) {
                        $proxySettings = $proxyIntegration['settings'];
                    }

                $this->settingsStore->integrationUpsert([
                        'id' => str_replace('.', '', uniqid($proxyProviderKey . '_', true)),
                        'name' => (string) ($proxyIntegration['name'] ?? $proxyProviderKey),
                        'provider' => $proxyProviderKey,
                        'category' => (string) ($providers[$proxyProviderKey]['category'] ?? 'proxy'),
                        'settings' => $proxySettings,
                ]);
                }
            }

            // Save DNS integration to integrations table.
            if ($dnsIntegration !== null) {
                $dnsProviderKey = (string) ($dnsIntegration['provider'] ?? '');
                if ($dnsProviderKey !== '' && isset($providers[$dnsProviderKey])) {
                $this->settingsStore->integrationUpsert([
                        'id' => str_replace('.', '', uniqid($dnsProviderKey . '_', true)),
                        'name' => (string) ($dnsIntegration['name'] ?? $dnsProviderKey),
                        'provider' => $dnsProviderKey,
                        'category' => (string) ($providers[$dnsProviderKey]['category'] ?? 'dns'),
                    'settings' => [],
                ]);
                }
            }

            // Save first domain if provided during setup.
            $pendingDomain = $_SESSION['setup_pending_domain'] ?? null;
            if (is_array($pendingDomain) && trim((string) ($pendingDomain['domain'] ?? '')) !== '') {
                $this->settingsStore->domainUpsert($pendingDomain);
            }
        } catch (RuntimeException $e) {
            Session::setFlash('error', $e->getMessage());
            $this->redirect('setup-confirm');
        }

        // Clean up setup session data.
        unset(
            $_SESSION['setup_pending'],
            $_SESSION['setup_pending_admin_password'],
            $_SESSION['setup_pending_proxy'],
            $_SESSION['setup_pending_dns'],
            $_SESSION['setup_pending_domain'],
            $_SESSION['setup_npm_accounts']
        );

        $adminEmail = strtolower(trim((string) ($adminSettings['ADMIN_USER'] ?? '')));
        Session::login($adminEmail);

        Session::setFlash('success', 'Setup complete and you are now logged in!');
        $this->redirect('overview');
    }

    public function serverIpAction(): void
    {
        header('Content-Type: application/json');

        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Invalid CSRF token.']);
            return;
        }

        $pendingSetup = $_SESSION['setup_pending'] ?? null;
        if (!is_array($pendingSetup)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'message' => 'Setup session is not active.']);
            return;
        }

        $ip = $this->detectServerIp();
        if ($ip === null) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'Unable to detect a valid server IPv4 address.']);
            return;
        }

        echo json_encode(['ok' => true, 'ip' => $ip]);
    }

    private function isSetupComplete(): bool
    {
        $primary = $this->settingsStore->userGetPrimary();

        return $primary !== null && trim((string) ($primary['password_hash'] ?? '')) !== '';
    }

    /**
     * @return array<string, array{label:string,description:string,category:string}>
     */
    private function setupProvidersByCategory(string $category): array
    {
        $providers = IntegrationRegistry::providers();
        $filtered = [];

        foreach ($providers as $key => $provider) {
            if (($provider['category'] ?? '') !== $category) {
                continue;
            }

            $filtered[$key] = [
                'label' => (string) ($provider['label'] ?? $key),
                'description' => (string) ($provider['description'] ?? ''),
                'category' => (string) ($provider['category'] ?? ''),
            ];
        }

        return $filtered;
    }

    private function testExternalNpmConnection(string $baseUrl, string $identity, string $secret): ?string
    {
        $url = rtrim($baseUrl, '/') . '/api/tokens';

        try {
            $result = $this->httpClient->post($url, [
                'identity' => $identity,
                'secret' => $secret,
            ]);
        } catch (RuntimeException $e) {
            return $e->getMessage();
        }

        if ((int) ($result['status'] ?? 0) !== 200) {
            return 'Unexpected HTTP status: ' . (int) ($result['status'] ?? 0);
        }

        $body = $result['body'] ?? null;
        if (!is_array($body) || trim((string) ($body['token'] ?? '')) === '') {
            return 'Authentication token was not returned by NPM.';
        }

        return null;
    }

    private function detectServerIp(): ?string
    {
        // Prefer the outbound source address first.
        $output = [];
        @exec('ip route get 1.1.1.1 2>/dev/null', $output);
        foreach ($output as $line) {
            if (preg_match('/\bsrc\s+([\d.]+)/', $line, $m)) {
                $ip = $m[1];
                if (
                    filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
                    && !in_array($ip, ['127.0.0.1', '0.0.0.0'], true)
                ) {
                    return $ip;
                }
            }
        }

        // Fallback to interface list.
        $output = [];
        @exec('hostname -I 2>/dev/null', $output);
        foreach ($output as $line) {
            foreach (explode(' ', trim($line)) as $candidate) {
                $candidate = trim($candidate);
                if (
                    $candidate !== ''
                    && filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
                    && !in_array($candidate, ['127.0.0.1', '0.0.0.0'], true)
                ) {
                    return $candidate;
                }
            }
        }

        // Last resort from hostname resolution.
        $resolvedHost = @gethostbyname((string) gethostname());
        if (
            is_string($resolvedHost)
            && filter_var($resolvedHost, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
            && !in_array($resolvedHost, ['127.0.0.1', '0.0.0.0'], true)
        ) {
            return $resolvedHost;
        }

        return null;
    }

    private function requestNpmToken(string $baseUrl, string $identity, string $secret): ?string
    {
        try {
            $result = $this->httpClient->post(rtrim($baseUrl, '/') . '/api/tokens', [
                'identity' => $identity,
                'secret' => $secret,
            ]);
        } catch (RuntimeException) {
            return null;
        }

        if ((int) ($result['status'] ?? 0) !== 200 || !is_array($result['body'] ?? null)) {
            return null;
        }

        $token = trim((string) ($result['body']['token'] ?? ''));

        return $token !== '' ? $token : null;
    }

    private function tryCreateBuiltinNpmSetupUser(string $baseUrl, string $identity, string $secret): bool
    {
        try {
            $result = $this->httpClient->post(rtrim($baseUrl, '/') . '/api/users', [
                'name' => 'Vhost Manager Admin',
                'nickname' => 'admin',
                'email' => $identity,
                'auth' => [
                    'type' => 'password',
                    'secret' => $secret,
                ],
            ]);
        } catch (RuntimeException) {
            return false;
        }

        return in_array((int) ($result['status'] ?? 0), [200, 201], true);
    }

    /**
     * @return array{identity:string,secret:string}
     */
    private function resolveSetupNpmServiceAccount(string $integrationName, string $baseUrl, string $adminIdentity, string $adminSecret): array
    {
        $cached = $this->getCachedSetupNpmServiceAccount($integrationName, $baseUrl);
        if ($cached !== null && $this->requestNpmToken($baseUrl, $cached['identity'], $cached['secret']) !== null) {
            return $cached;
        }

        $serviceAccount = $this->provisionNpmServiceAccount($baseUrl, $adminIdentity, $adminSecret);
        $this->cacheSetupNpmServiceAccount($integrationName, $baseUrl, $serviceAccount);

        return $serviceAccount;
    }

    /**
     * @return array{identity:string,secret:string}|null
     */
    private function getCachedSetupNpmServiceAccount(string $integrationName, string $baseUrl): ?array
    {
        $cache = $_SESSION['setup_npm_accounts'] ?? null;
        if (!is_array($cache)) {
            return null;
        }

        $key = $this->setupNpmAccountCacheKey($integrationName, $baseUrl);
        $account = $cache[$key] ?? null;
        if (!is_array($account)) {
            return null;
        }

        $identity = trim((string) ($account['identity'] ?? ''));
        $secret = trim((string) ($account['secret'] ?? ''));
        if ($identity === '' || $secret === '') {
            return null;
        }

        return [
            'identity' => $identity,
            'secret' => $secret,
        ];
    }

    /**
     * @param array{identity:string,secret:string} $account
     */
    private function cacheSetupNpmServiceAccount(string $integrationName, string $baseUrl, array $account): void
    {
        $cache = $_SESSION['setup_npm_accounts'] ?? [];
        if (!is_array($cache)) {
            $cache = [];
        }

        $cache[$this->setupNpmAccountCacheKey($integrationName, $baseUrl)] = [
            'identity' => $account['identity'],
            'secret' => $account['secret'],
            'cached_at' => date('c'),
        ];

        $_SESSION['setup_npm_accounts'] = $cache;
    }

    private function setupNpmAccountCacheKey(string $integrationName, string $baseUrl): string
    {
        return strtolower(trim($integrationName)) . '|' . strtolower(rtrim(trim($baseUrl), '/'));
    }

    /**
     * @return array{identity:string,secret:string}
     */
    private function provisionNpmServiceAccount(string $baseUrl, string $adminIdentity, string $adminSecret): array
    {
        $adminToken = $this->resolveNpmAdminToken($baseUrl, $adminIdentity, $adminSecret);

        $serviceIdentityBase = $this->buildNpmServiceIdentity($adminIdentity);
        $serviceIdentity = $serviceIdentityBase;
        $headers = ["Authorization: Bearer {$adminToken}"];

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $serviceSecret = bin2hex(random_bytes(32));
            $createUser = $this->createNpmUserWithFallback(
                rtrim($baseUrl, '/') . '/api/users',
                $headers,
                $serviceIdentity,
                $serviceSecret,
                false
            );

            if (!in_array((int) ($createUser['status'] ?? 0), [200, 201], true)) {
                if ($this->isNpmDuplicateUserError($createUser)) {
                    $serviceIdentity = $this->withNpmIdentitySuffix($serviceIdentityBase);
                    continue;
                }

                $body = json_encode($createUser['body'] ?? [], JSON_UNESCAPED_SLASHES);
                throw new RuntimeException('NPM service account could not be created: ' . $body);
            }

            if ($this->requestNpmToken($baseUrl, $serviceIdentity, $serviceSecret) === null) {
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
        try {
            $result = $this->httpClient->post(rtrim($baseUrl, '/') . '/api/tokens', [
                'identity' => $adminIdentity,
                'secret' => $adminSecret,
            ]);
        } catch (RuntimeException $e) {
            throw new RuntimeException('NPM admin authentication request failed: ' . $e->getMessage());
        }

        if ((int) ($result['status'] ?? 0) === 200 && is_array($result['body'] ?? null)) {
            $token = trim((string) ($result['body']['token'] ?? ''));
            if ($token !== '') {
                return $token;
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

        $status = (int) ($result['status'] ?? 0);
        $detail = $this->extractNpmErrorMessage($result['body'] ?? null);
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
