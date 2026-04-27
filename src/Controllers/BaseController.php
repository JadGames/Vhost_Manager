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
        $appVersion = (string) $this->config->get('APP_VERSION', 'dev');

        // Extract data first so callers can override any variable, then set
        // $username from session (the logged-in user) which is used by the layout.
        extract($data, EXTR_OVERWRITE);
        $username = $_SESSION['username'] ?? null;
        $contentTemplate = $template;

        include __DIR__ . '/../../templates/layouts/main.php';
    }

    protected function redirect(string $route): never
    {
        header('Location: /?route=' . urlencode($route));
        exit;
    }
}
