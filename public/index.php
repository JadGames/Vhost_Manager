<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\LogsController;
use App\Controllers\SetupController;
use App\Controllers\SettingsController;
use App\Controllers\VhostController;
use App\Core\AppDefaults;
use App\Core\Config;
use App\Core\Session;
use App\Security\Csrf;
use App\Security\RateLimiter;
use App\Services\AuthService;
use App\Services\ApacheModulesService;
use App\Services\CloudflareService;
use App\Services\HttpClient;
use App\Services\Logger;
use App\Services\NpmService;
use App\Services\SettingsStore;
use App\Services\VhostRepository;
use App\Services\VhostService;

require_once __DIR__ . '/../src/Core/helpers.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../src/';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});

$defaultSettings = AppDefaults::values();
$settingsStore = new SettingsStore((string) $defaultSettings['SETTINGS_DB_FILE']);
$settingsStore->initialize();

if ($settingsStore->isEmpty()) {
    $settingsStore->setMany($defaultSettings);
}

$currentSettings = $settingsStore->all();
$composeDocrootBases = parse_docroot_bases((string) ($defaultSettings['ALLOWED_DOCROOT_BASES'] ?? '/var/www'));
$storedDocrootBases = parse_docroot_bases((string) ($currentSettings['ALLOWED_DOCROOT_BASES'] ?? ''));
$mergedDocrootBases = merge_docroot_bases($storedDocrootBases, $composeDocrootBases);
if ($mergedDocrootBases === []) {
    $mergedDocrootBases = ['/var/www'];
}

$currentSettings['ALLOWED_DOCROOT_BASES'] = implode(',', $mergedDocrootBases);

$composeDefaultDocroot = trim((string) ($defaultSettings['DEFAULT_DOCROOT_BASE'] ?? '/var/www'));
$storedDefaultDocroot = trim((string) ($currentSettings['DEFAULT_DOCROOT_BASE'] ?? ''));
$effectiveDefaultDocroot = $storedDefaultDocroot !== '' ? $storedDefaultDocroot : $composeDefaultDocroot;
if (!in_array($effectiveDefaultDocroot, $mergedDocrootBases, true)) {
    $effectiveDefaultDocroot = in_array($composeDefaultDocroot, $mergedDocrootBases, true)
        ? $composeDefaultDocroot
        : $mergedDocrootBases[0];
}

$currentSettings['DEFAULT_DOCROOT_BASE'] = $effectiveDefaultDocroot;

// Version should track the running image build metadata, not persisted UI settings.
$currentSettings['APP_VERSION'] = (string) ($defaultSettings['APP_VERSION'] ?? 'dev');

$missingDefaults = [];
foreach ($defaultSettings as $key => $value) {
    if (!array_key_exists($key, $currentSettings)) {
        $missingDefaults[$key] = $value;
    }
}

if ($missingDefaults !== []) {
    $settingsStore->setMany($missingDefaults);
    $currentSettings = array_merge($currentSettings, $missingDefaults);
}

$legacyIntegrationKeys = [
    'CF_ENABLED',
    'CF_API_TOKEN',
    'CF_ZONE_ID',
    'CF_RECORD_IP',
    'CF_PROXIED',
    'CF_TTL',
    'NPM_ENABLED',
    'NPM_BASE_URL',
    'NPM_IDENTITY',
    'NPM_SECRET',
    'NPM_FORWARD_HOST',
    'NPM_FORWARD_PORT',
    'NPM_SSL_ENABLED',
    'NPM_CERTIFICATE_ID',
    'NPM_SSL_FORCED',
    'NPM_HTTP2_SUPPORT',
    'NPM_HSTS_ENABLED',
    'NPM_HSTS_SUBDOMAINS',
    'PROXY_MODE',
    'INTEGRATIONS_JSON',
];

$settingsStore->deleteKeys($legacyIntegrationKeys);
foreach ($legacyIntegrationKeys as $legacyKey) {
    unset($currentSettings[$legacyKey]);
}
unset($legacyKey);

$config = new Config(array_merge($defaultSettings, $currentSettings));

$timezone = (string) $config->get('APP_TIMEZONE', 'Australia/Brisbane');
if (@date_default_timezone_set($timezone) === false) {
    date_default_timezone_set('UTC');
}

Session::start($config);

$cspNonce = base64_encode(random_bytes(16));
$_SERVER['CSP_NONCE'] = $cspNonce;

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'; script-src 'self' 'nonce-{$cspNonce}'; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com; img-src 'self' data:");

$logger = new Logger($config->get('LOG_FILE', __DIR__ . '/../storage/logs/app.log'));
$csrf = new Csrf();
$rateLimiter = new RateLimiter(
    $config->get('LOGIN_ATTEMPTS_FILE', __DIR__ . '/../storage/data/login_attempts.json'),
    5,
    900,
    900
);
$authService = new AuthService($rateLimiter, $logger, $settingsStore);
$vhostRepository = new VhostRepository(
    $config->get('SETTINGS_DB_FILE', __DIR__ . '/../storage/data/settings.sqlite'),
    $config->get('MANAGED_VHOSTS_FILE', __DIR__ . '/../storage/data/vhosts.json')
);
$vhostRepository->initialize();

$httpClient = new HttpClient($config->getBool('CURL_VERIFY_SSL', true));

// Wire NpmService and CloudflareService from integrations table
$allIntegrations = $settingsStore->integrationGetAll();
$npmIntegration = null;
$cfIntegration = null;
foreach ($allIntegrations as $_int) {
    if ($npmIntegration === null && ($_int['provider'] ?? '') === 'npm') {
        $npmIntegration = $_int;
    }
    if ($cfIntegration === null && ($_int['provider'] ?? '') === 'cloudflare') {
        $cfIntegration = $_int;
    }
}
unset($_int);

$npm = null;
if ($npmIntegration !== null) {
    $ns = $npmIntegration['settings'] ?? [];
    $npmConfig = new Config(array_merge($currentSettings, [
        'NPM_ENABLED'        => 'true',
        'NPM_BASE_URL'       => (string) ($ns['base_url']      ?? 'http://localhost:81'),
        'NPM_IDENTITY'       => (string) ($ns['identity']      ?? ''),
        'NPM_SECRET'         => (string) ($ns['secret']        ?? ''),
        'NPM_FORWARD_HOST'   => (string) ($ns['forward_host']  ?? '127.0.0.1'),
        'NPM_FORWARD_PORT'   => (string) ($ns['forward_port']  ?? '80'),
        'NPM_SSL_ENABLED'    => 'false',
        'NPM_CERTIFICATE_ID' => '0',
        'NPM_SSL_FORCED'     => 'false',
        'NPM_HTTP2_SUPPORT'  => 'false',
        'NPM_HSTS_ENABLED'   => 'false',
        'NPM_HSTS_SUBDOMAINS'=> 'false',
    ]));
    $npm = new NpmService($npmConfig, $httpClient, $logger);
}

$cloudflare = null;
if ($cfIntegration !== null) {
    $cfConfig = new Config(array_merge($currentSettings, ['CF_ENABLED' => 'true']));
    $cloudflare = new CloudflareService($cfConfig, $httpClient, $logger, $settingsStore->domainGetCfMappings());
}

$vhostService = new VhostService($config, $logger, $vhostRepository, $cloudflare, $npm);
 $apacheModulesService = new ApacheModulesService($config);

$authController = new AuthController($config, $authService, $csrf, $settingsStore);
$setupController = new SetupController($config, $csrf, $settingsStore, $httpClient);
$vhostController = new VhostController($config, $csrf, $vhostService, $apacheModulesService, $settingsStore);
$settingsController = new SettingsController($config, $csrf, $settingsStore, $apacheModulesService, $httpClient);
$logsController = new LogsController($config, $csrf, $settingsStore);

$route = $_GET['route'] ?? 'overview';
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

$primaryAdmin = $settingsStore->userGetPrimary();
$isSetupComplete = $primaryAdmin !== null
    && trim((string) ($primaryAdmin['email'] ?? '')) !== ''
    && trim((string) ($primaryAdmin['password_hash'] ?? '')) !== '';

$setupRoutes = ['setup', 'setup-proxy', 'setup-dns', 'setup-confirm'];

if (!$isSetupComplete && !in_array($route, $setupRoutes, true)) {
    header('Location: /?route=setup');
    exit;
}

if ($isSetupComplete && in_array($route, $setupRoutes, true)) {
    header('Location: /?route=login');
    exit;
}

try {
    switch ($route) {
        case 'setup':
            if ($method === 'POST') {
                $setupController->complete();
                break;
            }
            $setupController->show();
            break;

        case 'setup-proxy':
            if ($method === 'POST') {
                $setupController->completeProxy();
                break;
            }
            $setupController->showProxy();
            break;

        case 'setup-dns':
            if ($method === 'POST') {
                $setupController->completeDns();
                break;
            }
            $setupController->showDns();
            break;

        case 'setup-confirm':
            if ($method === 'POST') {
                $setupController->completeConfirm();
                break;
            }
            $setupController->showConfirm();
            break;

        case 'login':
            if ($method === 'POST') {
                $authController->login();
                break;
            }
            $authController->showLogin();
            break;

        case 'logout':
            if ($method === 'POST') {
                $authController->logout();
                break;
            }
            header('Location: /?route=dashboard');
            exit;
            break;

        case 'create-vhost':
            Session::requireAuth();
            if ($method === 'POST') {
                $vhostController->create();
                break;
            }
            $vhostController->showCreateForm();
            break;

        case 'delete-vhost':
            Session::requireAuth();
            if ($method === 'POST') {
                $vhostController->delete();
                break;
            }
            $vhostController->showDeleteConfirm();
            break;

        case 'edit-vhost':
            Session::requireAuth();
            if ($method === 'POST') {
                $vhostController->edit();
                break;
            }
            $vhostController->showEditForm();
            break;

        case 'overview':
            Session::requireAuth();
            $vhostController->showOverview();
            break;

        case 'domains':
            Session::requireAuth();
            $vhostController->showDomains();
            break;

        case 'domains-save':
            Session::requireAuth();
            if ($method === 'POST') {
                $vhostController->saveDomain();
                break;
            }
            header('Location: /?route=domains');
            exit;
            break;

        case 'vhosts':
            Session::requireAuth();
            $vhostController->dashboard();
            break;

        case 'settings':
            Session::requireAuth();
            $settingsController->show();
            break;

        case 'settings-integrations':
            Session::requireAuth();
            $settingsController->showIntegrations();
            break;

        case 'settings-integrations-action':
            Session::requireAuth();
            if ($method === 'POST') {
                $settingsController->integrationsAction();
                break;
            }
            header('Location: /?route=settings-integrations');
            exit;
            break;

        case 'settings-integrations-test':
            Session::requireAuth();
            if ($method === 'POST') {
                $settingsController->integrationsTestAction();
                break;
            }
            header('Location: /?route=settings-integrations');
            exit;
            break;

        case 'settings-integrations-cloudflare-domain-test':
            Session::requireAuth();
            if ($method === 'POST') {
                $settingsController->integrationsCloudflareDomainTestAction();
                break;
            }
            header('Location: /?route=settings-integrations');
            exit;
            break;

        case 'settings-integrations-server-ip':
            Session::requireAuth();
            if ($method === 'POST') {
                $settingsController->integrationsServerIpAction();
                break;
            }
            header('Location: /?route=settings-integrations');
            exit;
            break;

        case 'settings-integrations-npm-bootstrap':
            Session::requireAuth();
            if ($method === 'POST') {
                $settingsController->integrationsNpmBootstrapAction();
                break;
            }
            header('Location: /?route=settings-integrations');
            exit;
            break;

        case 'settings-integrations-enable-cloudflare':
            Session::requireAuth();
            if ($method === 'POST') {
                $settingsController->integrationsEnableCloudflareAction();
                break;
            }
            header('Location: /?route=settings-integrations');
            exit;
            break;

        case 'settings-save-general':
            Session::requireAuth();
            if ($method === 'POST') {
                $settingsController->saveGeneral();
                break;
            }
            header('Location: /?route=settings');
            exit;
            break;

        case 'settings-users':
            Session::requireAuth();
            $settingsController->showUsers();
            break;

        case 'settings-users-action':
            Session::requireAuth();
            if ($method === 'POST') {
                $settingsController->usersAction();
                break;
            }
            header('Location: /?route=settings-users');
            exit;
            break;

        case 'settings-cloudflare':
            Session::requireAuth();
            $settingsController->showCloudflare();
            break;

        case 'settings-cloudflare-save':
            Session::requireAuth();
            if ($method === 'POST') {
                $settingsController->saveCloudflare();
                break;
            }
            header('Location: /?route=settings-cloudflare');
            exit;
            break;

        case 'settings-cloudflare-domains':
            Session::requireAuth();
            $settingsController->showCloudflareDomains();
            break;

        case 'settings-cloudflare-domains-action':
            Session::requireAuth();
            if ($method === 'POST') {
                $settingsController->cloudflareDomainsAction();
                break;
            }
            header('Location: /?route=settings-cloudflare-domains');
            exit;
            break;

        case 'settings-npm':
            Session::requireAuth();
            $settingsController->showNpm();
            break;

        case 'settings-npm-save':
            Session::requireAuth();
            if ($method === 'POST') {
                $settingsController->saveNpm();
                break;
            }
            header('Location: /?route=settings-npm');
            exit;
            break;

        case 'settings-docroot-detection-action':
            Session::requireAuth();
            if ($method === 'POST') {
                $settingsController->docrootDetectionAction();
                break;
            }
            header('Location: /?route=dashboard');
            exit;
            break;

        case 'settings-npm-ssl':
            Session::requireAuth();
            $settingsController->showNpmSsl();
            break;

        case 'settings-apache-modules':
            Session::requireAuth();
            $settingsController->showApacheModules();
            break;

        case 'settings-apache-modules-action':
            Session::requireAuth();
            if ($method === 'POST') {
                $settingsController->apacheModulesAction();
                break;
            }
            header('Location: /?route=settings-apache-modules');
            exit;
            break;

        case 'settings-npm-ssl-save':
            Session::requireAuth();
            if ($method === 'POST') {
                $settingsController->saveNpmSsl();
                break;
            }
            header('Location: /?route=settings-npm-ssl');
            exit;
            break;

        case 'settings-change-password':
            Session::requireAuth();
            if ($method === 'POST') {
                $settingsController->changePassword();
                break;
            }
            header('Location: /?route=settings');
            exit;
            break;

        case 'logs':
            Session::requireAuth();
            $logsController->show();
            break;

        case 'logs-clear':
            Session::requireAuth();
            if ($method === 'POST') {
                $logsController->clear();
                break;
            }
            header('Location: /?route=logs');
            exit;
            break;

        case 'dashboard':
        default:
            Session::requireAuth();
            $vhostController->dashboard();
            break;
    }
} catch (Throwable $e) {
    $logger->error('Unhandled exception', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);

    http_response_code(500);
    echo 'Internal Server Error';
}
