<?php

declare(strict_types=1);

namespace App\Security;

final class PathValidator
{
    public static function normalize(string $path): string
    {
        $path = preg_replace('#/+#', '/', trim($path)) ?? '';
        if ($path === '') {
            return '';
        }

        $segments = explode('/', $path);
        $safeSegments = [];

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($safeSegments);
                continue;
            }

            $safeSegments[] = $segment;
        }

        return '/' . implode('/', $safeSegments);
    }

    public static function isPathWithinAllowedBases(string $path, array $bases): bool
    {
        $normalizedPath = self::normalize($path);
        if ($normalizedPath === '/') {
            return false;
        }

        foreach ($bases as $base) {
            $normalizedBase = rtrim(self::normalize($base), '/');
            if ($normalizedBase === '') {
                continue;
            }

            if ($normalizedPath === $normalizedBase || str_starts_with($normalizedPath, $normalizedBase . '/')) {
                return true;
            }
        }

        return false;
    }
}
