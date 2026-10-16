<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Session sécurisée (§12.1) : cookies HttpOnly, Secure (en HTTPS), SameSite=Lax,
 * expiration après inactivité, régénération de l'identifiant à la connexion.
 * Hors contexte web (CLI, tests), un simple tableau en mémoire est utilisé.
 */
final class Session
{
    /** @var array<string, mixed> Stockage en mémoire quand la session PHP n'est pas démarrée */
    private static array $memory = [];

    private static bool $started = false;

    public static function start(string $name, bool $secure, int $idleMinutes = 120): void
    {
        if (self::$started || PHP_SAPI === 'cli' || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;

            return;
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', (string) ($idleMinutes * 60));
        session_name($name);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
        self::$started = true;

        // Expiration après inactivité
        $now = time();
        $last = (int) ($_SESSION['_last_activity'] ?? $now);
        if ($now - $last > $idleMinutes * 60) {
            self::invalidate();
        }
        $_SESSION['_last_activity'] = $now;

        self::ageFlashData();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $store = self::store();

        return $store[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        if (self::usingNative()) {
            $_SESSION[$key] = $value;
        } else {
            self::$memory[$key] = $value;
        }
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::store());
    }

    public static function forget(string $key): void
    {
        if (self::usingNative()) {
            unset($_SESSION[$key]);
        } else {
            unset(self::$memory[$key]);
        }
    }

    /** Lit puis supprime une valeur. */
    public static function pull(string $key, mixed $default = null): mixed
    {
        $value = self::get($key, $default);
        self::forget($key);

        return $value;
    }

    /** Valeur disponible uniquement pour la requête suivante (messages, anciennes saisies). */
    public static function flash(string $key, mixed $value): void
    {
        $new = (array) self::get('_flash_new', []);
        $new[$key] = $value;
        self::set('_flash_new', $new);
    }

    public static function getFlash(string $key, mixed $default = null): mixed
    {
        $new = (array) self::get('_flash_new', []);
        $old = (array) self::get('_flash_old', []);

        return $new[$key] ?? $old[$key] ?? $default;
    }

    /** Nouvel identifiant de session (connexion, changement de rôle). */
    public static function regenerate(): void
    {
        if (self::usingNative()) {
            session_regenerate_id(true);
        }
    }

    /** Vide la session et change d'identifiant (déconnexion, expiration). */
    public static function invalidate(): void
    {
        if (self::usingNative()) {
            $_SESSION = [];
            session_regenerate_id(true);
        } else {
            self::$memory = [];
        }
    }

    /** Réinitialise le stockage en mémoire (tests). */
    public static function reset(): void
    {
        self::$memory = [];
    }

    private static function ageFlashData(): void
    {
        $_SESSION['_flash_old'] = $_SESSION['_flash_new'] ?? [];
        $_SESSION['_flash_new'] = [];
    }

    private static function usingNative(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    /** @return array<string, mixed> */
    private static function store(): array
    {
        return self::usingNative() ? $_SESSION : self::$memory;
    }
}
