<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;

/**
 * Compteur de requêtes par clé (table rate_limits), fenêtre fixe de $decaySeconds secondes.
 * Utilisé par ThrottleMiddleware et, en Phase 1, par la connexion, l'inscription et l'OTP.
 */
final class RateLimiter
{
    /** @return array{allowed: bool, remaining: int, retry_after: int} */
    public static function hit(string $key, int $maxAttempts, int $decaySeconds): array
    {
        $key = mb_substr($key, 0, 190);
        try {
            // Insertion ou incrément atomique ; la fenêtre repart à zéro une fois expirée.
            Database::execute(
                'INSERT INTO rate_limits (rl_key, hits, reset_at) VALUES (?, 1, DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? SECOND))
                 ON DUPLICATE KEY UPDATE
                   hits = IF(reset_at <= UTC_TIMESTAMP(), 1, hits + 1),
                   reset_at = IF(reset_at <= UTC_TIMESTAMP(), DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? SECOND), reset_at)',
                [$key, $decaySeconds, $decaySeconds],
            );
            $row = Database::fetch(
                'SELECT hits, GREATEST(0, TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), reset_at)) AS retry FROM rate_limits WHERE rl_key = ?',
                [$key],
            );
        } catch (\PDOException $e) {
            // Sans base de données, on ne bloque pas le site mais on le signale.
            Logger::warning('Limitation indisponible : ' . $e->getMessage());

            return ['allowed' => true, 'remaining' => $maxAttempts, 'retry_after' => 0];
        }

        $hits = (int) ($row['hits'] ?? 1);

        return [
            'allowed'     => $hits <= $maxAttempts,
            'remaining'   => max(0, $maxAttempts - $hits),
            'retry_after' => max(1, (int) ($row['retry'] ?? $decaySeconds)),
        ];
    }

    public static function clear(string $key): void
    {
        Database::execute('DELETE FROM rate_limits WHERE rl_key = ?', [mb_substr($key, 0, 190)]);
    }
}
