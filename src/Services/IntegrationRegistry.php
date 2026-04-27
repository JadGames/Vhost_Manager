<?php

declare(strict_types=1);

namespace App\Services;

final class IntegrationRegistry
{
    /**
     * @return array<string, array{label:string, category:string, icon:string, description:string, fields:list<array{name:string,label:string,type:string,required:bool,placeholder:string,default:string}>}>
     */
    public static function providers(): array
    {
        return [
            'npm' => [
                'label'       => 'Nginx Proxy Manager',
                'category'    => 'proxy',
                'icon'        => 'fa-network-wired',
                'description' => 'Reverse proxy with automatic Let\'s Encrypt SSL support.',
                'fields'      => [
                    ['name' => 'base_url',      'label' => 'Base URL',      'type' => 'url',    'required' => true,  'placeholder' => 'http://npm:81',     'default' => 'http://npm:81'],
                    ['name' => 'identity',      'label' => 'Email',         'type' => 'email',  'required' => true,  'placeholder' => 'admin@example.com', 'default' => ''],
                    ['name' => 'secret',        'label' => 'Password',      'type' => 'password','required' => true, 'placeholder' => '',                  'default' => ''],
                    ['name' => 'forward_host',  'label' => 'Forward Host',  'type' => 'text',   'required' => true,  'placeholder' => '127.0.0.1',         'default' => '127.0.0.1'],
                    ['name' => 'forward_port',  'label' => 'Forward Port',  'type' => 'number', 'required' => true,  'placeholder' => '80',                'default' => '80'],
                ],
            ],
            'cloudflare' => [
                'label'       => 'Cloudflare',
                'category'    => 'dns',
                'icon'        => 'fa-cloud',
                'description' => 'DNS management and DDoS protection via the Cloudflare API.',
                'fields'      => [
                    ['name' => 'api_token',  'label' => 'API Token',             'type' => 'password', 'required' => true,  'placeholder' => '',        'default' => ''],
                    ['name' => 'zone_id',    'label' => 'Zone ID',               'type' => 'text',     'required' => true,  'placeholder' => '32-char hex', 'default' => ''],
                    ['name' => 'record_ip',  'label' => 'Record IP',             'type' => 'text',     'required' => true,  'placeholder' => '1.2.3.4', 'default' => ''],
                    ['name' => 'ttl',        'label' => 'TTL (seconds)',          'type' => 'number',   'required' => true,  'placeholder' => '120',     'default' => '120'],
                    ['name' => 'proxied',    'label' => 'Proxy records by default', 'type' => 'checkbox', 'required' => false, 'placeholder' => '',      'default' => '0'],
                ],
            ],
        ];
    }

    /**
     * @param list<array{id:string,name:string,provider:string,category:string,settings:array<string,string>}> $integrations
     * @return list<array{id:string,name:string,provider:string,category:string,settings:array<string,string>}>
     */
    public static function filterByCategory(array $integrations, string $category): array
    {
        return array_values(array_filter($integrations, static fn (array $i): bool => ($i['category'] ?? '') === $category));
    }
}
