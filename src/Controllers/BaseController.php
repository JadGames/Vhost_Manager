<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Session;
use App\Services\SettingsStore;

abstract class BaseController
{
    public function __construct(
        protected readonly Config $config,
        protected readonly SettingsStore $settingsStore
    ) {
    }

    protected function render(string $template, array $data = []): void
    {
        $flash = Session::consumeFlash();
        $docrootDetection = $_SESSION['docroot_detection'] ?? null;
        unset($_SESSION['docroot_detection']);
        $csrfToken = $_SESSION['csrf_token'] ?? '';
        $cspNonce = $_SERVER['CSP_NONCE'] ?? '';
        $appVersion = (string) $this->config->get('APP_VERSION', 'dev');

        extract($data, EXTR_OVERWRITE);
        $username = $_SESSION['username'] ?? null;
        $displayName = is_string($username) ? $username : '';
        $accountRole = 'User';

        if (is_string($username) && $username !== '') {
            $identity = strtolower(trim($username));
            $userRecord = $this->settingsStore->userGet($identity);

            if ($userRecord !== null) {
                $fullName = trim((string) ($userRecord['full_name'] ?? ''));
                $displayName = $fullName !== '' ? $fullName : $username;

                if ($userRecord['is_primary']) {
                    $accountRole = 'Primary Admin';
                } else {
                    $accountRole = ($userRecord['account_type'] ?? 'user') === 'admin' ? 'Admin' : 'User';
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
