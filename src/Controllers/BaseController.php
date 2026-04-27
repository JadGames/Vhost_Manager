<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Session;

abstract class BaseController
{
    public function __construct(protected readonly Config $config)
    {
    }

    protected function render(string $template, array $data = []): void
    {
        $flash = Session::consumeFlash();
        $docrootDetection = $_SESSION['docroot_detection'] ?? null;
        unset($_SESSION['docroot_detection']);
        $csrfToken = $_SESSION['csrf_token'] ?? '';
        $cspNonce = $_SERVER['CSP_NONCE'] ?? '';
        $appVersion = (string) $this->config->get('APP_VERSION', 'dev');

        // Extract data first so callers can override any variable, then set
        // $username from session (the logged-in user) which is used by the layout.
        extract($data, EXTR_OVERWRITE);
        $username = $_SESSION['username'] ?? null;
        $displayName = is_string($username) ? $username : '';
        $accountRole = 'User';

        if (is_string($username) && $username !== '') {
            $identity = strtolower(trim($username));
            $adminEmail = strtolower(trim((string) $this->config->get('ADMIN_USER', 'admin@example.com')));

            if ($identity === $adminEmail) {
                $adminFullName = trim((string) $this->config->get('ADMIN_FULL_NAME', ''));
                $displayName = $adminFullName !== '' ? $adminFullName : $username;
                $accountRole = 'Primary Admin';
            } else {
                $usersMetaRaw = (string) $this->config->get('USERS_META_JSON', '');
                $usersMeta = json_decode($usersMetaRaw, true);
                if (is_array($usersMeta) && isset($usersMeta[$identity]) && is_array($usersMeta[$identity])) {
                    $fullName = trim((string) ($usersMeta[$identity]['full_name'] ?? ''));
                    $displayName = $fullName !== '' ? $fullName : $username;
                    $accountType = (string) ($usersMeta[$identity]['account_type'] ?? 'user');
                    $accountRole = $accountType === 'admin' ? 'Admin' : 'User';
                }
            }
        }

        $contentTemplate = $template;

        include __DIR__ . '/../../templates/layouts/main.php';
    }

    protected function redirect(string $route): never
    {
        header('Location: /?route=' . urlencode($route));
        exit;
    }
}
