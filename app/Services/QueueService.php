<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;

/**
 * File d'envoi (table message_queue) traitée par cron/run.php : e-mails de notification
 * (et messages WhatsApp en V2). Trois tentatives avec délai croissant avant abandon.
 */
final class QueueService
{
    public const MAX_ATTEMPTS = 3;

    /** @param array<string, mixed> $payload */
    public static function push(string $channel, string $recipient, string $template, string $locale, array $payload = [], ?\DateTimeInterface $scheduledAt = null): int
    {
        Database::execute(
            'INSERT INTO message_queue (channel, recipient, template, locale, payload, scheduled_at) VALUES (?, ?, ?, ?, ?, ?)',
            [
                $channel,
                mb_substr($recipient, 0, 190),
                mb_substr($template, 0, 80),
                $locale,
                json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                ($scheduledAt ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            ],
        );

        return Database::lastInsertId();
    }

    /** Traite les messages en attente. Retourne [envoyés, échoués]. @return array{0: int, 1: int} */
    public static function process(int $limit = 25): array
    {
        $rows = Database::fetchAll(
            'SELECT * FROM message_queue WHERE status = ? AND scheduled_at <= UTC_TIMESTAMP() ORDER BY id ASC LIMIT ' . max(1, min(200, $limit)),
            ['pending'],
        );
        $sent = 0;
        $failed = 0;
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            // Verrou optimiste : seul le premier processus qui passe en « processing » traite la ligne.
            if (Database::execute('UPDATE message_queue SET status = ?, attempts = attempts + 1 WHERE id = ? AND status = ?', ['processing', $id, 'pending']) === 0) {
                continue;
            }
            $payload = json_decode((string) ($row['payload'] ?? '[]'), true);
            $payload = is_array($payload) ? $payload : [];
            $attempts = (int) $row['attempts'] + 1;
            try {
                $ok = match ((string) $row['channel']) {
                    'email'  => MailService::send((string) $row['recipient'], (string) $row['template'], $payload, (string) $row['locale'], isset($payload['name']) ? (string) $payload['name'] : null),
                    default  => false, // WhatsApp Cloud API : V2
                };
                $error = $ok ? null : 'Envoi refusé';
            } catch (\Throwable $e) {
                $ok = false;
                $error = $e->getMessage();
                Logger::exception($e);
            }

            if ($ok) {
                Database::execute('UPDATE message_queue SET status = ?, sent_at = UTC_TIMESTAMP(), last_error = NULL WHERE id = ?', ['sent', $id]);
                $sent++;
            } elseif ($attempts < self::MAX_ATTEMPTS) {
                Database::execute(
                    'UPDATE message_queue SET status = ?, last_error = ?, scheduled_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? MINUTE) WHERE id = ?',
                    ['pending', mb_substr((string) $error, 0, 1000), $attempts * 5, $id],
                );
            } else {
                Database::execute('UPDATE message_queue SET status = ?, last_error = ? WHERE id = ?', ['failed', mb_substr((string) $error, 0, 1000), $id]);
                $failed++;
            }
        }

        return [$sent, $failed];
    }

    /** Supprime les messages envoyés depuis plus de $days jours (cron). */
    public static function purge(int $days = 30): int
    {
        return Database::execute('DELETE FROM message_queue WHERE status = ? AND sent_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)', ['sent', $days]);
    }
}
