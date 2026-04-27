<?php

declare(strict_types=1);

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function asset_url(string $path): string
{
    $cleanPath = '/' . ltrim($path, '/');
    $assetFile = __DIR__ . '/../../public' . $cleanPath;

    if (!is_file($assetFile)) {
        return $cleanPath;
    }

    $mtime = filemtime($assetFile);
    if ($mtime === false) {
        return $cleanPath;
    }

    return $cleanPath . '?v=' . rawurlencode((string) $mtime);
}

function client_ip(array $server, string $trustedProxiesRaw = ''): string
{
    $remoteAddr = trim((string) ($server['REMOTE_ADDR'] ?? ''));
    if (!is_valid_ip($remoteAddr)) {
        return 'unknown';
    }

    $trustedProxies = parse_trusted_proxies($trustedProxiesRaw);
    if ($trustedProxies === [] || !ip_matches_any_proxy($remoteAddr, $trustedProxies)) {
        return $remoteAddr;
    }

    $candidates = [];

    $forwardedFor = trim((string) ($server['HTTP_X_FORWARDED_FOR'] ?? ''));
    if ($forwardedFor !== '') {
        $parts = array_map('trim', explode(',', $forwardedFor));
        foreach ($parts as $part) {
            if ($part !== '') {
                $candidates[] = $part;
            }
        }
    }

    $xRealIp = trim((string) ($server['HTTP_X_REAL_IP'] ?? ''));
    if ($xRealIp !== '') {
        $candidates[] = $xRealIp;
    }

    foreach ($candidates as $candidate) {
        if (is_valid_ip($candidate)) {
            return $candidate;
        }
    }

    return $remoteAddr;
}

/**
 * @return array<int, string>
 */
function parse_trusted_proxies(string $raw): array
{
    $items = array_map('trim', explode(',', $raw));
    $items = array_values(array_filter($items, static fn (string $v): bool => $v !== ''));

    return array_values(array_unique($items));
}

function is_valid_ip(string $ip): bool
{
    return filter_var($ip, FILTER_VALIDATE_IP) !== false;
}

/**
 * @param array<int, string> $trustedProxies
 */
function ip_matches_any_proxy(string $ip, array $trustedProxies): bool
{
    foreach ($trustedProxies as $proxy) {
        if (ip_in_cidr($ip, $proxy)) {
            return true;
        }
    }

    return false;
}

function ip_in_cidr(string $ip, string $cidr): bool
{
    $cidr = trim($cidr);
    if ($cidr === '') {
        return false;
    }

    if (strpos($cidr, '/') === false) {
        return hash_equals($cidr, $ip);
    }

    [$subnet, $maskBitsRaw] = explode('/', $cidr, 2);
    $subnet = trim($subnet);
    $maskBits = (int) trim($maskBitsRaw);

    $ipBin = inet_pton($ip);
    $subnetBin = inet_pton($subnet);
    if ($ipBin === false || $subnetBin === false) {
        return false;
    }

    $len = strlen($ipBin);
    if ($len !== strlen($subnetBin)) {
        return false;
    }

    $maxBits = $len * 8;
    if ($maskBits < 0 || $maskBits > $maxBits) {
        return false;
    }

    $fullBytes = intdiv($maskBits, 8);
    $remainingBits = $maskBits % 8;

    if ($fullBytes > 0 && substr($ipBin, 0, $fullBytes) !== substr($subnetBin, 0, $fullBytes)) {
        return false;
    }

    if ($remainingBits === 0) {
        return true;
    }

    $mask = (0xFF << (8 - $remainingBits)) & 0xFF;
    $ipByte = ord($ipBin[$fullBytes]);
    $subnetByte = ord($subnetBin[$fullBytes]);

    return (($ipByte & $mask) === ($subnetByte & $mask));
}

/**
 * @return array<int, string>
 */
function parse_docroot_bases(string $raw): array
{
    $items = array_map('trim', explode(',', $raw));
    $bases = [];

    foreach ($items as $item) {
        if ($item === '' || !str_starts_with($item, '/')) {
            continue;
        }

        if (!in_array($item, $bases, true)) {
            $bases[] = $item;
        }
    }

    return $bases;
}

/**
 * @param array<int, string> $stored
 * @param array<int, string> $compose
 * @return array<int, string>
 */
function merge_docroot_bases(array $stored, array $compose): array
{
    $merged = [];

    foreach ([$stored, $compose] as $set) {
        foreach ($set as $base) {
            $value = trim((string) $base);
            if ($value === '' || !str_starts_with($value, '/')) {
                continue;
            }

            if (!in_array($value, $merged, true)) {
                $merged[] = $value;
            }
        }
    }

    return $merged;
}

/**
 * @return array<int, string>
 */
function password_policy_errors(string $password): array
{
    $errors = [];

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }

    if (preg_match('/[A-Z]/', $password) !== 1) {
        $errors[] = 'Password must include at least one uppercase letter.';
    }

    if (preg_match('/[^a-zA-Z0-9]/', $password) !== 1) {
        $errors[] = 'Password must include at least one special character.';
    }

    return $errors;
}
