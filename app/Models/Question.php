<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Lang;
use App\Core\Model;

/**
 * Questions des membres (module central, §4.2) et leur fil de messages.
 */
class Question extends Model
{
    protected static string $table = 'questions';

    protected static bool $softDeletes = true;

    protected static array $fillable = [
        'reference', 'user_id', 'rubrique_id', 'title', 'input_type', 'source', 'spoken_language', 'answer_language',
        'answer_channel', 'whatsapp_number', 'visibility', 'status', 'priority', 'assigned_to', 'assigned_at',
        'answered_at', 'first_response_minutes', 'answer_message_id', 'rejection_reason', 'user_found_helpful',
        'closed_at', 'ip_address', 'user_agent', 'deleted_at',
    ];

    public const STATUSES = ['new', 'assigned', 'in_progress', 'needs_info', 'answered', 'published', 'rejected', 'closed'];

    /** Statuts regroupés pour les onglets de « Mes questions ». */
    public const GROUPS = [
        'pending'    => ['new', 'assigned', 'in_progress'],
        'needs_info' => ['needs_info'],
        'answered'   => ['answered', 'published', 'closed'],
    ];

    /** Couleur de badge par statut (pastille + libellé, §8.4). */
    public const BADGES = [
        'new' => 'warning', 'assigned' => 'warning', 'in_progress' => 'info', 'needs_info' => 'warning',
        'answered' => 'success', 'published' => 'success', 'rejected' => 'danger', 'closed' => 'secondary',
    ];

    private const SELECT = 'SELECT q.*, COALESCE(rt.name, rd.name) AS rubrique_name, COALESCE(rt.slug, rd.slug) AS rubrique_slug, r.icon AS rubrique_icon, r.code AS rubrique_code,
            s.full_name AS scholar_name, s.display_name AS scholar_display_name, s.avatar_path AS scholar_avatar,
            st.title AS scholar_title, sp.photo_path AS scholar_photo
        FROM questions q
        JOIN rubriques r ON r.id = q.rubrique_id
        LEFT JOIN rubrique_translations rt ON rt.rubrique_id = r.id AND rt.locale = ?
        LEFT JOIN rubrique_translations rd ON rd.rubrique_id = r.id AND rd.locale = ?
        LEFT JOIN users s ON s.id = q.assigned_to
        LEFT JOIN scholar_profiles sp ON sp.user_id = s.id
        LEFT JOIN scholar_translations st ON st.user_id = s.id AND st.locale = ?';

    /** @return array<string, mixed>|null */
    public static function findByReference(string $reference, ?string $locale = null): ?array
    {
        $locale ??= Lang::getLocale();

        return Database::fetch(self::SELECT . ' WHERE q.reference = ? AND q.deleted_at IS NULL LIMIT 1', [$locale, Lang::getDefault(), $locale, $reference]);
    }

    /** @return array<string, mixed>|null */
    public static function findDetailed(int $id, ?string $locale = null): ?array
    {
        $locale ??= Lang::getLocale();

        return Database::fetch(self::SELECT . ' WHERE q.id = ? LIMIT 1', [$locale, Lang::getDefault(), $locale, $id]);
    }

    /**
     * Questions d'un membre, filtrées et paginées (§4.4).
     *
     * @param array{tab?: string, rubrique?: string, period?: string, q?: string} $filters
     * @return array{data: list<array<string, mixed>>, total: int, page: int, per_page: int, last_page: int}
     */
    public static function forMember(int $userId, array $filters, int $page = 1, int $perPage = 10, ?string $locale = null): array
    {
        $locale ??= Lang::getLocale();
        $where = ['q.user_id = ?', 'q.deleted_at IS NULL'];
        $bindings = [$userId];

        $tab = $filters['tab'] ?? 'all';
        if (isset(self::GROUPS[$tab])) {
            $where[] = 'q.status IN (' . implode(', ', array_fill(0, count(self::GROUPS[$tab]), '?')) . ')';
            array_push($bindings, ...self::GROUPS[$tab]);
        }
        if (!empty($filters['rubrique']) && ctype_digit((string) $filters['rubrique'])) {
            $where[] = 'q.rubrique_id = ?';
            $bindings[] = (int) $filters['rubrique'];
        }
        $days = match ($filters['period'] ?? '') { '7' => 7, '30' => 30, '365' => 365, default => null };
        if ($days !== null) {
            $where[] = 'q.created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)';
            $bindings[] = $days;
        }
        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $where[] = '(q.reference LIKE ? OR q.title LIKE ? OR EXISTS (SELECT 1 FROM question_messages m WHERE m.question_id = q.id AND m.kind IN (\'question\',\'answer\') AND m.body LIKE ?))';
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
            array_push($bindings, $like, $like, $like);
        }
        $whereSql = ' WHERE ' . implode(' AND ', $where);

        $total = (int) Database::value('SELECT COUNT(*) FROM questions q' . $whereSql, $bindings);
        $page = max(1, $page);
        $rows = Database::fetchAll(
            self::SELECT . $whereSql . ' ORDER BY q.created_at DESC LIMIT ' . (int) $perPage . ' OFFSET ' . (int) (($page - 1) * $perPage),
            array_merge([$locale, Lang::getDefault(), $locale], $bindings),
        );

        return ['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'last_page' => max(1, (int) ceil($total / $perPage))];
    }

    /**
     * Fil de messages d'une question (sans les notes internes pour un membre), avec médias et expéditeurs.
     *
     * @return list<array<string, mixed>>
     */
    public static function messages(int $questionId, bool $withInternal = false, bool $approvedOnly = true): array
    {
        $sql = 'SELECT m.*, u.full_name AS sender_name, u.display_name AS sender_display_name, u.avatar_path AS sender_avatar, r.slug AS sender_role,
                       md.uuid AS media_uuid, md.duration_seconds AS media_duration, md.mime_type AS media_mime, md.transcript AS media_transcript
                FROM question_messages m
                LEFT JOIN users u ON u.id = m.sender_id
                LEFT JOIN roles r ON r.id = u.role_id
                LEFT JOIN media_files md ON md.id = m.media_id
                WHERE m.question_id = ?';
        $bindings = [$questionId];
        if (!$withInternal) {
            $sql .= ' AND m.kind <> ?';
            $bindings[] = 'internal_note';
        }
        if ($approvedOnly) {
            // Un membre ne voit une réponse en relecture qu'une fois validée (§4.2.4)
            $sql .= ' AND (m.kind <> ? OR m.review_status IN (?, ?))';
            array_push($bindings, 'answer', 'not_required', 'approved');
        }

        return Database::fetchAll($sql . ' ORDER BY m.created_at ASC, m.id ASC', $bindings);
    }

    /** Référence publique « DF-AAAA-NNNNNN » (§4.2.1). */
    public static function makeReference(int $id, ?int $year = null): string
    {
        return sprintf('DF-%d-%06d', $year ?? (int) gmdate('Y'), $id);
    }

    /** Groupe d'onglet (pending / needs_info / answered / rejected) d'un statut. */
    public static function groupOf(string $status): string
    {
        foreach (self::GROUPS as $group => $statuses) {
            if (in_array($status, $statuses, true)) {
                return $group;
            }
        }

        return $status;
    }
}
