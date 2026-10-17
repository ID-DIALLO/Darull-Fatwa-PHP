<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Jetons à usage unique (table user_tokens) : vérification d'e-mail, réinitialisation de mot de passe,
 * « se souvenir de moi ». Seule l'empreinte SHA-256 est stockée ; le jeton en clair n'existe que dans
 * le lien envoyé ou le cookie (§12.1).
 */
final class TokenService
{
    public const EMAIL_VERIFY = 'email_verify';
    public const PASSWORD_RESET = 'password_reset';
    public const REMEMBER = 'remember';
    public const INVITE = 'invite';

    /** Crée un jeton et retourne sa valeur en clair (64 caractères hexadécimaux). */
    public static function create(int $userId, string $type, int $ttlSeconds, bool $replaceExisting = true): string
    {
        if ($replaceExisting) {
            Database::execute('DELETE FROM user_tokens WHERE user_id = ? AND type = ?', [$userId, $type]);
        }
        $token = bin2hex(random_bytes(32));
        Database::execute(
            'INSERT INTO user_tokens (user_id, type, token_hash, expires_at) VALUES (?, ?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? SECOND))',
            [$userId, $type, self::hash($token), $ttlSeconds],
        );

        return $token;
    }

    /**
     * Retrouve un jeton valide (non expiré, non utilisé).
     *
     * @return array{id: int, user_id: int, type: string}|null
     */
    public static function find(string $token, string $type): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        $row = Database::fetch(
            'SELECT id, user_id, type FROM user_tokens
             WHERE token_hash = ? AND type = ? AND used_at IS NULL AND expires_at > UTC_TIMESTAMP() LIMIT 1',
            [self::hash($token), $type],
        );

        return $row === null ? null : ['id' => (int) $row['id'], 'user_id' => (int) $row['user_id'], 'type' => (string) $row['type']];
    }

    /** Consomme un jeton (usage unique). Retourne l'identifiant de l'utilisateur, ou null si invalide. */
    public static function consume(string $token, string $type): ?int
    {
        $found = self::find($token, $type);
        if ($found === null) {
            return null;
        }
        Database::execute('UPDATE user_tokens SET used_at = UTC_TIMESTAMP() WHERE id = ?', [$found['id']]);

        return $found['user_id'];
    }

    public static function delete(string $token): void
    {
        Database::execute('DELETE FROM user_tokens WHERE token_hash = ?', [self::hash($token)]);
    }

    public static function deleteAll(int $userId, ?string $type = null): void
    {
        if ($type === null) {
            Database::execute('DELETE FROM user_tokens WHERE user_id = ?', [$userId]);
        } else {
            Database::execute('DELETE FROM user_tokens WHERE user_id = ? AND type = ?', [$userId, $type]);
        }
    }

    /** Nettoyage des jetons expirés ou utilisés (cron). */
    public static function purge(): int
    {
        return Database::execute('DELETE FROM user_tokens WHERE expires_at < UTC_TIMESTAMP() OR used_at IS NOT NULL');
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
