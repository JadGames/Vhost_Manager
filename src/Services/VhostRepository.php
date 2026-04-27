<?php

declare(strict_types=1);

namespace App\Services;

final class VhostRepository
{
    public function __construct(private readonly string $storageFile)
    {
    }

    public function all(): array
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

    public function put(string $domain, array $record): void
    {
        $all = $this->all();
        $all[$domain] = $record;
        $this->save($all);
    }

    public function remove(string $domain): void
    {
        $all = $this->all();
        unset($all[$domain]);
        $this->save($all);
    }

    private function save(array $data): void
    {
        $dir = dirname($this->storageFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        ksort($data);
        file_put_contents($this->storageFile, json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX);
    }
}
