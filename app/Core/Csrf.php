<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Jetons CSRF (§12.1) : un jeton par session, vérifié sur POST/PUT/PATCH/DELETE
 * (champ « _token » ou en-tête « X-CSRF-Token » pour les appels JavaScript).
 */
final class Csrf
{
    private const KEY = '_csrf_token';

    public static function token(): string
    {
        $token = Session::get(self::KEY);
        if (!is_string($token) || strlen($token) !== 64) {
            $token = bin2hex(random_bytes(32));
            Session::set(self::KEY, $token);
        }

        return $token;
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(self::token()) . '">';
    }

    public static function verify(?string $token): bool
    {
        $expected = Session::get(self::KEY);

        return is_string($expected) && is_string($token) && $token !== '' && hash_equals($expected, $token);
    }

    /** Nouveau jeton (après connexion / déconnexion). */
    public static function refresh(): string
    {
        Session::forget(self::KEY);

        return self::token();
    }
}
