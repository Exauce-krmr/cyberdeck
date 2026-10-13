<?php

declare(strict_types=1);

namespace App\Core;

/** Fine couche autour de $_SESSION ; le cookie de connexion est celui de la session PHP. */
final class Session
{
    public static function start(int $days): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $lifetime = $days * 86400;
        ini_set('session.gc_maxlifetime', (string) $lifetime);
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => !empty($_SERVER['HTTPS']),
        ]);
        session_start();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    /** Nouvel identifiant de session à chaque connexion, contre la fixation de session. */
    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function clear(): void
    {
        $_SESSION = [];
        self::regenerate();
    }
}
