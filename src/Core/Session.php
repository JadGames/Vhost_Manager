<?php

declare(strict_types=1);

namespace App\Core;

final class Session
{
    public static function start(Config $config): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name((string) $config->get('SESSION_NAME', 'APHOSTSESSID'));

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $config->getBool('APP_HTTPS', false),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');

        session_start();

        $idleTimeout = (int) $config->get('SESSION_IDLE_TIMEOUT', 1800);
        $lastActivity = $_SESSION['last_activity'] ?? null;

        if (is_int($lastActivity) && (time() - $lastActivity) > $idleTimeout) {
            self::destroy();
            self::redirectToLogin();
        }

        $_SESSION['last_activity'] = time();
    }

    public static function isAuthenticated(): bool
    {
        return (bool) ($_SESSION['auth'] ?? false);
    }

    public static function login(string $username): void
    {
        session_regenerate_id(true);
        $_SESSION['auth'] = true;
        $_SESSION['username'] = $username;
    }

    public static function logout(): void
    {
        self::destroy();
    }

    public static function destroy(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
        }

        session_destroy();
    }

    public static function requireAuth(): void
    {
        if (!self::isAuthenticated()) {
            self::redirectToLogin();
        }
    }

    public static function setFlash(string $type, string $message): void
    {
        $_SESSION['flash'] = [
            'type' => $type,
            'message' => $message,
        ];
    }

    public static function consumeFlash(): ?array
    {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        return is_array($flash) ? $flash : null;
    }

    /**
     * @param array<string,string> $errors
     */
    public static function setFieldErrors(array $errors): void
    {
        $_SESSION['field_errors'] = $errors;
    }

    /**
     * @return array<string,string>
     */
    public static function consumeFieldErrors(): array
    {
        $errors = $_SESSION['field_errors'] ?? [];
        unset($_SESSION['field_errors']);

        return is_array($errors) ? $errors : [];
    }

    private static function redirectToLogin(): void
    {
        header('Location: /?route=login');
        exit;
    }
}
