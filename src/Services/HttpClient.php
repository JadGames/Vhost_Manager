<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class HttpClient
{
    public function __construct(private readonly bool $verifySsl = true)
    {
    }

    /** @return array{status:int,body:mixed} */
    public function get(string $url, array $headers = []): array
    {
        return $this->request('GET', $url, null, $headers);
    }

    /** @return array{status:int,body:mixed} */
    public function post(string $url, mixed $data, array $headers = []): array
    {
        return $this->request('POST', $url, $data, $headers);
    }

    /** @return array{status:int,body:mixed} */
    public function delete(string $url, array $headers = []): array
    {
        return $this->request('DELETE', $url, null, $headers);
    }

    /** @return array{status:int,body:mixed} */
    public function put(string $url, mixed $data, array $headers = []): array
    {
        return $this->request('PUT', $url, $data, $headers);
    }

    private function request(string $method, string $url, mixed $data, array $headers): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('The curl PHP extension is required but not available.');
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('curl_init failed.');
        }

        $allHeaders = array_merge(['Content-Type: application/json', 'Accept: application/json'], $headers);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => $this->verifySsl,
            CURLOPT_SSL_VERIFYHOST => $this->verifySsl ? 2 : 0,
            CURLOPT_HTTPHEADER     => $allHeaders,
            CURLOPT_CUSTOMREQUEST  => $method,
        ]);

        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_THROW_ON_ERROR));
        }

        $response = curl_exec($ch);
        $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException("HTTP {$method} {$url} failed: {$error}");
        }

        $body = ($response !== '') ? json_decode((string) $response, true) : null;

        return ['status' => $status, 'body' => $body];
    }
}
