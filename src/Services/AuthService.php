<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Security\RateLimiter;
use App\Services\SettingsStore;
use RuntimeException;

final class AuthService
{
    public function __construct(
        private readonly Config $config,
        private readonly RateLimiter $rateLimiter,
        private readonly Logger $logger,
        private readonly SettingsStore $settingsStore
    ) {
    }

    public function login(string $identity, string $password, string $ipAddress): array
    {
        $identity = strtolower(trim($identity));
        $limiterKey = 'login:' . hash('sha256', $ipAddress);

        if ($this->rateLimiter->isLimited($limiterKey)) {
            $seconds = $this->rateLimiter->remainingLockSeconds($limiterKey);
            return [false, "Too many failed attempts. Try again in {$seconds} seconds."];
        }

        $expectedUser = strtolower(trim((string) $this->config->get('ADMIN_USER', 'admin')));
        $expectedHash = (string) $this->config->get('ADMIN_PASSWORD_HASH', '');
        $users = $this->usersFromStore();
        $usersMeta = $this->usersMetaFromStore();

        $isPrimaryAdmin = hash_equals($expectedUser, $identity) && $expectedHash !== '' && password_verify($password, $expectedHash);
        $isAdditionalUser = isset($users[$identity]) && $users[$identity] !== '' && password_verify($password, $users[$identity]);

        if ($isAdditionalUser) {
            $meta = $usersMeta[$identity] ?? [];
            if (array_key_exists('active', $meta) && (bool) $meta['active'] === false) {
                $this->rateLimiter->hit($limiterKey);
                $this->logger->warning('Blocked login for disabled account', ['email' => $identity, 'ip' => $ipAddress]);

                return [false, 'This account is disabled.'];
            }
        }

        $isValid = $isPrimaryAdmin || $isAdditionalUser;

        if ($isValid) {
            $this->rateLimiter->clear($limiterKey);
            $this->recordSuccessfulLogin($identity, $isPrimaryAdmin, $usersMeta);
            $this->logger->info('User authenticated', ['email' => $identity, 'ip' => $ipAddress]);
            return [true, 'Login successful'];
        }

        $this->rateLimiter->hit($limiterKey);
        $this->logger->warning('Failed login', ['email' => $identity, 'ip' => $ipAddress]);

        return [false, 'Invalid credentials'];
    }

    /**
     * @param array<string, array{full_name:string,account_type:string,active:bool,created_at:string,last_login_at:string}> $usersMeta
     */
    private function recordSuccessfulLogin(string $identity, bool $isPrimaryAdmin, array $usersMeta): void
    {
        $now = date('c');

        try {
            if ($isPrimaryAdmin) {
                $this->settingsStore->setMany([
                    'ADMIN_LAST_LOGIN_AT' => $now,
                ]);

                return;
            }

            if (!isset($usersMeta[$identity])) {
                $usersMeta[$identity] = [
                    'full_name' => '',
                    'account_type' => 'user',
                    'active' => true,
                    'created_at' => $now,
                    'last_login_at' => $now,
                ];
            }

            $usersMeta[$identity]['last_login_at'] = $now;

            $this->settingsStore->setMany([
                'USERS_META_JSON' => json_encode($usersMeta, JSON_UNESCAPED_SLASHES) ?: '{}',
            ]);
        } catch (RuntimeException) {
            // Non-fatal for auth flow.
        }
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
        foreach ($decoded as $name => $hash) {
            $username = strtolower(trim((string) $name));
            $passwordHash = trim((string) $hash);
            if ($username === '' || $passwordHash === '') {
                continue;
            }

            $users[$username] = $passwordHash;
        }

        return $users;
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
}
