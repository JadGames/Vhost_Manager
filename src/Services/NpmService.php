<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use RuntimeException;

/**
 * Nginx Proxy Manager API integration.
 *
 * NPM exposes a REST API at /api.
 * Default port is 81 (HTTP) or 81/443 when SSL is enabled on the NPM instance.
 */
final class NpmService
{
    private readonly string $baseUrl;
    private readonly string $identity;
    private readonly string $secret;
    private readonly string $forwardHost;
    private readonly int    $forwardPort;
    private readonly bool   $sslEnabled;
    private readonly int    $certificateId;
    private readonly bool   $sslForced;
    private readonly bool   $http2Support;
    private readonly bool   $hstsEnabled;
    private readonly bool   $hstsSubdomains;

    private ?string $token = null;

    public function __construct(
        Config $config,
        private readonly HttpClient $http,
        private readonly Logger $logger
    ) {
        $this->baseUrl      = rtrim((string) $config->get('NPM_BASE_URL', 'http://localhost:81'), '/');
        $this->identity     = (string) $config->get('NPM_IDENTITY', '');
        $this->secret       = (string) $config->get('NPM_SECRET', '');
        $this->forwardHost  = (string) $config->get('NPM_FORWARD_HOST', '127.0.0.1');
        $this->forwardPort  = (int) $config->get('NPM_FORWARD_PORT', 80);
        $this->sslEnabled   = $config->getBool('NPM_SSL_ENABLED', false);
        $this->certificateId = (int) $config->get('NPM_CERTIFICATE_ID', 0);
        $this->sslForced    = $config->getBool('NPM_SSL_FORCED', false);
        $this->http2Support = $config->getBool('NPM_HTTP2_SUPPORT', false);
        $this->hstsEnabled  = $config->getBool('NPM_HSTS_ENABLED', false);
        $this->hstsSubdomains = $config->getBool('NPM_HSTS_SUBDOMAINS', false);
    }

    /**
     * Create an NPM proxy host for $domain forwarding to NPM_FORWARD_HOST:NPM_FORWARD_PORT.
     * Returns the NPM proxy host ID (needed for deletion).
     */
    public function createProxyHost(string $domain, array $options = []): int
    {
        $this->assertConfigured();

        $sslEnabled = $this->resolveBool($options['ssl_enabled'] ?? $this->sslEnabled);
        $certificateId = (int) ($options['certificate_id'] ?? $this->certificateId);
        if ($sslEnabled && $certificateId <= 0) {
            throw new RuntimeException('NPM SSL is enabled but no valid certificate ID is set. Configure NPM_CERTIFICATE_ID or provide it in the form.');
        }

        $sslForced = $sslEnabled ? $this->resolveBool($options['ssl_forced'] ?? $this->sslForced) : false;
        $http2Support = $sslEnabled ? $this->resolveBool($options['http2_support'] ?? $this->http2Support) : false;
        $hstsEnabled = $sslEnabled ? $this->resolveBool($options['hsts_enabled'] ?? $this->hstsEnabled) : false;
        $hstsSubdomains = ($sslEnabled && $hstsEnabled)
            ? $this->resolveBool($options['hsts_subdomains'] ?? $this->hstsSubdomains)
            : false;

        $token = $this->getToken();
        $url   = "{$this->baseUrl}/api/nginx/proxy-hosts";

        $payload = [
            'domain_names'            => [$domain],
            'forward_scheme'          => 'http',
            'forward_host'            => $this->forwardHost,
            'forward_port'            => $this->forwardPort,
            'allow_websocket_upgrade' => true,
            'access_list_id'          => '0',
            'certificate_id'          => $sslEnabled ? $certificateId : 0,
            'ssl_forced'              => $sslForced,
            'http2_support'           => $http2Support,
            'hsts_enabled'            => $hstsEnabled,
            'hsts_subdomains'         => $hstsSubdomains,
            'block_exploits'          => true,
            'caching_enabled'         => false,
            'advanced_config'         => '',
        ];

        $result = $this->http->post($url, $payload, $this->authHeader($token));

        if (!in_array($result['status'], [200, 201], true)) {
            $body = json_encode($result['body'] ?? [], JSON_UNESCAPED_SLASHES);
            $this->logger->error('NPM proxy host create failed', ['domain' => $domain, 'status' => $result['status'], 'body' => $body]);
            throw new RuntimeException("NPM proxy host creation failed (HTTP {$result['status']}): {$body}");
        }

        $proxyId = (int) ($result['body']['id'] ?? 0);
        $this->logger->info('NPM proxy host created', ['domain' => $domain, 'proxy_id' => $proxyId]);

        return $proxyId;
    }

    /**
     * Delete an NPM proxy host by its ID.
     */
    public function deleteProxyHost(int $proxyId): void
    {
        $this->assertConfigured();

        if ($proxyId <= 0) {
            return;
        }

        try {
            $token  = $this->getToken();
            $url    = "{$this->baseUrl}/api/nginx/proxy-hosts/{$proxyId}";
            $result = $this->http->delete($url, $this->authHeader($token));

            if ($result['status'] !== 200) {
                $this->logger->warning('NPM proxy host delete failed', ['proxy_id' => $proxyId, 'status' => $result['status']]);
            } else {
                $this->logger->info('NPM proxy host deleted', ['proxy_id' => $proxyId]);
            }
        } catch (RuntimeException $e) {
            $this->logger->warning('NPM proxy host delete error', ['proxy_id' => $proxyId, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Update SSL-related settings for an existing NPM proxy host.
     */
    public function updateProxyHost(int $proxyId, string $domain, array $options = []): void
    {
        $this->assertConfigured();

        if ($proxyId <= 0) {
            throw new RuntimeException('Invalid NPM proxy host ID.');
        }

        $token = $this->getToken();
        $existing = $this->getProxyHost($proxyId, $token);

        $sslEnabled = $this->resolveBool($options['ssl_enabled'] ?? $this->sslEnabled);
        $certificateId = (int) ($options['certificate_id'] ?? $this->certificateId);
        if ($sslEnabled && $certificateId <= 0) {
            throw new RuntimeException('NPM SSL is enabled but no valid certificate ID is set.');
        }

        $sslForced = $sslEnabled ? $this->resolveBool($options['ssl_forced'] ?? $this->sslForced) : false;
        $http2Support = $sslEnabled ? $this->resolveBool($options['http2_support'] ?? $this->http2Support) : false;
        $hstsEnabled = $sslEnabled ? $this->resolveBool($options['hsts_enabled'] ?? $this->hstsEnabled) : false;
        $hstsSubdomains = ($sslEnabled && $hstsEnabled)
            ? $this->resolveBool($options['hsts_subdomains'] ?? $this->hstsSubdomains)
            : false;

        $payload = [
            'domain_names'            => $existing['domain_names'] ?? [$domain],
            'forward_scheme'          => (string) ($existing['forward_scheme'] ?? 'http'),
            'forward_host'            => (string) ($existing['forward_host'] ?? $this->forwardHost),
            'forward_port'            => (int) ($existing['forward_port'] ?? $this->forwardPort),
            'allow_websocket_upgrade' => !empty($existing['allow_websocket_upgrade']),
            'access_list_id'          => (string) ($existing['access_list_id'] ?? '0'),
            'certificate_id'          => $sslEnabled ? $certificateId : 0,
            'ssl_forced'              => $sslForced,
            'http2_support'           => $http2Support,
            'hsts_enabled'            => $hstsEnabled,
            'hsts_subdomains'         => $hstsSubdomains,
            'block_exploits'          => !empty($existing['block_exploits']),
            'caching_enabled'         => !empty($existing['caching_enabled']),
            'advanced_config'         => (string) ($existing['advanced_config'] ?? ''),
            'enabled'                 => array_key_exists('enabled', $existing) ? (bool) $existing['enabled'] : true,
            'meta'                    => $existing['meta'] ?? [],
            'locations'               => $existing['locations'] ?? [],
        ];

        $url = "{$this->baseUrl}/api/nginx/proxy-hosts/{$proxyId}";
        $result = $this->http->put($url, $payload, $this->authHeader($token));

        if (!in_array($result['status'], [200, 201], true)) {
            $body = json_encode($result['body'] ?? [], JSON_UNESCAPED_SLASHES);
            $this->logger->error('NPM proxy host update failed', ['domain' => $domain, 'proxy_id' => $proxyId, 'status' => $result['status'], 'body' => $body]);
            throw new RuntimeException("NPM proxy host update failed (HTTP {$result['status']}): {$body}");
        }

        $this->logger->info('NPM proxy host updated', ['domain' => $domain, 'proxy_id' => $proxyId]);
    }

    /**
     * Fetch available SSL certificates from NPM.
     *
     * Returns a normalized list of arrays with keys:
     * - id (int)
     * - name (string)
     */
    public function listCertificates(): array
    {
        $this->assertConfigured();

        $token  = $this->getToken();
        $url    = "{$this->baseUrl}/api/nginx/certificates";
        $result = $this->http->get($url, $this->authHeader($token));

        if ($result['status'] !== 200 || !is_array($result['body'])) {
            $this->logger->warning('NPM certificates lookup failed', ['status' => $result['status']]);

            return [];
        }

        $certs = [];
        foreach ($result['body'] as $row) {
            if (!is_array($row)) {
                continue;
            }

            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $certs[] = [
                'id' => $id,
                'name' => $this->certificateDisplayName($row),
            ];
        }

        usort($certs, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $certs;
    }

    private function getToken(): string
    {
        if ($this->token !== null) {
            return $this->token;
        }

        $url    = "{$this->baseUrl}/api/tokens";
        $result = $this->http->post($url, ['identity' => $this->identity, 'secret' => $this->secret]);

        if ($result['status'] !== 200 || empty($result['body']['token'])) {
            $this->logger->error('NPM authentication failed', ['status' => $result['status']]);
            throw new RuntimeException('NPM authentication failed. Check NPM_IDENTITY and NPM_SECRET.');
        }

        $this->token = (string) $result['body']['token'];

        return $this->token;
    }

    private function authHeader(string $token): array
    {
        return ["Authorization: Bearer {$token}"];
    }

    private function assertConfigured(): void
    {
        if ($this->baseUrl === '' || $this->identity === '' || $this->secret === '') {
            throw new RuntimeException('NPM is not fully configured. NPM_BASE_URL, NPM_IDENTITY and NPM_SECRET are all required.');
        }
    }

    private function resolveBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
        }

        if (is_int($value)) {
            return $value === 1;
        }

        return false;
    }

    private function getProxyHost(int $proxyId, string $token): array
    {
        $url = "{$this->baseUrl}/api/nginx/proxy-hosts/{$proxyId}";
        $result = $this->http->get($url, $this->authHeader($token));

        if ($result['status'] !== 200 || !is_array($result['body'])) {
            throw new RuntimeException("Unable to load NPM proxy host {$proxyId} for update.");
        }

        return $result['body'];
    }

    private function certificateDisplayName(array $row): string
    {
        $display = trim((string) ($row['nice_name'] ?? $row['name'] ?? ''));
        if ($display !== '') {
            return $display;
        }

        $domains = $row['domain_names'] ?? [];
        if (is_array($domains) && !empty($domains)) {
            $domainList = array_values(array_filter(array_map(static fn ($v): string => trim((string) $v), $domains)));
            if (!empty($domainList)) {
                return implode(', ', $domainList);
            }
        }

        $provider = trim((string) ($row['provider'] ?? 'certificate'));

        return ucfirst($provider) . ' #' . (int) ($row['id'] ?? 0);
    }
}
