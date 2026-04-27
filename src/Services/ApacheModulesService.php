<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use RuntimeException;

final class ApacheModulesService
{
    /**
     * @var array<string, array{label:string,description:string,required:bool,keywords:string}>
     */
    private const CATALOG = [
        'rewrite' => [
            'label' => 'Rewrite',
            'description' => 'URL rewriting for front-controller routing and friendly paths.',
            'required' => true,
            'keywords' => 'routing htaccess urls',
        ],
        'headers' => [
            'label' => 'Headers',
            'description' => 'Set or modify HTTP headers such as cache and security headers.',
            'required' => false,
            'keywords' => 'security caching response headers',
        ],
        'ssl' => [
            'label' => 'SSL',
            'description' => 'HTTPS/TLS support for Apache virtual hosts.',
            'required' => false,
            'keywords' => 'tls https certificates',
        ],
        'proxy' => [
            'label' => 'Proxy',
            'description' => 'Core reverse proxy support for upstream applications.',
            'required' => false,
            'keywords' => 'reverse proxy upstream gateway',
        ],
        'proxy_http' => [
            'label' => 'Proxy HTTP',
            'description' => 'HTTP and HTTPS proxying for backend apps.',
            'required' => false,
            'keywords' => 'proxy backend http https',
        ],
        'http2' => [
            'label' => 'HTTP/2',
            'description' => 'Enable HTTP/2 support where your Apache build allows it.',
            'required' => false,
            'keywords' => 'h2 performance tls',
        ],
        'expires' => [
            'label' => 'Expires',
            'description' => 'Set asset expiry and browser caching rules.',
            'required' => false,
            'keywords' => 'cache browser assets static',
        ],
        'deflate' => [
            'label' => 'Deflate',
            'description' => 'Compress responses to reduce payload size.',
            'required' => true,
            'keywords' => 'compression gzip performance',
        ],
        'remoteip' => [
            'label' => 'Remote IP',
            'description' => 'Honor client IPs passed from trusted reverse proxies.',
            'required' => false,
            'keywords' => 'proxy ip forwarded for real client',
        ],
        'setenvif' => [
            'label' => 'SetEnvIf',
            'description' => 'Conditionally set environment variables from request attributes.',
            'required' => true,
            'keywords' => 'conditions env request matching',
        ],
        'dir' => [
            'label' => 'Directory Index',
            'description' => 'Serve index files such as index.php and index.html automatically.',
            'required' => true,
            'keywords' => 'index directoryindex index.php',
        ],
        'alias' => [
            'label' => 'Alias',
            'description' => 'Map URLs to filesystem paths outside the document root.',
            'required' => true,
            'keywords' => 'mapping filesystem path url',
        ],
        'mime' => [
            'label' => 'MIME',
            'description' => 'Send correct content types for static assets and downloads.',
            'required' => true,
            'keywords' => 'content-type static files downloads',
        ],
    ];

    public function __construct(private readonly Config $config)
    {
    }

    /**
     * @return list<array{module:string,label:string,description:string,required:bool,enabled:bool,keywords:string,required_message:string}>
     */
    public function listModules(): array
    {
        [$exitCode, $output] = $this->runHelper('list-modules');
        if ($exitCode !== 0) {
            throw new RuntimeException('Failed to load Apache modules: ' . $output);
        }

        $states = [];
        foreach (preg_split('/\r?\n/', $output) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            [$module, $state] = array_pad(explode('|', $line, 2), 2, '');
            if ($module === '' || $state === '') {
                continue;
            }

            $states[$module] = $state === 'enabled';
        }

        $modules = [];
        foreach (self::CATALOG as $module => $meta) {
            $modules[] = [
                'module' => $module,
                'label' => $meta['label'],
                'description' => $meta['description'],
                'required' => $meta['required'],
                'enabled' => (bool) ($states[$module] ?? false),
                'keywords' => $meta['keywords'],
                'required_message' => $meta['required'] ? 'Required for Vhost Manager to run correctly.' : '',
            ];
        }

        return $modules;
    }

    public function setEnabled(string $module, bool $enabled): void
    {
        $module = strtolower(trim($module));
        if (!array_key_exists($module, self::CATALOG)) {
            throw new RuntimeException('Unsupported Apache module.');
        }

        if ($enabled) {
            [$exitCode, $output] = $this->runHelper('enable-module', $module);
        } else {
            if (self::CATALOG[$module]['required']) {
                throw new RuntimeException('This module is required and cannot be disabled.');
            }

            [$exitCode, $output] = $this->runHelper('disable-module', $module);
        }

        if ($exitCode !== 0) {
            throw new RuntimeException($output !== '' ? $output : 'Apache module update failed.');
        }
    }

    /**
     * @return array{0:int,1:string}
     */
    private function runHelper(string $action, ?string $module = null): array
    {
        $helper = (string) $this->config->get('PRIV_HELPER', '/usr/local/sbin/vhost-admin-helper');
        $command = sprintf('sudo %s %s', escapeshellarg($helper), escapeshellarg($action));
        if ($module !== null) {
            $command .= ' ' . escapeshellarg($module);
        }

        $output = [];
        $exitCode = 0;
        exec($command . ' 2>&1', $output, $exitCode);

        return [$exitCode, implode("\n", $output)];
    }
}