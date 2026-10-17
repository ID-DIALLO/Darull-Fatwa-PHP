<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;

/**
 * Limitation des tentatives de connexion (§4.1, §12.1) : au-delà de 5 échecs en 15 minutes,
 * par identifiant ou par adresse IP, la connexion est bloquée temporairement (réponse 429).
 */
final class LoginThrottle
{
    public const MAX_FAILURES = 5;
    public const WINDOW_MINUTES = 15;

    /**
     * Nombre de secondes avant de pouvoir réessayer, ou 0 si la connexion est autorisée.
     */
    public static function retryAfter(string $identifier, string $ip): int
    {
        try {
            $rows = Database::fetchAll(
                'SELECT identifier, ip_address, success, created_at FROM login_attempts
                 WHERE (identifier = ? OR ip_address = ?) AND created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? MINUTE)
                 ORDER BY created_at ASC',
                [self::normalize($identifier), $ip, self::WINDOW_MINUTES],
            );
        } catch (\PDOException $e) {
            Logger::warning('Limitation de connexion indisponible : ' . $e->getMessage());

            return 0;
        }

        $byIdentifier = self::failuresSinceLastSuccess(array_filter($rows, static fn (array $r): bool => $r['identifier'] === self::normalize($identifier)));
        $byIp = self::failuresSinceLastSuccess(array_filter($rows, static fn (array $r): bool => $r['ip_address'] === $ip));

        foreach ([$byIdentifier, $byIp] as $failures) {
            if (count($failures) >= self::MAX_FAILURES) {
                $oldest = strtotime((string) $failures[array_key_first($failures)]['created_at'] . ' UTC');

                return max(1, $oldest + self::WINDOW_MINUTES * 60 - time());
            }
        }

        return 0;
    }

    public static function record(string $identifier, string $ip, bool $success): void
    {
        try {
            Database::execute(
                'INSERT INTO login_attempts (identifier, ip_address, success) VALUES (?, ?, ?)',
                [self::normalize($identifier), $ip, $success ? 1 : 0],
            );
        } catch (\PDOException $e) {
            Logger::warning('Journal des connexions indisponible : ' . $e->getMessage());
        }
    }

    /** Nettoyage des anciennes tentatives (cron). */
    public static function purge(int $days = 30): int
    {
        return Database::execute('DELETE FROM login_attempts WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)', [$days]);
    }

    /**
     * Échecs postérieurs au dernier succès (une connexion réussie remet le compteur à zéro).
     *
     * @param array<int, array<string, mixed>> $rows triées par date croissante
     * @return array<int, array<string, mixed>>
     */
    private static function failuresSinceLastSuccess(array $rows): array
    {
        $failures = [];
        foreach ($rows as $row) {
            if ((int) $row['success'] === 1) {
                $failures = [];
            } else {
                $failures[] = $row;
            }
        }

        return $failures;
    }

    private static function normalize(string $identifier): string
    {
        return mb_substr(mb_strtolower(trim($identifier)), 0, 190);
    }
}
