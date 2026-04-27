<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Security\RateLimiter;

final class AuthService
{
    public function __construct(
        private readonly Config $config,
        private readonly RateLimiter $rateLimiter,
        private readonly Logger $logger
    ) {
    }

    public function login(string $username, string $password, string $ipAddress): array
    {
        $limiterKey = 'login:' . hash('sha256', $ipAddress);

        if ($this->rateLimiter->isLimited($limiterKey)) {
            $seconds = $this->rateLimiter->remainingLockSeconds($limiterKey);
            return [false, "Too many failed attempts. Try again in {$seconds} seconds."];
        }

        $expectedUser = (string) $this->config->get('ADMIN_USER', 'admin');
        $expectedHash = (string) $this->config->get('ADMIN_PASSWORD_HASH', '');
        $users = $this->usersFromStore();

        $isPrimaryAdmin = hash_equals($expectedUser, $username) && $expectedHash !== '' && password_verify($password, $expectedHash);
        $isAdditionalUser = isset($users[$username]) && $users[$username] !== '' && password_verify($password, $users[$username]);
        $isValid = $isPrimaryAdmin || $isAdditionalUser;

        if ($isValid) {
            $this->rateLimiter->clear($limiterKey);
            $this->logger->info('User authenticated', ['username' => $username, 'ip' => $ipAddress]);
            return [true, 'Login successful'];
        }

        $this->rateLimiter->hit($limiterKey);
        $this->logger->warning('Failed login', ['username' => $username, 'ip' => $ipAddress]);

        return [false, 'Invalid credentials'];
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
            $username = trim((string) $name);
            $passwordHash = trim((string) $hash);
            if ($username === '' || $passwordHash === '') {
                continue;
            }

            $users[$username] = $passwordHash;
        }

        return $users;
    }
}
