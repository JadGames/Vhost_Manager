<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Session;
use App\Security\Csrf;
use App\Services\AuthService;
use App\Services\SettingsStore;
use RuntimeException;

final class AuthController extends BaseController
{
    public function __construct(
        Config $config,
        private readonly AuthService $authService,
        private readonly Csrf $csrf,
        private readonly SettingsStore $settingsStore
    ) {
        parent::__construct($config);
    }

    public function showLogin(): void
    {
        if (Session::isAuthenticated()) {
            $this->redirect('overview');
        }

        $this->render('auth/login.php', [
            'csrfToken' => $this->csrf->token(),
        ]);
    }

    public function login(): void
    {
        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            Session::setFlash('error', 'Invalid CSRF token.');
            $this->redirect('login');
        }

        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $trustedProxies = (string) $this->config->get('TRUSTED_PROXIES', '');
        $ipAddress = client_ip($_SERVER, $trustedProxies);

        [$ok, $message] = $this->authService->login($email, $password, $ipAddress);
        if (!$ok) {
            Session::setFlash('error', $message);
            $this->redirect('login');
        }

        Session::login($email);
        $this->handleDocrootDetectionAfterLogin();
        Session::setFlash('success', 'Logged in successfully.');
        $this->redirect('overview');
    }

    private function handleDocrootDetectionAfterLogin(): void
    {
        $currentBases = parse_docroot_bases((string) $this->config->get('ALLOWED_DOCROOT_BASES', '/var/www'));
        if ($currentBases === []) {
            return;
        }

        $lastSeenBases = parse_docroot_bases((string) $this->config->get('DOCROOT_BASES_LAST_SEEN', ''));
        $notificationsEnabled = $this->config->getBool('DOCROOT_BASES_NOTIFY', true);

        if ($lastSeenBases !== []) {
            $newBases = array_values(array_diff($currentBases, $lastSeenBases));
            if ($notificationsEnabled && $newBases !== []) {
                $_SESSION['docroot_detection'] = [
                    'new_bases' => $newBases,
                    'allowed_bases' => $currentBases,
                    'default_base' => (string) $this->config->get('DEFAULT_DOCROOT_BASE', $currentBases[0]),
                ];
            }
        }

        try {
            $this->settingsStore->setMany([
                'DOCROOT_BASES_LAST_SEEN' => implode(',', $currentBases),
            ]);
        } catch (RuntimeException) {
            // Non-fatal: detection can retry on next login.
        }
    }

    public function logout(): void
    {
        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            Session::setFlash('error', 'Invalid CSRF token.');
            $this->redirect('overview');
        }

        Session::logout();
        header('Location: /?route=login');
        exit;
    }
}
