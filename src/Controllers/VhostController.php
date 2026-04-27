<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Session;
use App\Security\Csrf;
use App\Services\ApacheModulesService;
use App\Services\VhostService;
use RuntimeException;

final class VhostController extends BaseController
{
    public function __construct(
        Config $config,
        private readonly Csrf $csrf,
        private readonly VhostService $service,
        private readonly ApacheModulesService $apacheModules
    ) {
        parent::__construct($config);
    }

    public function showOverview(): void
    {
        $vhosts = array_values($this->service->listManaged());
        usort($vhosts, static function (array $left, array $right): int {
            $leftTimestamp = (string) ($left['updated_at'] ?? $left['created_at'] ?? '');
            $rightTimestamp = (string) ($right['updated_at'] ?? $right['created_at'] ?? '');

            return strcmp($rightTimestamp, $leftTimestamp);
        });

        $activeModuleCount = 0;
        try {
            $activeModuleCount = count(array_filter(
                $this->apacheModules->listModules(),
                static fn (array $module): bool => !empty($module['enabled'])
            ));
        } catch (RuntimeException) {
            $activeModuleCount = 0;
        }

        $this->render('overview.php', [
            'vhostCount' => count($vhosts),
            'integrationCount' => $this->integrationCountFromConfig(),
            'userCount' => $this->additionalUsersCountFromConfig(),
            'activeModuleCount' => $activeModuleCount,
            'recentVhosts' => $vhosts,
        ]);
    }

    public function showDomains(): void
    {
        $this->render('domains/index.php');
    }

    public function dashboard(): void
    {
        $this->render('vhosts/dashboard.php', [
            'vhosts' => $this->service->listManaged(),
            'allowedDocrootBases' => $this->allowedDocrootBases(),
            'csrfToken' => $this->csrf->token(),
        ]);
    }

    public function showCreateForm(): void
    {
        $this->render('vhosts/create.php', [
            'csrfToken'      => $this->csrf->token(),
            'defaultBase'    => $this->config->get('DEFAULT_DOCROOT_BASE', '/var/www'),
            'allowedDocrootBases' => $this->allowedDocrootBases(),
            'baseDomain'     => strtolower(trim((string) $this->config->get('VHOST_BASE_DOMAIN', ''))),
            'cfEnabled'      => $this->config->getBool('CF_ENABLED', false),
            'npmEnabled'     => $this->config->getBool('NPM_ENABLED', false),
            'cfRecordIp'     => $this->config->get('CF_RECORD_IP', ''),
            'npmForwardHost' => $this->config->get('NPM_FORWARD_HOST', '127.0.0.1'),
            'npmForwardPort' => $this->config->get('NPM_FORWARD_PORT', 80),
            'npmSslEnabled'  => $this->config->getBool('NPM_SSL_ENABLED', false),
            'npmCertId'      => (int) $this->config->get('NPM_CERTIFICATE_ID', 0),
            'npmSslForced'   => $this->config->getBool('NPM_SSL_FORCED', false),
            'npmHttp2'       => $this->config->getBool('NPM_HTTP2_SUPPORT', false),
            'npmHsts'        => $this->config->getBool('NPM_HSTS_ENABLED', false),
            'npmHstsSubs'    => $this->config->getBool('NPM_HSTS_SUBDOMAINS', false),
            'npmCertificates' => $this->service->listNpmCertificates(),
        ]);
    }

    public function create(): void
    {
        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            Session::setFlash('error', 'Invalid CSRF token.');
            $this->redirect('create-vhost');
        }

        $baseDomain = strtolower(trim((string) $this->config->get('VHOST_BASE_DOMAIN', '')));
        $subdomain  = strtolower(trim((string) ($_POST['subdomain'] ?? '')));
        $domain     = (string) ($_POST['domain'] ?? '');

        if ($baseDomain !== '' && $subdomain !== '') {
            $domain = $this->buildFqdn($subdomain, $baseDomain);
        }

        $domain = strtolower(trim($domain));
        $docrootBase = trim((string) ($_POST['docroot_base'] ?? ''));
        $alias = $this->normalizeFolderName((string) ($_POST['alias'] ?? ''));
        $createCloudflare = $this->config->getBool('CF_ENABLED', false) && $this->postBool('create_cloudflare');
        $createNpm = $this->config->getBool('NPM_ENABLED', false) && $this->postBool('create_npm');
        $npmOptions = [
            'ssl_enabled'     => $this->postBool('npm_ssl_enabled'),
            'certificate_id'  => (int) ($_POST['npm_certificate_id'] ?? 0),
            'ssl_forced'      => $this->postBool('npm_ssl_forced'),
            'http2_support'   => $this->postBool('npm_http2_support'),
            'hsts_enabled'    => $this->postBool('npm_hsts_enabled'),
            'hsts_subdomains' => $this->postBool('npm_hsts_subdomains'),
        ];

        if (!$createNpm) {
            $npmOptions = [
                'ssl_enabled' => false,
                'certificate_id' => 0,
                'ssl_forced' => false,
                'http2_support' => false,
                'hsts_enabled' => false,
                'hsts_subdomains' => false,
            ];
        }

        $docroot = $this->buildDocroot($docrootBase, $alias !== '' ? $alias : $domain);

        try {
            $created = $this->service->create(
                $domain,
                $docroot,
                (string) ($_SESSION['username'] ?? 'unknown'),
                $npmOptions,
                $alias,
                $createCloudflare,
                $createNpm
            );

            $parts = ['Virtual host created successfully.'];
            if ($this->config->getBool('CF_ENABLED', false)) {
                if ($createCloudflare) {
                    $parts[] = !empty($created['cf_record_id'])
                        ? 'Cloudflare DNS record created.'
                        : 'Cloudflare DNS was requested but no record ID was returned.';
                } else {
                    $parts[] = 'Cloudflare DNS not requested for this vhost.';
                }
            }

            if ($this->config->getBool('NPM_ENABLED', false)) {
                if ($createNpm) {
                    $parts[] = !empty($created['npm_proxy_id'])
                        ? 'NPM proxy host created.'
                        : 'NPM was requested but no proxy host ID was returned.';
                } else {
                    $parts[] = 'NPM host creation was skipped.';
                }
            }

            Session::setFlash('success', implode(' ', $parts));
            $this->redirect('dashboard');
        } catch (RuntimeException $e) {
            Session::setFlash('error', $e->getMessage());
            $this->redirect('create-vhost');
        }
    }

    public function showDeleteConfirm(): void
    {
        $domain = strtolower(trim((string) ($_GET['domain'] ?? '')));
        if ($domain === '') {
            Session::setFlash('error', 'Missing domain.');
            $this->redirect('dashboard');
        }

        $vhosts = $this->service->listManaged();
        $entry = $vhosts[$domain] ?? null;
        if ($entry === null) {
            Session::setFlash('error', 'Virtual host not found.');
            $this->redirect('dashboard');
        }

        $this->render('vhosts/delete.php', [
            'csrfToken' => $this->csrf->token(),
            'entry' => $entry,
        ]);
    }

    public function showEditForm(): void
    {
        $domain = strtolower(trim((string) ($_GET['domain'] ?? '')));
        if ($domain === '') {
            Session::setFlash('error', 'Missing domain.');
            $this->redirect('dashboard');
        }

        $entry = $this->service->getManaged($domain);
        if ($entry === null) {
            Session::setFlash('error', 'Virtual host not found.');
            $this->redirect('dashboard');
        }

        $this->render('vhosts/edit.php', [
            'csrfToken' => $this->csrf->token(),
            'entry' => $entry,
            'cfEnabled' => $this->config->getBool('CF_ENABLED', false),
            'npmEnabled' => $this->config->getBool('NPM_ENABLED', false),
            'npmCertificates' => $this->service->listNpmCertificates(),
        ]);
    }

    public function edit(): void
    {
        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            Session::setFlash('error', 'Invalid CSRF token.');
            $this->redirect('dashboard');
        }

        $domain = (string) ($_POST['domain'] ?? '');
        $docroot = (string) ($_POST['docroot'] ?? '');
        $npmOptions = [
            'ssl_enabled'     => $this->postBool('npm_ssl_enabled'),
            'certificate_id'  => (int) ($_POST['npm_certificate_id'] ?? 0),
            'ssl_forced'      => $this->postBool('npm_ssl_forced'),
            'http2_support'   => $this->postBool('npm_http2_support'),
            'hsts_enabled'    => $this->postBool('npm_hsts_enabled'),
            'hsts_subdomains' => $this->postBool('npm_hsts_subdomains'),
        ];
        $cfOptions = [
            'record_ip' => trim((string) ($_POST['cf_record_ip'] ?? '')),
            'proxied' => $this->postBool('cf_proxied'),
        ];

        try {
            $this->service->update($domain, $docroot, (string) ($_SESSION['username'] ?? 'unknown'), $npmOptions, $cfOptions);
            Session::setFlash('success', 'Virtual host updated successfully.');
            $this->redirect('dashboard');
        } catch (RuntimeException $e) {
            Session::setFlash('error', $e->getMessage());
            header('Location: /?route=edit-vhost&domain=' . urlencode($domain));
            exit;
        }
    }

    public function delete(): void
    {
        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            Session::setFlash('error', 'Invalid CSRF token.');
            $this->redirect('dashboard');
        }

        $domain = (string) ($_POST['domain'] ?? '');
        $deleteRoot = isset($_POST['delete_root']) && $_POST['delete_root'] === '1';

        try {
            $this->service->delete($domain, $deleteRoot, (string) ($_SESSION['username'] ?? 'unknown'));
            Session::setFlash('success', 'Virtual host deleted successfully.');
            $this->redirect('dashboard');
        } catch (RuntimeException $e) {
            Session::setFlash('error', $e->getMessage());
            $this->redirect('dashboard');
        }
    }

    private function postBool(string $key): bool
    {
        return isset($_POST[$key]) && (string) $_POST[$key] === '1';
    }

    private function buildFqdn(string $subdomain, string $baseDomain): string
    {
        $left = trim($subdomain, " \t\n\r\0\x0B.");
        $right = trim($baseDomain, " \t\n\r\0\x0B.");

        if ($left === '') {
            return $right;
        }

        return $left . '.' . $right;
    }

    private function allowedDocrootBases(): array
    {
        $bases = array_filter(array_map(
            'trim',
            explode(',', (string) $this->config->get('ALLOWED_DOCROOT_BASES', '/var/www'))
        ));

        return $bases !== [] ? array_values($bases) : ['/var/www'];
    }

    private function buildDocroot(string $docrootBase, string $folderName): string
    {
        $allowedBases = $this->allowedDocrootBases();
        $selectedBase = trim($docrootBase);
        if ($selectedBase === '') {
            $selectedBase = (string) $this->config->get('DEFAULT_DOCROOT_BASE', '/var/www');
        }

        if (!in_array($selectedBase, $allowedBases, true)) {
            throw new RuntimeException('Selected document root base is not allowed.');
        }

        if ($folderName === '') {
            throw new RuntimeException('A folder name could not be derived for the document root.');
        }

        return rtrim($selectedBase, '/') . '/' . $folderName;
    }

    private function normalizeFolderName(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = trim($normalized, " ./\\");
        if ($normalized === '') {
            return '';
        }

        if (!preg_match('/^[a-z0-9](?:[a-z0-9._-]{0,120}[a-z0-9])?$/', $normalized)) {
            throw new RuntimeException('Alias may only contain letters, numbers, dots, dashes, and underscores.');
        }

        return $normalized;
    }

    private function additionalUsersCountFromConfig(): int
    {
        $raw = (string) $this->config->get('USERS_JSON', '');
        if ($raw === '') {
            return 0;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? count($decoded) : 0;
    }

    private function integrationCountFromConfig(): int
    {
        $raw = (string) $this->config->get('INTEGRATIONS_JSON', '');
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return count($decoded);
            }
        }

        $count = 0;
        if (
            trim((string) $this->config->get('NPM_BASE_URL', '')) !== ''
            && trim((string) $this->config->get('NPM_IDENTITY', '')) !== ''
            && trim((string) $this->config->get('NPM_SECRET', '')) !== ''
        ) {
            $count++;
        }

        if (
            trim((string) $this->config->get('CF_API_TOKEN', '')) !== ''
            && trim((string) $this->config->get('CF_ZONE_ID', '')) !== ''
            && trim((string) $this->config->get('CF_RECORD_IP', '')) !== ''
        ) {
            $count++;
        }

        return $count;
    }
}
