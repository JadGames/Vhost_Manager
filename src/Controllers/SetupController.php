<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Session;
use App\Security\Csrf;
use App\Services\HttpClient;
use App\Services\SettingsStore;
use RuntimeException;

final class SetupController extends BaseController
{
    public function __construct(
        Config $config,
        private readonly Csrf $csrf,
        private readonly SettingsStore $settingsStore,
        private readonly HttpClient $httpClient
    ) {
        parent::__construct($config);
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
            $passwordErrors = password_policy_errors($password);
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

        $this->redirect('setup-integration');
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
     * Show page 2 of setup: Proxy/Integration selection
     */
    public function showIntegration(): void
    {
        if ($this->isSetupComplete()) {
            $this->redirect(Session::isAuthenticated() ? 'dashboard' : 'login');
        }

        $pendingSetup = $_SESSION['setup_pending'] ?? null;
        if (!is_array($pendingSetup)) {
            $this->redirect('setup');
        }

        $npmForwardPort = (int) (getenv('VHM_NPM_FORWARD_PORT') ?: '80');
        $defaultBuiltinIdentity = strtolower(trim((string) ($pendingSetup['ADMIN_USER'] ?? 'admin@example.com')));
        $defaultBuiltinSecret = (string) ($_SESSION['setup_pending_admin_password'] ?? '');

        $this->render('auth/setup-integration.php', [
            'csrfToken' => $this->csrf->token(),
            'hasBuiltinNpm' => $this->hasBuiltinNpm(),
            'proxyMode' => $_SESSION['setup_pending_proxy_mode'] ?? ($this->hasBuiltinNpm() ? 'builtin_npm' : 'disabled'),
            'builtinNpmIdentity' => $_SESSION['setup_pending_builtin_npm_identity'] ?? $defaultBuiltinIdentity,
            'builtinNpmSecret' => $_SESSION['setup_pending_builtin_npm_secret'] ?? $defaultBuiltinSecret,
            'npmBaseUrl' => $_SESSION['setup_pending_npm_base_url'] ?? '',
            'npmIdentity' => $_SESSION['setup_pending_npm_identity'] ?? '',
            'npmSecret' => $_SESSION['setup_pending_npm_secret'] ?? '',
            'npmForwardHost' => $_SESSION['setup_pending_npm_forward_host'] ?? '',
            'npmForwardPort' => $_SESSION['setup_pending_npm_forward_port'] ?? (string) $npmForwardPort,
            'externalNpmTestError' => $this->consumeExternalNpmTestError(),
        ]);
    }

    /**
     * Process page 2: validate integrations, store in session
     */
    public function completeIntegration(): void
    {
        if ($this->isSetupComplete()) {
            $this->redirect('login');
        }

        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            Session::setFlash('error', 'Invalid CSRF token.');
            $this->redirect('setup-integration');
        }

        $pendingSetup = $_SESSION['setup_pending'] ?? null;
        if (!is_array($pendingSetup)) {
            $this->redirect('setup');
        }

        $proxyMode = trim((string) ($_POST['proxy_mode'] ?? ($this->hasBuiltinNpm() ? 'builtin_npm' : 'disabled')));

        if (!in_array($proxyMode, ['builtin_npm', 'external_npm', 'disabled'], true)) {
            Session::setFlash('error', 'Invalid proxy mode selected.');
            $this->redirect('setup-integration');
        }

        if (!$this->hasBuiltinNpm() && $proxyMode === 'builtin_npm') {
            Session::setFlash('error', 'Built-in NPM is not available in this deployment.');
            $this->redirect('setup-integration');
        }

        $_SESSION['setup_pending_proxy_mode'] = $proxyMode;

        // Handle built-in NPM credentials if selected
        if ($proxyMode === 'builtin_npm') {
            $npmIdentity = strtolower(trim((string) ($_POST['builtin_npm_identity'] ?? '')));
            $npmSecret = (string) ($_POST['builtin_npm_secret'] ?? '');
            if ($npmSecret === '') {
                $npmSecret = (string) ($_SESSION['setup_pending_admin_password'] ?? '');
            }

            if ($npmIdentity === '' || !filter_var($npmIdentity, FILTER_VALIDATE_EMAIL)) {
                Session::setFlash('error', 'NPM admin email must be a valid email address.');
                $this->redirect('setup-integration');
            }

            if (strlen($npmSecret) < 8) {
                Session::setFlash('error', 'NPM admin password must be at least 8 characters long.');
                $this->redirect('setup-integration');
            }

            $_SESSION['setup_pending_builtin_npm_identity'] = $npmIdentity;
            $_SESSION['setup_pending_builtin_npm_secret'] = $npmSecret;
        }

        // Handle external NPM settings if selected
        if ($proxyMode === 'external_npm') {
            $npmBaseUrlScheme = strtolower(trim((string) ($_POST['npm_base_url_scheme'] ?? 'http')));
            $npmBaseUrlHost = trim((string) ($_POST['npm_base_url_host'] ?? ''));
            $npmBaseUrlPort = (int) ($_POST['npm_base_url_port'] ?? 81);
            $npmBaseUrl = $npmBaseUrlScheme . '://' . $npmBaseUrlHost . ':' . $npmBaseUrlPort;

            $npmIdentity = strtolower(trim((string) ($_POST['npm_identity'] ?? '')));
            $npmSecret = trim((string) ($_POST['npm_secret'] ?? ''));
            $npmForwardHost = trim((string) ($_POST['npm_forward_host'] ?? ''));
            $npmForwardPort = (int) ($_POST['npm_forward_port'] ?? 80);

            if ($npmBaseUrlHost === '' || filter_var($npmBaseUrlHost, FILTER_VALIDATE_IP) === false && !preg_match('/^[a-z0-9.-]+$/i', $npmBaseUrlHost)) {
                Session::setFlash('error', 'NPM host must be a valid IP address or hostname.');
                $this->redirect('setup-integration');
            }

            if ($npmBaseUrlPort < 1 || $npmBaseUrlPort > 65535) {
                Session::setFlash('error', 'NPM port must be between 1 and 65535.');
                $this->redirect('setup-integration');
            }

            if ($npmIdentity === '' || $npmSecret === '') {
                Session::setFlash('error', 'NPM admin email and password are required one time to provision the VHM account.');
                $this->redirect('setup-integration');
            }

            if (filter_var($npmIdentity, FILTER_VALIDATE_EMAIL) === false) {
                Session::setFlash('error', 'NPM identity must be a valid email address.');
                $this->redirect('setup-integration');
            }

            if ($npmForwardHost === '') {
                Session::setFlash('error', 'Forward address is required.');
                $this->redirect('setup-integration');
            }

            if ($npmForwardPort < 1 || $npmForwardPort > 65535) {
                Session::setFlash('error', 'Forward port must be between 1 and 65535.');
                $this->redirect('setup-integration');
            }

            $testError = $this->testExternalNpmConnection($npmBaseUrl, $npmIdentity, $npmSecret);
            if ($testError !== null) {
                $_SESSION['setup_external_npm_test_error'] = $testError;
                Session::setFlash('error', 'Failed to connect to external NPM. Check the details and try again.');
                $this->redirect('setup-integration');
            }

            try {
                $serviceAccount = $this->provisionNpmServiceAccount($npmBaseUrl, $npmIdentity, $npmSecret);
            } catch (RuntimeException $e) {
                $_SESSION['setup_external_npm_test_error'] = $e->getMessage();
                Session::setFlash('error', 'Connected to NPM but failed to provision the VHM account.');
                $this->redirect('setup-integration');
            }

            unset($_SESSION['setup_external_npm_test_error']);

            $_SESSION['setup_pending_npm_base_url'] = $npmBaseUrl;
            $_SESSION['setup_pending_npm_identity'] = $serviceAccount['identity'];
            $_SESSION['setup_pending_npm_secret'] = $serviceAccount['secret'];
            $_SESSION['setup_pending_npm_forward_host'] = $npmForwardHost;
            $_SESSION['setup_pending_npm_forward_port'] = (string) $npmForwardPort;
        }

        $this->redirect('setup-confirm');
    }

    /**
     * Show page 3 of setup: Confirmation review
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

        $proxyMode = $_SESSION['setup_pending_proxy_mode'] ?? 'disabled';

        $summary = [
            'admin_email' => $pendingSetup['ADMIN_USER'] ?? '',
            'admin_full_name' => $pendingSetup['ADMIN_FULL_NAME'] ?? '',
            'admin_password' => $_SESSION['setup_pending_admin_password'] ?? '',
            'app_url' => $pendingSetup['APP_URL'] ?? '',
            'app_https' => $pendingSetup['APP_HTTPS'] === 'true',
            'allowed_docroot_bases' => $pendingSetup['ALLOWED_DOCROOT_BASES'] ?? '',
            'default_docroot_base' => $pendingSetup['DEFAULT_DOCROOT_BASE'] ?? '',
            'proxy_mode' => $proxyMode,
                        'builtin_npm_identity' => $_SESSION['setup_pending_builtin_npm_identity'] ?? '',
                        'builtin_npm_secret' => $_SESSION['setup_pending_builtin_npm_secret'] ?? '',
            'npm_base_url' => $_SESSION['setup_pending_npm_base_url'] ?? '',
            'npm_identity' => $_SESSION['setup_pending_npm_identity'] ?? '',
            'npm_forward_host' => $_SESSION['setup_pending_npm_forward_host'] ?? '',
            'npm_forward_port' => $_SESSION['setup_pending_npm_forward_port'] ?? '',
        ];

        $this->render('auth/setup-confirm.php', [
            'csrfToken' => $this->csrf->token(),
            'summary' => $summary,
        ]);
    }

    /**
     * Process page 3: Save all settings and auto-login
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

        $proxyMode = $_SESSION['setup_pending_proxy_mode'] ?? 'disabled';
        $settings = $pendingSetup;
        $settings['PROXY_MODE'] = $proxyMode;
        if (trim((string) ($settings['ADMIN_CREATED_AT'] ?? '')) === '') {
            $settings['ADMIN_CREATED_AT'] = date('c');
        }
        if (trim((string) ($settings['ADMIN_FULL_NAME'] ?? '')) === '') {
            $settings['ADMIN_FULL_NAME'] = (string) ($settings['ADMIN_USER'] ?? 'Admin User');
        }

        if ($proxyMode === 'builtin_npm') {
            $builtinIdentity = (string) ($_SESSION['setup_pending_builtin_npm_identity'] ?? 'admin@example.com');
            $builtinSecret = (string) ($_SESSION['setup_pending_builtin_npm_secret'] ?? 'changeme');

            $provisionError = $this->provisionBuiltinNpmCredentials($builtinIdentity, $builtinSecret);
            if ($provisionError !== null) {
                Session::setFlash('error', $provisionError);
                $this->redirect('setup-confirm');
            }

            try {
                $serviceAccount = $this->provisionNpmServiceAccount('http://npm:81', $builtinIdentity, $builtinSecret);
            } catch (RuntimeException $e) {
                Session::setFlash('error', 'Built-in NPM connected, but VHM account provisioning failed: ' . $e->getMessage());
                $this->redirect('setup-confirm');
            }

            $settings['NPM_ENABLED'] = 'true';
            $settings['NPM_BASE_URL'] = 'http://npm:81';
            $settings['NPM_IDENTITY'] = $serviceAccount['identity'];
            $settings['NPM_SECRET'] = $serviceAccount['secret'];
            $settings['NPM_FORWARD_HOST'] = 'vhost-manager';
            $settings['NPM_FORWARD_PORT'] = '80';
        } elseif ($proxyMode === 'external_npm') {
            $settings['NPM_ENABLED'] = 'true';
            $settings['NPM_BASE_URL'] = $_SESSION['setup_pending_npm_base_url'] ?? '';
            $settings['NPM_IDENTITY'] = $_SESSION['setup_pending_npm_identity'] ?? '';
            $settings['NPM_SECRET'] = $_SESSION['setup_pending_npm_secret'] ?? '';
            $settings['NPM_FORWARD_HOST'] = $_SESSION['setup_pending_npm_forward_host'] ?? '';
            $settings['NPM_FORWARD_PORT'] = $_SESSION['setup_pending_npm_forward_port'] ?? '';
        } else {
            $settings['NPM_ENABLED'] = 'false';
        }

        try {
            $this->settingsStore->setMany($settings);
        } catch (RuntimeException $e) {
            Session::setFlash('error', $e->getMessage());
            $this->redirect('setup-confirm');
        }

        // Clean up setup session data
        unset($_SESSION['setup_pending']);
        unset($_SESSION['setup_pending_admin_password']);
        unset($_SESSION['setup_pending_proxy_mode']);
            unset($_SESSION['setup_pending_builtin_npm_identity']);
            unset($_SESSION['setup_pending_builtin_npm_secret']);
        unset($_SESSION['setup_pending_npm_base_url']);
        unset($_SESSION['setup_pending_npm_identity']);
        unset($_SESSION['setup_pending_npm_secret']);
        unset($_SESSION['setup_pending_npm_forward_host']);
        unset($_SESSION['setup_pending_npm_forward_port']);

        // Auto-login the user
        $adminEmail = strtolower(trim((string) ($settings['ADMIN_USER'] ?? '')));
        Session::login($adminEmail);

        Session::setFlash('success', 'Setup complete and you are now logged in!');
        $this->redirect('overview');
    }

    private function isSetupComplete(): bool
    {
        $user = trim((string) $this->config->get('ADMIN_USER', ''));
        $hash = trim((string) $this->config->get('ADMIN_PASSWORD_HASH', ''));

        return $user !== '' && $hash !== '';
    }

    private function hasBuiltinNpm(): bool
    {
        return filter_var(getenv('VHM_BUILTIN_NPM_AVAILABLE') ?: 'false', FILTER_VALIDATE_BOOLEAN);
    }

    private function consumeExternalNpmTestError(): ?string
    {
        $error = $_SESSION['setup_external_npm_test_error'] ?? null;
        unset($_SESSION['setup_external_npm_test_error']);

        return is_string($error) && $error !== '' ? $error : null;
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

    private function provisionBuiltinNpmCredentials(string $identity, string $secret): ?string
    {
        $baseUrl = 'http://npm:81';
        $bootstrapIdentity = trim((string) (getenv('VHM_NPM_BOOTSTRAP_IDENTITY') ?: 'admin@example.com'));
        $bootstrapSecret = (string) (getenv('VHM_NPM_BOOTSTRAP_SECRET') ?: 'changeme');

        // If the target credentials already work, there is nothing to change.
        if ($this->requestNpmToken($baseUrl, $identity, $secret) !== null) {
            return null;
        }

        // Fresh NPM without any user can accept unauthenticated setup user creation.
        if ($this->tryCreateBuiltinNpmSetupUser($baseUrl, $identity, $secret)) {
            if ($this->requestNpmToken($baseUrl, $identity, $secret) !== null) {
                return null;
            }

            return 'Built-in NPM setup user was created, but login verification failed afterwards.';
        }

        // Fresh NPM installs usually start with the configured bootstrap login.
        $bootstrapToken = $this->requestNpmToken($baseUrl, $bootstrapIdentity, $bootstrapSecret);
        if ($bootstrapToken === null) {
            return 'Built-in NPM is reachable, but Vhost Manager could not authenticate to apply the selected credentials. If this is an existing NPM instance, enter its current admin login and password in setup first.';
        }

        $authHeader = ["Authorization: Bearer {$bootstrapToken}"];

        try {
            $meResponse = $this->httpClient->get(rtrim($baseUrl, '/') . '/api/users/me', $authHeader);
            if ((int) ($meResponse['status'] ?? 0) !== 200 || !is_array($meResponse['body'] ?? null)) {
                return 'Built-in NPM user profile could not be loaded while applying credentials.';
            }

            $me = $meResponse['body'];
            $name = trim((string) ($me['name'] ?? ''));
            $nickname = trim((string) ($me['nickname'] ?? ''));

            $updateUserResponse = $this->httpClient->put(
                rtrim($baseUrl, '/') . '/api/users/me',
                [
                    'name' => $name !== '' ? $name : 'Vhost Manager Admin',
                    'nickname' => $nickname !== '' ? $nickname : 'admin',
                    'email' => $identity,
                ],
                $authHeader
            );

            if (!in_array((int) ($updateUserResponse['status'] ?? 0), [200, 201], true)) {
                return 'Built-in NPM admin email could not be updated during setup.';
            }

            $updatePasswordResponse = $this->httpClient->put(
                rtrim($baseUrl, '/') . '/api/users/me/auth',
                [
                    'type' => 'password',
                    'current' => $bootstrapSecret,
                    'secret' => $secret,
                ],
                $authHeader
            );

            if (!in_array((int) ($updatePasswordResponse['status'] ?? 0), [200, 201], true)) {
                return 'Built-in NPM admin password could not be updated during setup.';
            }
        } catch (RuntimeException $e) {
            return 'Built-in NPM credentials could not be applied: ' . $e->getMessage();
        }

        if ($this->requestNpmToken($baseUrl, $identity, $secret) === null) {
            return 'Built-in NPM credentials were updated, but login verification failed afterwards. Please verify NPM admin credentials manually.';
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
        $localPart = strtolower(trim((string) strtok($email, '@')));
        $localPart = preg_replace('/[^a-z0-9._-]/', '', $localPart);
        $localPart = is_string($localPart) ? trim($localPart, '._-') : '';

        if ($localPart === '') {
            $localPart = 'vhm';
        }

        return $localPart . '@vhost-manager.npm';
    }
}
