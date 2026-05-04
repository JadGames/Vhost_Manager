<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Session;
use App\Security\Csrf;
use App\Services\ApacheModulesService;
use App\Services\SettingsStore;
use App\Services\VhostService;
use RuntimeException;

final class VhostController extends BaseController
{
    public function __construct(
        Config $config,
        private readonly Csrf $csrf,
        private readonly VhostService $service,
        private readonly ApacheModulesService $apacheModules,
        private readonly SettingsStore $settingsStore
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
        $this->render('domains/index.php', [
            'csrfToken' => $this->csrf->token(),
            'cfEnabled' => $this->config->getBool('CF_ENABLED', false),
            'domains' => $this->domainsFromStore(),
        ]);
    }

    public function saveDomain(): void
    {
        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            Session::setFlash('error', 'Invalid CSRF token.');
            $this->redirect('domains');
        }

        $domain = strtolower(trim((string) ($_POST['domain'] ?? '')));
        if (!\App\Security\DomainValidator::isValid($domain)) {
            Session::setFlash('error', 'Domain must be a valid FQDN.');
            $this->redirect('domains');
        }

        $domains = $this->domainsFromStore();
        $domains = array_values(array_filter(
            $domains,
            static fn (array $row): bool => strtolower((string) ($row['domain'] ?? '')) !== $domain
        ));

        $record = [
            'domain' => $domain,
            'updated_at' => date('c'),
        ];

        if ($this->config->getBool('CF_ENABLED', false)) {
            $zoneId = trim((string) ($_POST['cf_zone_id'] ?? ''));
            $apiToken = trim((string) ($_POST['cf_api_token'] ?? ''));
            $recordIp = trim((string) ($_POST['cf_record_ip'] ?? ''));
            $proxied = isset($_POST['cf_proxied']) && (string) $_POST['cf_proxied'] === '1';
            $ttl = (int) ($_POST['cf_ttl'] ?? 120);

            if (($zoneId !== '' && $apiToken === '') || ($zoneId === '' && $apiToken !== '')) {
                Session::setFlash('error', 'Cloudflare zone ID and API token must be provided together.');
                $this->redirect('domains');
            }

            if ($zoneId !== '' && preg_match('/^[a-f0-9]{32}$/i', $zoneId) !== 1) {
                Session::setFlash('error', 'Cloudflare zone ID must be a 32-character hexadecimal value.');
                $this->redirect('domains');
            }

            if ($recordIp !== '' && filter_var($recordIp, FILTER_VALIDATE_IP) === false) {
                Session::setFlash('error', 'Cloudflare record IP must be a valid IPv4 or IPv6 address.');
                $this->redirect('domains');
            }

            if ($ttl < 1 || $ttl > 86400) {
                Session::setFlash('error', 'Cloudflare TTL must be between 1 and 86400 seconds.');
                $this->redirect('domains');
            }

            $record['cloudflare'] = [
                'zone_id' => $zoneId,
                'api_token' => $apiToken,
                'record_ip' => $recordIp,
                'proxied' => $proxied,
                'ttl' => $ttl,
            ];

            $this->upsertCloudflareDomainMapping($domain, $zoneId, $apiToken);
        }

        $domains[] = $record;
        usort($domains, static fn (array $a, array $b): int => strcasecmp((string) ($a['domain'] ?? ''), (string) ($b['domain'] ?? '')));

        $this->settingsStore->setMany([
            'DOMAINS_JSON' => json_encode($domains, JSON_UNESCAPED_SLASHES) ?: '[]',
        ]);

        Session::setFlash('success', 'Domain settings saved.');
        $this->redirect('domains');
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

    /**
     * @return list<array{domain:string,updated_at:string,cloudflare?:array{zone_id:string,api_token:string,record_ip:string,proxied:bool,ttl:int}}>
     */
    private function domainsFromStore(): array
    {
        $raw = (string) $this->config->get('DOMAINS_JSON', '[]');
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $rows = [];
        foreach ($decoded as $entry) {
            if (is_string($entry)) {
                $domain = strtolower(trim($entry));
                if ($domain !== '' && \App\Security\DomainValidator::isValid($domain)) {
                    $rows[] = ['domain' => $domain, 'updated_at' => ''];
                }
                continue;
            }

            if (!is_array($entry)) {
                continue;
            }

            $domain = strtolower(trim((string) ($entry['domain'] ?? '')));
            if ($domain === '' || !\App\Security\DomainValidator::isValid($domain)) {
                continue;
            }

            $row = [
                'domain' => $domain,
                'updated_at' => trim((string) ($entry['updated_at'] ?? '')),
            ];

            if (is_array($entry['cloudflare'] ?? null)) {
                $row['cloudflare'] = [
                    'zone_id' => trim((string) ($entry['cloudflare']['zone_id'] ?? '')),
                    'api_token' => trim((string) ($entry['cloudflare']['api_token'] ?? '')),
                    'record_ip' => trim((string) ($entry['cloudflare']['record_ip'] ?? '')),
                    'proxied' => !empty($entry['cloudflare']['proxied']),
                    'ttl' => max(1, (int) ($entry['cloudflare']['ttl'] ?? 120)),
                ];
            }

            $rows[] = $row;
        }

        return $rows;
    }

    private function upsertCloudflareDomainMapping(string $domain, string $zoneId, string $apiToken): void
    {
        $raw = (string) $this->config->get('CF_DOMAINS_JSON', '[]');
        $decoded = json_decode($raw, true);
        $mappings = is_array($decoded) ? $decoded : [];

        $mappings = array_values(array_filter(
            $mappings,
            static fn (array $row): bool => strtolower(trim((string) ($row['domain'] ?? ''))) !== $domain
        ));

        if ($zoneId !== '' && $apiToken !== '') {
            $mappings[] = [
                'domain' => $domain,
                'zone_id' => $zoneId,
                'api_token' => $apiToken,
            ];
            usort($mappings, static fn (array $a, array $b): int => strcasecmp((string) ($a['domain'] ?? ''), (string) ($b['domain'] ?? '')));
        }

        $this->settingsStore->setMany([
            'CF_DOMAINS_JSON' => json_encode($mappings, JSON_UNESCAPED_SLASHES) ?: '[]',
        ]);
    }
}
