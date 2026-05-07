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
        $enableIntegrations = $this->config->getBool('ENABLE_INTEGRATIONS', true);

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
                    $accountType = strtolower(trim((string) ($userRecord['account_type'] ?? 'user')));
                    $accountRole = $accountType === 'admin' ? 'Admin' : 'User';
                }
            }
        }

        $contentTemplate = $template;
        $isAdmin = in_array($accountRole, ['Admin', 'Primary Admin'], true);
        Session::setIsAdmin($isAdmin);
        $pendingModuleRequests = 0;
        try {
            $pendingModuleRequests = $isAdmin ? $this->settingsStore->moduleRequestCount() : 0;
        } catch (\Throwable) {
            // table may not exist yet before first initialize
        }

        $unreadNotifications = 0;
        $notifications = [];
        $notificationPollSeconds = max(30, (int) $this->config->get('NOTIFICATIONS_POLL_SECONDS', 120));
        try {
            $identity = is_string($username) ? strtolower(trim($username)) : '';
            $notifications = $this->settingsStore->notificationListForUser($identity, $isAdmin, 10);
            $unreadNotifications = $this->settingsStore->notificationUnreadCountForUser($identity, $isAdmin);
        } catch (\Throwable) {
            $notifications = [];
            $unreadNotifications = 0;
        }

        include __DIR__ . '/../../templates/layouts/main.php';
    }

    protected function redirect(string $route): never
    {
        header('Location: /?route=' . urlencode($route));
        exit;
    }
}
