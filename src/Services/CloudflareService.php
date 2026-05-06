<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use RuntimeException;

final class CloudflareService
{
    private readonly string $apiToken;
    private readonly string $zoneId;
    private readonly string $recordIp;
    private readonly bool   $proxied;
    private readonly int    $ttl;
    /** @var list<array{domain:string, zone_id:string, api_token:string}> */
    private readonly array $domainMappings;

    public function __construct(
        Config $config,
        private readonly HttpClient $http,
        private readonly Logger $logger,
        array $domainMappings = []
    ) {
        $this->apiToken  = (string) $config->get('CF_API_TOKEN', '');
        $this->zoneId    = (string) $config->get('CF_ZONE_ID', '');
        $this->recordIp  = (string) $config->get('CF_RECORD_IP', '');
        $this->proxied   = $config->getBool('CF_PROXIED', false);
        $this->ttl       = max(1, (int) $config->get('CF_TTL', 120));
        $normalizedMappings = $this->normalizeDomainMappings($domainMappings);
        $this->domainMappings = $normalizedMappings !== []
            ? $normalizedMappings
            : $this->parseDomainMappings((string) $config->get('CF_DOMAINS_JSON', ''));
    }

    /**
     * Create an A record for $domain pointing to CF_RECORD_IP.
     * Returns the Cloudflare record ID (needed for deletion).
     */
    public function createRecord(string $domain, ?string $zoneId = null): string
    {
        $credentials = $this->resolveCredentials($domain, $zoneId);
        $resolvedZoneId = $credentials['zone_id'];
        $resolvedToken  = $credentials['api_token'];
        // Per-domain record_ip takes precedence over the global default.
        $resolvedIp = $credentials['record_ip'] !== '' ? $credentials['record_ip'] : $this->recordIp;

        if ($resolvedIp === '') {
            throw new RuntimeException('Cloudflare record IP is required for DNS creation. Set the Record IP on the domain profile.');
        }

        $url     = "https://api.cloudflare.com/client/v4/zones/{$resolvedZoneId}/dns_records";
        $payload = [
            'type'    => 'A',
            'name'    => $domain,
            'content' => $resolvedIp,
            'ttl'     => $this->ttl,
            'proxied' => $this->proxied,
        ];

        $result = $this->http->post($url, $payload, $this->authHeader($resolvedToken));

        if ($result['status'] !== 200 || empty($result['body']['success'])) {
            $errors = json_encode($result['body']['errors'] ?? [], JSON_UNESCAPED_SLASHES);
            $this->logger->error('Cloudflare DNS create failed', ['domain' => $domain, 'errors' => $errors]);
            throw new RuntimeException("Cloudflare DNS creation failed: {$errors}");
        }

        $recordId = (string) ($result['body']['result']['id'] ?? '');
        $this->logger->info('Cloudflare DNS A record created', ['domain' => $domain, 'record_id' => $recordId, 'ip' => $this->recordIp]);

        return $recordId;
    }

    /**
     * Delete a Cloudflare DNS record by its ID.
     */
    public function deleteRecord(string $recordId, ?string $domain = null, ?string $zoneId = null): void
    {
        if ($recordId === '') {
            return;
        }

        try {
            $credentials = $this->resolveCredentials($domain, $zoneId);
            $resolvedZoneId = $credentials['zone_id'];
            $resolvedToken = $credentials['api_token'];
        } catch (RuntimeException $e) {
            $this->logger->warning('Cloudflare DNS delete skipped due to missing configuration', ['record_id' => $recordId, 'error' => $e->getMessage()]);

            return;
        }

        $url    = "https://api.cloudflare.com/client/v4/zones/{$resolvedZoneId}/dns_records/{$recordId}";
        $result = $this->http->delete($url, $this->authHeader($resolvedToken));

        if ($result['status'] !== 200 || empty($result['body']['success'])) {
            $this->logger->warning('Cloudflare DNS delete failed', ['record_id' => $recordId, 'status' => $result['status']]);
        } else {
            $this->logger->info('Cloudflare DNS record deleted', ['record_id' => $recordId]);
        }
    }

    /**
     * Update an existing A record.
     */
    public function updateRecord(string $recordId, string $domain, string $ip, bool $proxied, ?string $zoneId = null): void
    {
        $credentials = $this->resolveCredentials($domain, $zoneId);
        $resolvedZoneId = $credentials['zone_id'];
        $resolvedToken = $credentials['api_token'];

        if ($recordId === '') {
            throw new RuntimeException('Cloudflare record ID is required for updates.');
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            throw new RuntimeException('Cloudflare A record IP must be a valid IPv4 address.');
        }

        $url = "https://api.cloudflare.com/client/v4/zones/{$resolvedZoneId}/dns_records/{$recordId}";
        $payload = [
            'type' => 'A',
            'name' => $domain,
            'content' => $ip,
            'ttl' => $this->ttl,
            'proxied' => $proxied,
        ];

        $result = $this->http->put($url, $payload, $this->authHeader($resolvedToken));

        if ($result['status'] !== 200 || empty($result['body']['success'])) {
            $errors = json_encode($result['body']['errors'] ?? [], JSON_UNESCAPED_SLASHES);
            $this->logger->error('Cloudflare DNS update failed', ['domain' => $domain, 'record_id' => $recordId, 'errors' => $errors]);
            throw new RuntimeException("Cloudflare DNS update failed: {$errors}");
        }

        $this->logger->info('Cloudflare DNS record updated', [
            'domain' => $domain,
            'record_id' => $recordId,
            'ip' => $ip,
            'proxied' => $proxied,
        ]);
    }

    public function defaultRecordIp(): string
    {
        return $this->recordIp;
    }

    public function resolvedRecordIpForDomain(string $domain): string
    {
        try {
            $creds = $this->resolveCredentials($domain, null);
            return $creds['record_ip'] !== '' ? $creds['record_ip'] : $this->recordIp;
        } catch (RuntimeException) {
            return $this->recordIp;
        }
    }

    public function defaultProxied(): bool
    {
        return $this->proxied;
    }

    public function resolveZoneIdForDomain(string $domain): string
    {
        return $this->resolveCredentials($domain, null)['zone_id'];
    }

    private function authHeader(string $token): array
    {
        return ["Authorization: Bearer {$token}"];
    }

    /**
     * @return array{zone_id:string, api_token:string, record_ip:string}
     */
    private function resolveCredentials(?string $domain, ?string $zoneId): array
    {
        $domain = strtolower(trim((string) ($domain ?? '')));
        $explicitZoneId = trim((string) ($zoneId ?? ''));

        if ($explicitZoneId !== '') {
            foreach ($this->domainMappings as $mapping) {
                if ($mapping['zone_id'] === $explicitZoneId) {
                    return [
                        'zone_id'   => $mapping['zone_id'],
                        'api_token' => $mapping['api_token'],
                        'record_ip' => (string) ($mapping['record_ip'] ?? ''),
                    ];
                }
            }

            if ($this->apiToken !== '') {
                return ['zone_id' => $explicitZoneId, 'api_token' => $this->apiToken, 'record_ip' => ''];
            }
        }

        $best = null;
        foreach ($this->domainMappings as $mapping) {
            $suffix = $mapping['domain'];
            if ($domain === '' || ($domain !== $suffix && !str_ends_with($domain, '.' . $suffix))) {
                continue;
            }

            if ($best === null || strlen($suffix) > strlen($best['domain'])) {
                $best = $mapping;
            }
        }

        if ($best !== null) {
            return [
                'zone_id'   => $best['zone_id'],
                'api_token' => $best['api_token'],
                'record_ip' => (string) ($best['record_ip'] ?? ''),
            ];
        }

        if ($this->apiToken !== '' && $this->zoneId !== '') {
            return [
                'zone_id'   => $this->zoneId,
                'api_token' => $this->apiToken,
                'record_ip' => $this->recordIp,
            ];
        }

        throw new RuntimeException('Cloudflare is not fully configured. Configure default API token/zone or add a domain mapping.');
    }

    /**
     * @return list<array{domain:string, zone_id:string, api_token:string}>
     */
    private function parseDomainMappings(string $raw): array
    {
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

            $rows[] = ['domain' => $domain, 'zone_id' => $zoneId, 'api_token' => $apiToken, 'record_ip' => trim((string) ($entry['record_ip'] ?? ''))];
        }

        return $rows;
    }

    /**
     * @param list<array{domain:string,zone_id:string,api_token:string,record_ip?:string}> $mappings
     * @return list<array{domain:string,zone_id:string,api_token:string,record_ip:string}>
     */
    private function normalizeDomainMappings(array $mappings): array
    {
        $rows = [];
        foreach ($mappings as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $domain = strtolower(trim((string) ($entry['domain'] ?? '')));
            $zoneId = trim((string) ($entry['zone_id'] ?? ''));
            $apiToken = trim((string) ($entry['api_token'] ?? ''));

            if ($domain === '' || $zoneId === '' || $apiToken === '') {
                continue;
            }

            $rows[] = ['domain' => $domain, 'zone_id' => $zoneId, 'api_token' => $apiToken, 'record_ip' => trim((string) ($entry['record_ip'] ?? ''))];
        }

        return $rows;
    }
}
