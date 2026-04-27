<?php

declare(strict_types=1);

namespace App\Security;

final class RateLimiter
{
    public function __construct(
        private readonly string $storageFile,
        private readonly int $maxAttempts,
        private readonly int $windowSeconds,
        private readonly int $lockSeconds
    ) {
    }

    public function isLimited(string $key): bool
    {
        $data = $this->load();
        $entry = $data[$key] ?? ['attempts' => [], 'locked_until' => 0];

        if ((int) ($entry['locked_until'] ?? 0) > time()) {
            return true;
        }

        return false;
    }

    public function remainingLockSeconds(string $key): int
    {
        $data = $this->load();
        $lockedUntil = (int) (($data[$key]['locked_until'] ?? 0));

        return max(0, $lockedUntil - time());
    }

    public function hit(string $key): void
    {
        $data = $this->load();
        $now = time();

        $entry = $data[$key] ?? ['attempts' => [], 'locked_until' => 0];
        $attempts = array_values(array_filter(
            (array) ($entry['attempts'] ?? []),
            fn (mixed $timestamp): bool => is_int($timestamp) && $timestamp > ($now - $this->windowSeconds)
        ));

        $attempts[] = $now;
        $lockedUntil = 0;

        if (count($attempts) >= $this->maxAttempts) {
            $lockedUntil = $now + $this->lockSeconds;
            $attempts = [];
        }

        $data[$key] = [
            'attempts' => $attempts,
            'locked_until' => $lockedUntil,
        ];

        $this->save($data);
    }

    public function clear(string $key): void
    {
        $data = $this->load();
        unset($data[$key]);
        $this->save($data);
    }

    private function load(): array
    {
        if (!is_file($this->storageFile)) {
            return [];
        }

        $json = file_get_contents($this->storageFile);
        if ($json === false || $json === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function save(array $data): void
    {
        $dir = dirname($this->storageFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        file_put_contents($this->storageFile, json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX);
    }
}
