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
                'description' => 'Reverse proxy with a dedicated VHM runtime account provisioned from one-time admin credentials.',
                'fields'      => [
                    ['name' => 'base_url',      'label' => 'Base URL',      'type' => 'url',    'required' => true,  'placeholder' => 'http://npm:81',     'default' => 'http://npm:81'],
                    ['name' => 'identity',      'label' => 'Runtime Identity', 'type' => 'email',  'required' => false, 'placeholder' => '',                  'default' => ''],
                    ['name' => 'secret',        'label' => 'Runtime Secret', 'type' => 'password','required' => false, 'placeholder' => '',                  'default' => ''],
                    ['name' => 'forward_host',  'label' => 'Forward Host',  'type' => 'text',   'required' => true,  'placeholder' => '127.0.0.1',         'default' => '127.0.0.1'],
                    ['name' => 'forward_port',  'label' => 'Forward Port',  'type' => 'number', 'required' => true,  'placeholder' => '80',                'default' => '80'],
                    ['name' => 'bootstrap_key', 'label' => 'Bootstrap Key', 'type' => 'text',   'required' => false, 'placeholder' => '',                  'default' => ''],
                ],
            ],
            'cloudflare' => [
                'label'       => 'Cloudflare',
                'category'    => 'dns',
                'icon'        => 'fa-cloud',
                'description' => 'Enable integration only. Per-domain Cloudflare settings are configured from the Domains page.',
                'fields'      => [],
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
