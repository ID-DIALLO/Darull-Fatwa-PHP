<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;

/**
 * Anti-robot Cloudflare Turnstile (§4.1, §12.1). Désactivé tant que les clés ne sont pas renseignées
 * dans .env (TURNSTILE_SITE_KEY / TURNSTILE_SECRET_KEY) : la vérification passe alors toujours.
 * Un champ piège (honeypot) complète la protection dans les formulaires.
 */
final class Turnstile
{
    public const FIELD = 'cf-turnstile-response';
    public const HONEYPOT = 'website';

    public static function enabled(): bool
    {
        return self::siteKey() !== '' && self::secretKey() !== '';
    }

    /** Clé de site : paramètre de l'administration (turnstile_site_key) sinon .env. */
    public static function siteKey(): string
    {
        return (string) (\App\Models\Setting::get('turnstile_site_key') ?: config('app.turnstile.site_key', ''));
    }

    /** Clé secrète : paramètre chiffré de l'administration sinon .env. */
    private static function secretKey(): string
    {
        return (string) (\App\Models\Setting::secret('turnstile_secret_key') ?? config('app.turnstile.secret_key', ''));
    }

    /**
     * Vérifie le jeton du widget et le champ piège.
     *
     * @param array<string, mixed> $input Données du formulaire
     */
    public static function passes(array $input, string $ip): bool
    {
        if (trim((string) ($input[self::HONEYPOT] ?? '')) !== '') {
            Logger::info('Formulaire rejeté : champ piège rempli', ['ip' => $ip]);

            return false;
        }
        if (!self::enabled()) {
            return true;
        }
        $token = (string) ($input[self::FIELD] ?? '');
        if ($token === '' || !function_exists('curl_init')) {
            return $token !== '' && !function_exists('curl_init');
        }

        $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query(['secret' => self::secretKey(), 'response' => $token, 'remoteip' => $ip]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        if (!is_string($body)) {
            Logger::warning('Turnstile injoignable : vérification ignorée');

            return true;
        }
        $data = json_decode($body, true);

        return is_array($data) && ($data['success'] ?? false) === true;
    }
}
