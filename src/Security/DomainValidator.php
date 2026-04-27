<?php

declare(strict_types=1);

namespace App\Security;

final class DomainValidator
{
    public static function isValid(string $domain): bool
    {
        $domain = strtolower(trim($domain));

        if ($domain === '' || strlen($domain) > 253) {
            return false;
        }

        if (!preg_match('/^(?=.{1,253}$)(?!-)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $domain)) {
            return false;
        }

        return true;
    }
}
