<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Security\DomainValidator;
use App\Security\PathValidator;
use RuntimeException;

final class VhostService
{
    public function __construct(
        private readonly Config $config,
        private readonly Logger $logger,
        private readonly VhostRepository $repository,
        private readonly ?CloudflareService $cloudflare = null,
        private readonly ?NpmService $npm = null
    ) {
    }

    public function listManaged(): array
    {
        return $this->repository->all();
    }

    public function getManaged(string $domain): ?array
    {
        $domain = strtolower(trim($domain));
        $all = $this->repository->all();

        return $all[$domain] ?? null;
    }

    public function listNpmCertificates(): array
    {
        if ($this->npm === null) {
            return [];
        }

        try {
            return $this->npm->listCertificates();
        } catch (RuntimeException $e) {
            $this->logger->warning('NPM certificate list unavailable', ['error' => $e->getMessage()]);

            return [];
        }
    }

    public function create(
        string $domain,
        ?string $docroot,
        string $actor,
        array $npmOptions = [],
        ?string $alias = null,
        bool $createCloudflare = true,
        bool $createNpm = true
    ): array
    {
        $domain = strtolower(trim($domain));
        $alias = trim((string) ($alias ?? ''));
        if (!DomainValidator::isValid($domain)) {
            throw new RuntimeException('Invalid domain name.');
        }

        $defaultBase = rtrim((string) $this->config->get('DEFAULT_DOCROOT_BASE', '/var/www'), '/');
        $docroot = trim((string) ($docroot ?? ''));
        $docroot = $docroot !== '' ? PathValidator::normalize($docroot) : $defaultBase . '/' . $domain;

        $allowedBases = array_filter(array_map('trim', explode(',', (string) $this->config->get('ALLOWED_DOCROOT_BASES', '/var/www'))));
        if (!PathValidator::isPathWithinAllowedBases($docroot, $allowedBases)) {
            throw new RuntimeException('Document root path is outside allowed directories.');
        }

        // Step 1: Create Apache vhost via privileged helper
        $helper  = (string) $this->config->get('PRIV_HELPER', '/usr/local/sbin/vhost-admin-helper');
        $allowedBasesStr = implode(',', $allowedBases);
        $command = sprintf(
            'sudo %s create %s %s %s',
            escapeshellarg($helper),
            escapeshellarg($domain),
            escapeshellarg($docroot),
            escapeshellarg($allowedBasesStr)
        );

        [$exitCode, $output] = $this->runCommand($command);
        if ($exitCode !== 0) {
            $this->logger->error('Vhost create failed', ['domain' => $domain, 'output' => $output, 'actor' => $actor]);
            throw new RuntimeException("Failed to create virtual host: {$output}");
        }

        // Step 2: Cloudflare DNS record (rollback Apache on failure)
        $cfRecordId = null;
        $cfZoneId = null;
        if ($createCloudflare && $this->cloudflare !== null) {
            try {
                $cfRecordId = $this->cloudflare->createRecord($domain);
                $cfZoneId = $this->cloudflare->resolveZoneIdForDomain($domain);
            } catch (RuntimeException $e) {
                $this->rollbackApache($helper, $domain, $docroot, $allowedBasesStr);
                throw new RuntimeException("Cloudflare DNS failed (Apache vhost rolled back): " . $e->getMessage());
            }
        } else {
            $this->logger->info('Cloudflare DNS skipped for vhost create', [
                'domain' => $domain,
                'create_cloudflare' => $createCloudflare,
                'cloudflare_service_available' => $this->cloudflare !== null,
                'actor' => $actor,
            ]);
        }

        // Step 3: NPM proxy host (rollback CF + Apache on failure)
        $npmProxyId = null;
        if ($createNpm && $this->npm !== null) {
            try {
                $npmProxyId = $this->npm->createProxyHost($domain, $npmOptions);
            } catch (RuntimeException $e) {
                if ($cfRecordId !== null && $this->cloudflare !== null) {
                    $this->cloudflare->deleteRecord($cfRecordId, $domain, $cfZoneId);
                }
                $this->rollbackApache($helper, $domain, $docroot, $allowedBasesStr);
                throw new RuntimeException("NPM proxy host failed (Apache vhost and DNS rolled back): " . $e->getMessage());
            }
        } else {
            $this->logger->info('NPM proxy host skipped for vhost create', [
                'domain' => $domain,
                'create_npm' => $createNpm,
                'npm_service_available' => $this->npm !== null,
                'actor' => $actor,
            ]);
        }

        $record = [
            'domain'       => $domain,
            'alias'        => $alias !== '' ? $alias : null,
            'docroot'      => $docroot,
            'created_at'   => date('c'),
            'updated_at'   => date('c'),
            'created_by'   => $actor,
            'cf_record_id' => $cfRecordId,
            'cf_zone_id'   => $cfZoneId,
            'cf_record_ip' => $cfRecordId !== null && $this->cloudflare !== null ? $this->cloudflare->defaultRecordIp() : null,
            'cf_proxied'   => $cfRecordId !== null && $this->cloudflare !== null ? $this->cloudflare->defaultProxied() : null,
            'npm_proxy_id' => $npmProxyId,
            'npm_ssl_enabled' => !empty($npmOptions['ssl_enabled']),
            'npm_certificate_id' => (int) ($npmOptions['certificate_id'] ?? 0),
            'npm_ssl_forced' => !empty($npmOptions['ssl_forced']),
            'npm_http2_support' => !empty($npmOptions['http2_support']),
            'npm_hsts_enabled' => !empty($npmOptions['hsts_enabled']),
            'npm_hsts_subdomains' => !empty($npmOptions['hsts_subdomains']),
        ];

        $this->repository->put($domain, $record);
        $this->logger->info('Vhost created', [
            'domain'       => $domain,
            'alias'        => $alias !== '' ? $alias : null,
            'docroot'      => $docroot,
            'cf_record_id' => $cfRecordId,
            'npm_proxy_id' => $npmProxyId,
            'actor'        => $actor,
        ]);

        return $record;
    }

    public function update(string $domain, ?string $docroot, string $actor, array $npmOptions = [], array $cfOptions = []): array
    {
        $domain = strtolower(trim($domain));
        if (!DomainValidator::isValid($domain)) {
            throw new RuntimeException('Invalid domain name.');
        }

        $entry = $this->getManaged($domain);
        if ($entry === null) {
            throw new RuntimeException('Virtual host not found.');
        }

        $currentDocroot = (string) ($entry['docroot'] ?? '');
        if ($currentDocroot === '') {
            throw new RuntimeException('Missing current document root in record.');
        }

        $newDocroot = trim((string) ($docroot ?? ''));
        $newDocroot = $newDocroot !== '' ? PathValidator::normalize($newDocroot) : $currentDocroot;

        $allowedBases = array_filter(array_map('trim', explode(',', (string) $this->config->get('ALLOWED_DOCROOT_BASES', '/var/www'))));
        if (!PathValidator::isPathWithinAllowedBases($newDocroot, $allowedBases)) {
            throw new RuntimeException('Document root path is outside allowed directories.');
        }

        $helper = (string) $this->config->get('PRIV_HELPER', '/usr/local/sbin/vhost-admin-helper');
        $allowedBasesStr = implode(',', $allowedBases);

        if ($newDocroot !== $currentDocroot) {
            $deleteCmd = sprintf(
                'sudo %s delete %s 0 %s %s',
                escapeshellarg($helper),
                escapeshellarg($domain),
                escapeshellarg($currentDocroot),
                escapeshellarg($allowedBasesStr)
            );

            [$deleteExit, $deleteOutput] = $this->runCommand($deleteCmd);
            if ($deleteExit !== 0) {
                throw new RuntimeException("Failed to update Apache vhost (delete old config): {$deleteOutput}");
            }

            $createCmd = sprintf(
                'sudo %s create %s %s %s',
                escapeshellarg($helper),
                escapeshellarg($domain),
                escapeshellarg($newDocroot),
                escapeshellarg($allowedBasesStr)
            );

            [$createExit, $createOutput] = $this->runCommand($createCmd);
            if ($createExit !== 0) {
                $rollbackCmd = sprintf(
                    'sudo %s create %s %s %s',
                    escapeshellarg($helper),
                    escapeshellarg($domain),
                    escapeshellarg($currentDocroot),
                    escapeshellarg($allowedBasesStr)
                );
                [$rollbackExit, $rollbackOutput] = $this->runCommand($rollbackCmd);
                if ($rollbackExit !== 0) {
                    $this->logger->error('Apache vhost update rollback failed', ['domain' => $domain, 'output' => $rollbackOutput]);
                }

                throw new RuntimeException("Failed to update Apache vhost (create new config): {$createOutput}");
            }
        }

        $cfRecordId = (string) ($entry['cf_record_id'] ?? '');
        $cfZoneId = (string) ($entry['cf_zone_id'] ?? '');
        if ($cfRecordId !== '') {
            if ($this->cloudflare === null) {
                throw new RuntimeException('Cloudflare DNS record exists for this domain, but Cloudflare integration is not configured.');
            }

            $desiredIp = trim((string) ($cfOptions['record_ip'] ?? ($entry['cf_record_ip'] ?? $this->cloudflare->defaultRecordIp())));
            if ($desiredIp === '') {
                $desiredIp = (string) ($entry['cf_record_ip'] ?? $this->cloudflare->defaultRecordIp());
            }
            $desiredProxied = array_key_exists('proxied', $cfOptions)
                ? (bool) $cfOptions['proxied']
                : (bool) ($entry['cf_proxied'] ?? $this->cloudflare->defaultProxied());

            $this->cloudflare->updateRecord($cfRecordId, $domain, $desiredIp, $desiredProxied, $cfZoneId !== '' ? $cfZoneId : null);
            if ($cfZoneId === '') {
                $entry['cf_zone_id'] = $this->cloudflare->resolveZoneIdForDomain($domain);
            }
            $entry['cf_record_ip'] = $desiredIp;
            $entry['cf_proxied'] = $desiredProxied;
        }

        $npmProxyId = (int) ($entry['npm_proxy_id'] ?? 0);
        if ($npmProxyId > 0) {
            if ($this->npm === null) {
                throw new RuntimeException('NPM proxy host exists for this domain, but NPM service is not configured.');
            }

            $this->npm->updateProxyHost($npmProxyId, $domain, $npmOptions);
            $entry['npm_ssl_enabled'] = !empty($npmOptions['ssl_enabled']);
            $entry['npm_certificate_id'] = (int) ($npmOptions['certificate_id'] ?? 0);
            $entry['npm_ssl_forced'] = !empty($npmOptions['ssl_forced']);
            $entry['npm_http2_support'] = !empty($npmOptions['http2_support']);
            $entry['npm_hsts_enabled'] = !empty($npmOptions['hsts_enabled']);
            $entry['npm_hsts_subdomains'] = !empty($npmOptions['hsts_subdomains']);
        }

        $entry['docroot'] = $newDocroot;
        $entry['updated_at'] = date('c');
        $entry['updated_by'] = $actor;

        $this->repository->put($domain, $entry);
        $this->logger->info('Vhost updated', ['domain' => $domain, 'docroot' => $newDocroot, 'actor' => $actor]);

        return $entry;
    }

    public function delete(string $domain, bool $deleteRoot, string $actor): void
    {
        $domain = strtolower(trim($domain));
        if (!DomainValidator::isValid($domain)) {
            throw new RuntimeException('Invalid domain name.');
        }

        $entries = $this->repository->all();
        $entry   = $entries[$domain] ?? null;
        if ($entry === null) {
            throw new RuntimeException('Virtual host not found.');
        }

        $docroot = (string) ($entry['docroot'] ?? '');
        if ($docroot === '') {
            throw new RuntimeException('Missing document root in record.');
        }

        // Reverse order: NPM → Cloudflare → Apache

        // Step 1: Delete NPM proxy host
        $npmProxyId = (int) ($entry['npm_proxy_id'] ?? 0);
        if ($npmProxyId > 0) {
            if ($this->npm !== null) {
                $this->npm->deleteProxyHost($npmProxyId);
            } else {
                $this->logger->warning('NPM proxy host ID stored but NPM service not configured; skipping cleanup.', ['proxy_id' => $npmProxyId]);
            }
        }

        // Step 2: Delete Cloudflare DNS record
        $cfRecordId = (string) ($entry['cf_record_id'] ?? '');
        $cfZoneId = (string) ($entry['cf_zone_id'] ?? '');
        if ($cfRecordId !== '') {
            if ($this->cloudflare !== null) {
                $this->cloudflare->deleteRecord($cfRecordId, $domain, $cfZoneId !== '' ? $cfZoneId : null);
            } else {
                $this->logger->warning('Cloudflare record ID stored but CF service not configured; skipping cleanup.', ['record_id' => $cfRecordId]);
            }
        }

        // Step 3: Delete Apache vhost via helper
        $helper  = (string) $this->config->get('PRIV_HELPER', '/usr/local/sbin/vhost-admin-helper');
        $allowedBases = array_filter(array_map('trim', explode(',', (string) $this->config->get('ALLOWED_DOCROOT_BASES', '/var/www'))));
        $allowedBasesStr = implode(',', $allowedBases);
        $command = sprintf(
            'sudo %s delete %s %s %s %s',
            escapeshellarg($helper),
            escapeshellarg($domain),
            escapeshellarg($deleteRoot ? '1' : '0'),
            escapeshellarg($docroot),
            escapeshellarg($allowedBasesStr)
        );

        [$exitCode, $output] = $this->runCommand($command);
        if ($exitCode !== 0) {
            $this->logger->error('Vhost delete failed', ['domain' => $domain, 'output' => $output, 'actor' => $actor]);
            throw new RuntimeException("Failed to delete virtual host: {$output}");
        }

        $this->repository->remove($domain);
        $this->logger->info('Vhost deleted', ['domain' => $domain, 'delete_root' => $deleteRoot, 'actor' => $actor]);
    }

    private function rollbackApache(string $helper, string $domain, string $docroot, string $allowedBasesStr = '/var/www'): void
    {
        $cmd = sprintf(
            'sudo %s delete %s 0 %s %s',
            escapeshellarg($helper),
            escapeshellarg($domain),
            escapeshellarg($docroot),
            escapeshellarg($allowedBasesStr)
        );
        [$exitCode, $output] = $this->runCommand($cmd);
        if ($exitCode !== 0) {
            $this->logger->error('Apache vhost rollback failed', ['domain' => $domain, 'output' => $output]);
        }
    }

    private function runCommand(string $command): array
    {
        $output   = [];
        $exitCode = 0;
        exec($command . ' 2>&1', $output, $exitCode);

        return [$exitCode, implode("\n", $output)];
    }
}
