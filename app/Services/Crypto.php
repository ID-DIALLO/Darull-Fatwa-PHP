<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Chiffrement symétrique des paramètres secrets (§12.4) : AES-256-GCM avec la clé APP_KEY (.env).
 * Format stocké : base64( nonce 12 octets . tag 16 octets . texte chiffré ).
 */
final class Crypto
{
    private const CIPHER = 'aes-256-gcm';

    public static function encrypt(string $plain): string
    {
        $key = self::key();
        $nonce = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, self::CIPHER, $key, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if ($cipher === false) {
            throw new \RuntimeException('Chiffrement impossible');
        }

        return base64_encode($nonce . $tag . $cipher);
    }

    /** Retourne null si la valeur est illisible (clé changée, données corrompues). */
    public static function decrypt(?string $encoded): ?string
    {
        if ($encoded === null || $encoded === '') {
            return null;
        }
        $raw = base64_decode($encoded, true);
        if ($raw === false || strlen($raw) < 28) {
            return null;
        }
        $plain = openssl_decrypt(substr($raw, 28), self::CIPHER, self::key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));

        return $plain === false ? null : $plain;
    }

    private static function key(): string
    {
        $configured = (string) config('app.key', '');
        if (str_starts_with($configured, 'base64:')) {
            $configured = (string) base64_decode(substr($configured, 7), true);
        }
        if (strlen($configured) < 32) {
            throw new \RuntimeException('APP_KEY manquante ou trop courte : lancez php bin/install.php');
        }

        return hash('sha256', $configured, true);
    }
}
