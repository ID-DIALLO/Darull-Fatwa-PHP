<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Lang;

/**
 * Espace professeur (§4.5) : tableau de bord, files de travail, statistiques.
 */
final class ScholarService
{
    private const SELECT = 'SELECT q.id, q.reference, q.title, q.input_type, q.spoken_language, q.answer_language, q.answer_channel, q.status, q.priority,
            q.assigned_to, q.assigned_at, q.answered_at, q.created_at, q.user_id, q.source, q.requested_scholar_id, q.visibility,
            COALESCE(rt.name, rd.name) AS rubrique_name, r.icon AS rubrique_icon,
            u.full_name AS member_name, u.display_name AS member_display_name, u.country AS member_country,
            TIMESTAMPDIFF(HOUR, q.created_at, UTC_TIMESTAMP()) AS age_hours,
            (SELECT COUNT(*) FROM question_messages m WHERE m.question_id = q.id AND m.kind = \'follow_up\' AND m.read_at IS NULL) AS unread_follow_ups
        FROM questions q
        JOIN rubriques r ON r.id = q.rubrique_id
        LEFT JOIN rubrique_translations rt ON rt.rubrique_id = r.id AND rt.locale = ?
        LEFT JOIN rubrique_translations rd ON rd.rubrique_id = r.id AND rd.locale = ?
        JOIN users u ON u.id = q.user_id';

    /** Identifiants des rubriques d'un professeur. @return list<int> */
    public static function rubriqueIds(int $scholarId): array
    {
        return array_map(static fn (array $r): int => (int) $r['rubrique_id'], Database::fetchAll('SELECT rubrique_id FROM scholar_rubriques WHERE user_id = ?', [$scholarId]));
    }

    /** @return array<string, mixed>|null */
    public static function profile(int $scholarId): ?array
    {
        return Database::fetch('SELECT * FROM scholar_profiles WHERE user_id = ?', [$scholarId]);
    }

    /**
     * Compteurs du tableau de bord (§4.5).
     *
     * @return array<string, int|float|null>
     */
    public static function dashboard(int $scholarId): array
    {
        $lateHours = QuestionService::lateHours();
        $mine = Database::fetch(
            "SELECT COUNT(*) AS total,
                    SUM(status = 'assigned') AS assigned, SUM(status = 'in_progress') AS in_progress, SUM(status = 'needs_info') AS needs_info,
                    SUM(status IN ('answered','published','closed')) AS answered,
                    SUM(status IN ('assigned','in_progress','needs_info') AND created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? HOUR)) AS late,
                    SUM(status IN ('answered','published','closed') AND answered_at >= DATE_FORMAT(UTC_TIMESTAMP(), '%Y-%m-01')) AS answered_month,
                    AVG(CASE WHEN first_response_minutes IS NOT NULL THEN first_response_minutes END) AS avg_minutes
             FROM questions WHERE assigned_to = ? AND deleted_at IS NULL",
            [$lateHours, $scholarId],
        ) ?? [];
        $rubriques = self::rubriqueIds($scholarId);
        $unassigned = 0;
        if ($rubriques !== []) {
            $unassigned = (int) Database::value(
                'SELECT COUNT(*) FROM questions WHERE status = ? AND assigned_to IS NULL AND deleted_at IS NULL AND rubrique_id IN (' . implode(',', array_fill(0, count($rubriques), '?')) . ')',
                array_merge(['new'], $rubriques),
            );
        }

        return [
            'assigned'       => (int) ($mine['assigned'] ?? 0),
            'in_progress'    => (int) ($mine['in_progress'] ?? 0),
            'needs_info'     => (int) ($mine['needs_info'] ?? 0),
            'answered'       => (int) ($mine['answered'] ?? 0),
            'answered_month' => (int) ($mine['answered_month'] ?? 0),
            'late'           => (int) ($mine['late'] ?? 0),
            'unassigned'     => $unassigned,
            'avg_hours'      => $mine['avg_minutes'] !== null ? round(((float) $mine['avg_minutes']) / 60, 1) : null,
            'late_hours'     => $lateHours,
        ];
    }

    /**
     * File de travail : « mes questions » (assignées, ouvertes) ou « à prendre » (nouvelles de mes rubriques).
     *
     * @param array{sort?: string, rubrique?: string, language?: string, type?: string, status?: string} $filters
     * @return array{data: list<array<string, mixed>>, total: int, page: int, per_page: int, last_page: int}
     */
    public static function queue(int $scholarId, string $file, array $filters, int $page = 1, int $perPage = 15): array
    {
        $locale = Lang::getLocale();
        $where = ['q.deleted_at IS NULL'];
        $bindings = [];
        if ($file === 'available') {
            $rubriques = self::rubriqueIds($scholarId);
            $canSelfAssign = (int) (Database::value('SELECT can_self_assign FROM scholar_profiles WHERE user_id = ?', [$scholarId]) ?? 0) === 1;
            // Questions nouvelles de ses rubriques (si auto-attribution autorisée) + celles qui lui sont adressées
            $conditions = ['q.requested_scholar_id = ?'];
            $bindings[] = $scholarId;
            if ($rubriques !== [] && $canSelfAssign) {
                $conditions[] = 'q.rubrique_id IN (' . implode(',', array_fill(0, count($rubriques), '?')) . ')';
                array_push($bindings, ...$rubriques);
            }
            $where[] = "q.status = 'new' AND q.assigned_to IS NULL AND (" . implode(' OR ', $conditions) . ')';
        } else {
            $where[] = 'q.assigned_to = ?';
            $bindings[] = $scholarId;
            $status = $filters['status'] ?? 'open';
            if ($status === 'open') {
                $where[] = "q.status IN ('assigned','in_progress','needs_info')";
            } elseif ($status === 'done') {
                $where[] = "q.status IN ('answered','published','closed')";
            } elseif (in_array($status, \App\Models\Question::STATUSES, true)) {
                $where[] = 'q.status = ?';
                $bindings[] = $status;
            }
        }
        if (!empty($filters['rubrique']) && ctype_digit((string) $filters['rubrique'])) {
            $where[] = 'q.rubrique_id = ?';
            $bindings[] = (int) $filters['rubrique'];
        }
        if (!empty($filters['language']) && in_array($filters['language'], ['fr', 'ar', 'en', 'bm', 'other'], true)) {
            $where[] = 'q.spoken_language = ?';
            $bindings[] = $filters['language'];
        }
        if (!empty($filters['type']) && in_array($filters['type'], ['audio', 'text', 'mixed'], true)) {
            $where[] = 'q.input_type = ?';
            $bindings[] = $filters['type'];
        }
        $order = match ($filters['sort'] ?? 'oldest') {
            'newest'   => 'q.created_at DESC',
            'rubrique' => 'rubrique_name ASC, q.created_at ASC',
            'language' => 'q.spoken_language ASC, q.created_at ASC',
            'type'     => 'q.input_type ASC, q.created_at ASC',
            default    => 'q.priority DESC, q.created_at ASC',
        };
        $whereSql = ' WHERE ' . implode(' AND ', $where);
        $total = (int) Database::value('SELECT COUNT(*) FROM questions q' . $whereSql, $bindings);
        $page = max(1, $page);
        $rows = Database::fetchAll(
            self::SELECT . $whereSql . ' ORDER BY ' . $order . ' LIMIT ' . (int) $perPage . ' OFFSET ' . (int) (($page - 1) * $perPage),
            array_merge([$locale, Lang::getDefault()], $bindings),
        );
        foreach ($rows as &$row) {
            // Nom réel du membre uniquement pour les questions auxquelles ce professeur a répondu
            $row['member_label'] = AnswerService::askerLabel(['full_name' => $row['member_name'], 'display_name' => $row['member_display_name']], $row, $scholarId);
            unset($row['member_name']);
        }
        unset($row);

        return ['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'last_page' => max(1, (int) ceil($total / $perPage))];
    }

    /**
     * Statistiques du professeur (§ demande du 18/10) : par statut, par mois (12 derniers), par rubrique, par langue, par source, délais.
     *
     * @return array<string, mixed>
     */
    public static function statistics(int $scholarId): array
    {
        $locale = Lang::getLocale();
        $byStatus = [];
        foreach (Database::fetchAll('SELECT status, COUNT(*) AS n FROM questions WHERE assigned_to = ? AND deleted_at IS NULL GROUP BY status', [$scholarId]) as $r) {
            $byStatus[(string) $r['status']] = (int) $r['n'];
        }
        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $months[gmdate('Y-m', strtotime("first day of -{$i} month"))] = ['received' => 0, 'answered' => 0];
        }
        foreach (Database::fetchAll("SELECT DATE_FORMAT(assigned_at, '%Y-%m') AS m, COUNT(*) AS n FROM questions WHERE assigned_to = ? AND deleted_at IS NULL AND assigned_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 12 MONTH) GROUP BY m", [$scholarId]) as $r) {
            if (isset($months[$r['m']])) { $months[$r['m']]['received'] = (int) $r['n']; }
        }
        foreach (Database::fetchAll("SELECT DATE_FORMAT(answered_at, '%Y-%m') AS m, COUNT(*) AS n FROM questions WHERE assigned_to = ? AND deleted_at IS NULL AND answered_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 12 MONTH) GROUP BY m", [$scholarId]) as $r) {
            if (isset($months[$r['m']])) { $months[$r['m']]['answered'] = (int) $r['n']; }
        }
        $byRubrique = Database::fetchAll(
            'SELECT COALESCE(rt.name, rd.name) AS name, COUNT(*) AS n, SUM(q.status IN (\'answered\',\'published\',\'closed\')) AS answered
             FROM questions q JOIN rubriques r ON r.id = q.rubrique_id
             LEFT JOIN rubrique_translations rt ON rt.rubrique_id = r.id AND rt.locale = ?
             LEFT JOIN rubrique_translations rd ON rd.rubrique_id = r.id AND rd.locale = ?
             WHERE q.assigned_to = ? AND q.deleted_at IS NULL GROUP BY r.id, name ORDER BY n DESC',
            [$locale, Lang::getDefault(), $scholarId],
        );
        $byLanguage = Database::fetchAll('SELECT spoken_language AS code, COUNT(*) AS n FROM questions WHERE assigned_to = ? AND deleted_at IS NULL GROUP BY spoken_language ORDER BY n DESC', [$scholarId]);
        $bySource = Database::fetchAll('SELECT source, COUNT(*) AS n FROM questions WHERE assigned_to = ? AND deleted_at IS NULL GROUP BY source', [$scholarId]);
        $byType = Database::fetchAll('SELECT input_type, COUNT(*) AS n FROM questions WHERE assigned_to = ? AND deleted_at IS NULL GROUP BY input_type', [$scholarId]);
        $delays = Database::fetch("SELECT AVG(first_response_minutes) AS avg_minutes, MIN(first_response_minutes) AS min_minutes, MAX(first_response_minutes) AS max_minutes,
                                          SUM(user_found_helpful = 1) AS helpful, SUM(user_found_helpful = 0) AS not_helpful, SUM(requested_scholar_id = ?) AS requested
                                   FROM questions WHERE assigned_to = ? AND deleted_at IS NULL", [$scholarId, $scholarId]) ?? [];
        $total = array_sum($byStatus);
        $answered = ($byStatus['answered'] ?? 0) + ($byStatus['published'] ?? 0) + ($byStatus['closed'] ?? 0);

        return [
            'total'       => $total,
            'answered'    => $answered,
            'open'        => ($byStatus['assigned'] ?? 0) + ($byStatus['in_progress'] ?? 0) + ($byStatus['needs_info'] ?? 0),
            'published'   => $byStatus['published'] ?? 0,
            'by_status'   => $byStatus,
            'months'      => $months,
            'by_rubrique' => $byRubrique,
            'by_language' => $byLanguage,
            'by_source'   => $bySource,
            'by_type'     => $byType,
            'avg_hours'   => $delays['avg_minutes'] !== null ? round((float) $delays['avg_minutes'] / 60, 1) : null,
            'min_hours'   => $delays['min_minutes'] !== null ? round((float) $delays['min_minutes'] / 60, 1) : null,
            'max_hours'   => $delays['max_minutes'] !== null ? round((float) $delays['max_minutes'] / 60, 1) : null,
            'helpful'     => (int) ($delays['helpful'] ?? 0),
            'not_helpful' => (int) ($delays['not_helpful'] ?? 0),
            'requested'   => (int) ($delays['requested'] ?? 0),
        ];
    }

    /**
     * Commentaires (approuvés et en attente) sous les articles du professeur et ses Q/R publiques.
     *
     * @return list<array<string, mixed>>
     */
    public static function comments(int $scholarId, int $limit = 100): array
    {
        $locale = Lang::getLocale();
        $rows = Database::fetchAll(
            "SELECT c.id, c.body, c.status, c.created_at, c.commentable_type, c.commentable_id,
                    u.full_name AS author_name, u.display_name AS author_display_name,
                    COALESCE(at.title, ad.title) AS article_title, COALESCE(at.slug, ad.slug) AS article_slug,
                    COALESCE(qt.title, qd.title) AS qa_title, COALESCE(qt.slug, qd.slug) AS qa_slug
             FROM comments c
             JOIN users u ON u.id = c.user_id
             LEFT JOIN articles a ON c.commentable_type = 'article' AND a.id = c.commentable_id
             LEFT JOIN article_translations at ON at.article_id = a.id AND at.locale = ?
             LEFT JOIN article_translations ad ON ad.article_id = a.id AND ad.locale = ?
             LEFT JOIN qa_publications p ON c.commentable_type = 'qa' AND p.id = c.commentable_id
             LEFT JOIN qa_publication_translations qt ON qt.publication_id = p.id AND qt.locale = ?
             LEFT JOIN qa_publication_translations qd ON qd.publication_id = p.id AND qd.locale = ?
             WHERE c.status IN ('approved','pending') AND ((a.author_id = ?) OR (p.scholar_id = ?))
             ORDER BY c.created_at DESC LIMIT " . (int) $limit,
            [$locale, Lang::getDefault(), $locale, Lang::getDefault(), $scholarId, $scholarId],
        );
        foreach ($rows as &$row) {
            $row['author'] = $row['author_display_name'] ?: $row['author_name'];
            $row['target_title'] = $row['commentable_type'] === 'article' ? $row['article_title'] : $row['qa_title'];
            $row['url'] = $row['commentable_type'] === 'article'
                ? ($row['article_slug'] && route_exists('blog.show') ? route('blog.show', ['slug' => $row['article_slug']]) : null)
                : ($row['qa_slug'] && route_exists('qa.show') ? route('qa.show', ['slug' => $row['qa_slug']]) : null);
        }
        unset($row);

        return $rows;
    }

    /** Questions en retard (toutes) pour les rappels du cron. @return list<array<string, mixed>> */
    public static function lateQuestions(): array
    {
        return Database::fetchAll(
            "SELECT q.id, q.reference, q.assigned_to, q.rubrique_id, q.created_at FROM questions q
             WHERE q.status IN ('new','assigned','in_progress') AND q.deleted_at IS NULL
               AND q.created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? HOUR) ORDER BY q.created_at",
            [QuestionService::lateHours()],
        );
    }

    /** Met à jour le profil public du professeur (titre/bio par langue, langues, photo). @param array<string, mixed> $data */
    public static function updateProfile(int $scholarId, array $data): void
    {
        Database::transaction(static function () use ($scholarId, $data): void {
            Database::execute('INSERT IGNORE INTO scholar_profiles (user_id) VALUES (?)', [$scholarId]);
            $languages = array_values(array_intersect((array) ($data['languages'] ?? []), ['fr', 'ar', 'en', 'bm', 'other']));
            $update = ['languages' => implode(',', $languages !== [] ? $languages : ['fr'])];
            if (array_key_exists('photo_path', $data)) {
                $update['photo_path'] = $data['photo_path'];
            }
            if (isset($data['is_available'])) {
                $update['is_available'] = (int) (bool) $data['is_available'];
            }
            \App\Models\ScholarProfile::update($scholarId, $update);
            foreach (Lang::locales() as $locale) {
                $title = trim((string) ($data['title'][$locale] ?? ''));
                $bio = trim((string) ($data['bio'][$locale] ?? ''));
                if ($title === '' && $bio === '') {
                    Database::execute('DELETE FROM scholar_translations WHERE user_id = ? AND locale = ?', [$scholarId, $locale]);
                    continue;
                }
                Database::execute(
                    'INSERT INTO scholar_translations (user_id, locale, title, bio) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE title = VALUES(title), bio = VALUES(bio)',
                    [$scholarId, $locale, mb_substr($title, 0, 80) ?: null, $bio !== '' ? $bio : null],
                );
            }
        });
        \App\Core\Cache::flush('stats.');
    }
}
