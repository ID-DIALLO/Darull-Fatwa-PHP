<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Core\Database;
use App\Core\Lang;
use App\Services\QuestionService;

/**
 * Statistiques du tableau de bord (§4.6.1) : indicateurs et données des graphiques pour une période.
 */
final class StatsService
{
    public const PERIODS = ['7', '30', '365', 'custom'];

    /**
     * Bornes UTC de la période demandée.
     *
     * @param array<string, string> $input
     * @return array{from: string, to: string, period: string, label_from: string, label_to: string}
     */
    public static function range(array $input): array
    {
        $period = in_array($input['period'] ?? '30', self::PERIODS, true) ? (string) ($input['period'] ?? '30') : '30';
        $today = new \DateTimeImmutable('today', new \DateTimeZone('UTC'));
        $to = $today;
        $from = match ($period) {
            '7'   => $today->modify('-6 days'),
            '365' => $today->modify('-364 days'),
            'custom' => $today->modify('-29 days'),
            default => $today->modify('-29 days'),
        };
        if ($period === 'custom') {
            $parse = static fn (string $value, \DateTimeImmutable $default): \DateTimeImmutable => preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? new \DateTimeImmutable($value, new \DateTimeZone('UTC')) : $default;
            $from = $parse((string) ($input['from'] ?? ''), $from);
            $to = $parse((string) ($input['to'] ?? ''), $to);
            if ($from > $to) {
                [$from, $to] = [$to, $from];
            }
        }

        return [
            'from' => $from->format('Y-m-d 00:00:00'), 'to' => $to->format('Y-m-d 23:59:59'),
            'period' => $period, 'label_from' => $from->format('Y-m-d'), 'label_to' => $to->format('Y-m-d'),
        ];
    }

    /**
     * Cartes KPI.
     *
     * @param array{from: string, to: string} $range
     * @return array<string, int|float|null>
     */
    public static function kpis(array $range): array
    {
        $b = [$range['from'], $range['to']];
        $period = 'q.deleted_at IS NULL AND q.created_at BETWEEN ? AND ?';
        $total = (int) Database::value('SELECT COUNT(*) FROM questions q WHERE ' . $period, $b);
        $answered = (int) Database::value("SELECT COUNT(*) FROM questions q WHERE {$period} AND q.status IN ('answered','published','closed')", $b);
        $avg = Database::value('SELECT AVG(q.first_response_minutes) FROM questions q WHERE ' . $period . ' AND q.first_response_minutes IS NOT NULL', $b);

        return [
            'total'           => $total,
            'new_24h'         => (int) Database::value('SELECT COUNT(*) FROM questions q WHERE q.deleted_at IS NULL AND q.created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)'),
            'new_7d'          => (int) Database::value('SELECT COUNT(*) FROM questions q WHERE q.deleted_at IS NULL AND q.created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)'),
            'pending'         => (int) Database::value("SELECT COUNT(*) FROM questions q WHERE q.deleted_at IS NULL AND q.status IN ('new','assigned','in_progress','needs_info')"),
            'late'            => (int) Database::value("SELECT COUNT(*) FROM questions q WHERE q.deleted_at IS NULL AND q.status IN ('new','assigned','in_progress') AND q.created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? HOUR)", [QuestionService::lateHours()]),
            'review'          => (int) Database::value("SELECT COUNT(DISTINCT question_id) FROM question_messages WHERE kind = 'answer' AND review_status = 'pending'"),
            'answered'        => $answered,
            'answer_rate'     => $total > 0 ? round($answered * 100 / $total) : null,
            'avg_hours'       => $avg !== null ? round(((float) $avg) / 60, 1) : null,
            'members'         => (int) Database::value("SELECT COUNT(*) FROM users WHERE deleted_at IS NULL AND status <> 'banned'"),
            'members_new'     => (int) Database::value('SELECT COUNT(*) FROM users WHERE deleted_at IS NULL AND created_at BETWEEN ? AND ?', $b),
            'articles'        => (int) Database::value("SELECT COUNT(*) FROM articles WHERE status = 'published' AND deleted_at IS NULL"),
            'topics'          => (int) Database::value('SELECT COUNT(*) FROM forum_topics WHERE deleted_at IS NULL'),
            'ideas'           => (int) Database::value('SELECT COUNT(*) FROM ideas WHERE deleted_at IS NULL'),
            'contacts_new'    => (int) Database::value("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'"),
        ];
    }

    /**
     * Données des graphiques Chart.js (libellés déjà traduits).
     *
     * @param array{from: string, to: string} $range
     * @return array<string, mixed>
     */
    public static function charts(array $range): array
    {
        $b = [$range['from'], $range['to']];
        $period = 'q.deleted_at IS NULL AND q.created_at BETWEEN ? AND ?';
        $pairs = static fn (array $rows, callable $label): array => [
            'labels' => array_map(static fn (array $r): string => $label((string) $r['k']), $rows),
            'values' => array_map(static fn (array $r): int => (int) $r['n'], $rows),
        ];

        $byRubrique = Database::fetchAll(
            'SELECT COALESCE(rt.name, rd.name) AS k, COUNT(*) AS n FROM questions q JOIN rubriques r ON r.id = q.rubrique_id
             LEFT JOIN rubrique_translations rt ON rt.rubrique_id = r.id AND rt.locale = ? LEFT JOIN rubrique_translations rd ON rd.rubrique_id = r.id AND rd.locale = ?
             WHERE ' . $period . ' GROUP BY r.id, k ORDER BY n DESC',
            array_merge([Lang::getLocale(), Lang::getDefault()], $b),
        );
        $monthly = Database::fetchAll(
            "SELECT DATE_FORMAT(q.created_at, '%Y-%m') AS k, COUNT(*) AS n,
                    SUM(q.status IN ('answered','published','closed')) AS answered
             FROM questions q WHERE q.deleted_at IS NULL AND q.created_at >= DATE_SUB(DATE_FORMAT(UTC_TIMESTAMP(), '%Y-%m-01'), INTERVAL 11 MONTH)
             GROUP BY k ORDER BY k",
        );
        $scholars = Database::fetchAll(
            "SELECT u.full_name AS name,
                    (SELECT COUNT(*) FROM question_assignments a WHERE a.scholar_id = u.id AND a.assigned_at BETWEEN ? AND ?) AS assigned,
                    (SELECT COUNT(*) FROM questions q WHERE q.assigned_to = u.id AND q.deleted_at IS NULL AND q.status IN ('assigned','in_progress','needs_info')) AS open_count,
                    (SELECT COUNT(*) FROM questions q WHERE q.assigned_to = u.id AND q.deleted_at IS NULL AND q.answered_at BETWEEN ? AND ?) AS answered,
                    (SELECT ROUND(AVG(q.first_response_minutes) / 60, 1) FROM questions q WHERE q.assigned_to = u.id AND q.deleted_at IS NULL AND q.answered_at BETWEEN ? AND ? AND q.first_response_minutes IS NOT NULL) AS avg_hours
             FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug = 'scholar' AND u.deleted_at IS NULL ORDER BY answered DESC, u.full_name",
            array_merge($b, $b, $b),
        );
        $countries = Database::fetchAll('SELECT COALESCE(u.country, \'--\') AS k, COUNT(*) AS n FROM questions q JOIN users u ON u.id = q.user_id WHERE ' . $period . ' GROUP BY k ORDER BY n DESC LIMIT 10', $b);

        return [
            'rubrique' => $pairs($byRubrique, static fn (string $k): string => $k),
            'status'   => $pairs(Database::fetchAll('SELECT q.status AS k, COUNT(*) AS n FROM questions q WHERE ' . $period . ' GROUP BY q.status', $b), static fn (string $k): string => __('questions.status.' . $k)),
            'language' => $pairs(Database::fetchAll('SELECT q.spoken_language AS k, COUNT(*) AS n FROM questions q WHERE ' . $period . ' GROUP BY q.spoken_language', $b), static fn (string $k): string => __('questions.languages.' . $k)),
            'channel'  => $pairs(Database::fetchAll('SELECT q.answer_channel AS k, COUNT(*) AS n FROM questions q WHERE ' . $period . ' GROUP BY q.answer_channel', $b), static fn (string $k): string => __('questions.channels.' . $k)),
            'type'     => $pairs(Database::fetchAll('SELECT q.input_type AS k, COUNT(*) AS n FROM questions q WHERE ' . $period . ' GROUP BY q.input_type', $b), static fn (string $k): string => __('questions.types.' . $k)),
            'country'  => $pairs($countries, static fn (string $k): string => $k === '--' ? __('admin.stats.unknown') : (\Locale::getDisplayRegion('-' . $k, Lang::getLocale()) ?: $k)),
            'monthly'  => [
                'labels'   => array_map(static fn (array $r): string => (string) $r['k'], $monthly),
                'values'   => array_map(static fn (array $r): int => (int) $r['n'], $monthly),
                'answered' => array_map(static fn (array $r): int => (int) $r['answered'], $monthly),
            ],
            'scholars' => $scholars,
        ];
    }
}
