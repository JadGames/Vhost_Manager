<?php

declare(strict_types=1);

namespace App\Services;

use App\Security\RateLimiter;
use App\Services\SettingsStore;
use RuntimeException;

final class AuthService
{
    public function __construct(
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

        $userRecord = $this->settingsStore->userGet($identity);

        if ($userRecord === null) {
            $this->rateLimiter->hit($limiterKey);
            $this->logger->warning('Failed login', ['email' => $identity, 'ip' => $ipAddress]);
            return [false, 'Invalid credentials'];
        }

        $hash = $userRecord['password_hash'];
        if ($hash === '' || !password_verify($password, $hash)) {
            $this->rateLimiter->hit($limiterKey);
            $this->logger->warning('Failed login', ['email' => $identity, 'ip' => $ipAddress]);
            return [false, 'Invalid credentials'];
        }

        if (!$userRecord['is_primary'] && !(bool) $userRecord['active']) {
            $this->rateLimiter->hit($limiterKey);
            $this->logger->warning('Blocked login for disabled account', ['email' => $identity, 'ip' => $ipAddress]);
            return [false, 'This account is disabled.'];
        }

        $this->rateLimiter->clear($limiterKey);
        $this->recordSuccessfulLogin($identity, (bool) $userRecord['is_primary']);
        $this->logger->info('User authenticated', ['email' => $identity, 'ip' => $ipAddress]);
        return [true, 'Login successful'];
    }

    private function recordSuccessfulLogin(string $identity, bool $isPrimaryAdmin): void
    {
        $now = date('c');

        try {
            $this->settingsStore->userUpsert(['email' => $identity, 'last_login_at' => $now]);

            if ($isPrimaryAdmin) {
                $this->settingsStore->setMany(['ADMIN_LAST_LOGIN_AT' => $now]);
            }
        } catch (RuntimeException) {
            // Non-fatal for auth flow.
        }
        }
    }
