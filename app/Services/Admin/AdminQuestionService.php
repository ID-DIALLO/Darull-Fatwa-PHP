<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Core\Database;
use App\Core\Lang;
use App\Models\MediaFile;
use App\Models\Question;
use App\Services\ActivityLog;
use App\Services\AssignmentService;
use App\Services\AudioService;
use App\Services\QuestionService;
use App\Services\QuestionWorkflow;
use App\Services\WorkflowException;

/**
 * Gestion complète des questions par l'administration (§4.6.2) : tableau filtrable, chronologie,
 * changement de statut, actions groupées, suppression logique, import d'un audio reçu sur WhatsApp.
 */
final class AdminQuestionService
{
    public const SORTS = ['newest', 'oldest', 'late', 'status', 'rubrique', 'scholar'];

    private const SELECT = 'SELECT q.id, q.reference, q.title, q.input_type, q.source, q.spoken_language, q.answer_language, q.answer_channel,
            q.status, q.priority, q.visibility, q.assigned_to, q.assigned_at, q.answered_at, q.first_response_minutes, q.created_at, q.closed_at, q.user_id,
            COALESCE(rt.name, rd.name) AS rubrique_name, r.icon AS rubrique_icon, r.parent_id AS rubrique_parent_id,
            u.full_name AS member_name, u.display_name AS member_display_name, u.email AS member_email, u.phone AS member_phone, u.country AS member_country,
            s.full_name AS scholar_name,
            TIMESTAMPDIFF(HOUR, q.created_at, UTC_TIMESTAMP()) AS age_hours,
            (SELECT mf.duration_seconds FROM question_messages qm JOIN media_files mf ON mf.id = qm.media_id WHERE qm.question_id = q.id AND qm.kind = \'question\' ORDER BY qm.id LIMIT 1) AS audio_seconds,
            (SELECT mf.uuid FROM question_messages qm JOIN media_files mf ON mf.id = qm.media_id WHERE qm.question_id = q.id AND qm.kind = \'question\' ORDER BY qm.id LIMIT 1) AS audio_uuid,
            (SELECT COUNT(*) FROM question_messages pm WHERE pm.question_id = q.id AND pm.kind = \'answer\' AND pm.review_status = \'pending\') AS pending_review
        FROM questions q
        JOIN rubriques r ON r.id = q.rubrique_id
        LEFT JOIN rubrique_translations rt ON rt.rubrique_id = r.id AND rt.locale = ?
        LEFT JOIN rubrique_translations rd ON rd.rubrique_id = r.id AND rd.locale = ?
        JOIN users u ON u.id = q.user_id
        LEFT JOIN users s ON s.id = q.assigned_to';

    /**
     * @param array<string, string> $filters
     * @return array{data: list<array<string, mixed>>, total: int, page: int, per_page: int, last_page: int}
     */
    public static function list(array $filters, int $page = 1, int $perPage = 20): array
    {
        [$whereSql, $bindings] = self::where($filters);
        $total = (int) Database::value('SELECT COUNT(*) FROM questions q JOIN users u ON u.id = q.user_id' . $whereSql, $bindings);
        $page = max(1, $page);
        $rows = Database::fetchAll(
            self::SELECT . $whereSql . ' ORDER BY ' . self::orderBy($filters['sort'] ?? 'newest') . ' LIMIT ' . (int) $perPage . ' OFFSET ' . (int) (($page - 1) * $perPage),
            array_merge([Lang::getLocale(), Lang::getDefault()], $bindings),
        );

        return ['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'last_page' => max(1, (int) ceil($total / $perPage))];
    }

    /**
     * Lignes pour l'export (mêmes filtres que le tableau, 5 000 lignes au plus).
     *
     * @param array<string, string> $filters
     * @return list<array<string, mixed>>
     */
    public static function forExport(array $filters, int $limit = 5000): array
    {
        [$whereSql, $bindings] = self::where($filters);

        return Database::fetchAll(
            self::SELECT . $whereSql . ' ORDER BY ' . self::orderBy($filters['sort'] ?? 'newest') . ' LIMIT ' . (int) $limit,
            array_merge([Lang::getLocale(), Lang::getDefault()], $bindings),
        );
    }

    /**
     * @param array<string, string> $filters
     * @return array{0: string, 1: list<mixed>}
     */
    private static function where(array $filters): array
    {
        $where = ['q.deleted_at IS NULL'];
        $bindings = [];
        $in = static function (string $column, array $values) use (&$where, &$bindings): void {
            $where[] = $column . ' IN (' . implode(',', array_fill(0, count($values), '?')) . ')';
            array_push($bindings, ...$values);
        };
        $status = (string) ($filters['status'] ?? '');
        if ($status === 'open') {
            $in('q.status', ['new', 'assigned', 'in_progress', 'needs_info']);
        } elseif ($status === 'done') {
            $in('q.status', ['answered', 'published', 'closed']);
        } elseif (in_array($status, Question::STATUSES, true)) {
            $where[] = 'q.status = ?';
            $bindings[] = $status;
        }
        if (ctype_digit((string) ($filters['rubrique'] ?? ''))) {
            $where[] = '(q.rubrique_id = ? OR r2.parent_id = ?)';
            array_push($bindings, (int) $filters['rubrique'], (int) $filters['rubrique']);
        }
        if (in_array($filters['language'] ?? '', ['fr', 'ar', 'en', 'bm', 'other'], true)) {
            $where[] = 'q.spoken_language = ?';
            $bindings[] = $filters['language'];
        }
        if (in_array($filters['channel'] ?? '', ['platform', 'whatsapp', 'both'], true)) {
            $where[] = 'q.answer_channel = ?';
            $bindings[] = $filters['channel'];
        }
        if (in_array($filters['source'] ?? '', ['web', 'whatsapp', 'admin'], true)) {
            $where[] = 'q.source = ?';
            $bindings[] = $filters['source'];
        }
        if (in_array($filters['type'] ?? '', ['audio', 'text', 'mixed'], true)) {
            $where[] = 'q.input_type = ?';
            $bindings[] = $filters['type'];
        }
        $scholar = (string) ($filters['scholar'] ?? '');
        if ($scholar === 'none') {
            $where[] = 'q.assigned_to IS NULL';
        } elseif (ctype_digit($scholar)) {
            $where[] = 'q.assigned_to = ?';
            $bindings[] = (int) $scholar;
        }
        if (preg_match('/^[A-Z]{2}$/', (string) ($filters['country'] ?? ''))) {
            $where[] = 'u.country = ?';
            $bindings[] = $filters['country'];
        }
        if (($filters['late'] ?? '') === '1') {
            $where[] = "q.status IN ('new','assigned','in_progress') AND q.created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? HOUR)";
            $bindings[] = QuestionService::lateHours();
        }
        if (($filters['review'] ?? '') === '1') {
            $where[] = "EXISTS (SELECT 1 FROM question_messages pr WHERE pr.question_id = q.id AND pr.kind = 'answer' AND pr.review_status = 'pending')";
        }
        if (($filters['visibility'] ?? '') === 'publishable') {
            $where[] = "q.visibility = 'publishable'";
        }
        foreach (['from' => '>=', 'to' => '<='] as $key => $op) {
            $date = (string) ($filters[$key] ?? '');
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $where[] = 'q.created_at ' . $op . ' ?';
                $bindings[] = $date . ($key === 'to' ? ' 23:59:59' : ' 00:00:00');
            }
        }
        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
            $where[] = '(q.reference LIKE ? OR q.title LIKE ? OR u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR EXISTS (SELECT 1 FROM question_messages sm WHERE sm.question_id = q.id AND sm.body LIKE ?))';
            array_push($bindings, $like, $like, $like, $like, $like, $like);
        }
        $sql = ' LEFT JOIN rubriques r2 ON r2.id = q.rubrique_id WHERE ' . implode(' AND ', $where);

        return [$sql, $bindings];
    }

    private static function orderBy(string $sort): string
    {
        return match ($sort) {
            'oldest'   => 'q.created_at ASC',
            'late'     => "FIELD(q.status,'new','assigned','in_progress','needs_info') DESC, q.created_at ASC",
            'status'   => 'q.status ASC, q.created_at DESC',
            'rubrique' => 'rubrique_name ASC, q.created_at DESC',
            'scholar'  => 'scholar_name ASC, q.created_at DESC',
            default    => 'q.created_at DESC',
        };
    }

    /**
     * Chronologie complète (§4.6.2) : journaux de statut, assignations et journal d'activité de la question.
     *
     * @return list<array{at: string, kind: string, actor: string|null, text: string, comment: string|null}>
     */
    public static function timeline(int $questionId): array
    {
        $events = [];
        foreach (Database::fetchAll('SELECT l.*, u.full_name AS actor FROM question_status_logs l LEFT JOIN users u ON u.id = l.changed_by WHERE l.question_id = ? ORDER BY l.created_at, l.id', [$questionId]) as $row) {
            $events[] = [
                'at'      => (string) $row['created_at'],
                'kind'    => 'status',
                'actor'   => $row['actor'],
                'text'    => $row['from_status'] === null ? __('questions.detail.created') : __('questions.detail.status_changed', ['status' => __('questions.status.' . $row['to_status'])]),
                'comment' => $row['comment'],
            ];
        }
        foreach (Database::fetchAll('SELECT a.*, s.full_name AS scholar, b.full_name AS actor FROM question_assignments a JOIN users s ON s.id = a.scholar_id LEFT JOIN users b ON b.id = a.assigned_by WHERE a.question_id = ? ORDER BY a.assigned_at, a.id', [$questionId]) as $row) {
            $events[] = [
                'at'      => (string) $row['assigned_at'],
                'kind'    => 'assignment',
                'actor'   => $row['actor'],
                'text'    => __('admin.questions.timeline.assigned_to', ['name' => $row['scholar']]),
                'comment' => $row['reason'],
            ];
        }
        foreach (Database::fetchAll("SELECT a.*, u.full_name AS actor FROM activity_logs a LEFT JOIN users u ON u.id = a.user_id WHERE a.subject_type = 'question' AND a.subject_id = ? AND a.action NOT IN ('question.created','question.status_changed') ORDER BY a.created_at, a.id", [$questionId]) as $row) {
            $events[] = [
                'at'      => (string) $row['created_at'],
                'kind'    => 'activity',
                'actor'   => $row['actor'],
                'text'    => Lang::has('admin.activity.actions.' . $row['action']) ? __('admin.activity.actions.' . $row['action']) : (string) $row['action'],
                'comment' => null,
            ];
        }
        usort($events, static fn (array $a, array $b): int => strcmp($a['at'], $b['at']));

        return $events;
    }

    /**
     * Changement de statut manuel par l'administration (avec commentaire), via le workflow.
     *
     * @param array<string, mixed> $question
     * @return array<string, mixed>
     */
    public static function changeStatus(array $question, string $status, int $adminId, ?string $comment): array
    {
        $extra = $status === 'rejected' ? ['rejection_reason' => $comment !== null ? mb_substr($comment, 0, 255) : null] : [];
        $updated = QuestionWorkflow::transition($question, $status, $adminId, $comment, $extra);
        ActivityLog::record('admin.question.status', 'question', (int) $question['id'], ['from' => $question['status'], 'to' => $status]);

        return $updated;
    }

    /** @param array<string, mixed> $question */
    public static function softDelete(array $question, int $adminId): void
    {
        Question::softDelete((int) $question['id']);
        ActivityLog::record('admin.question.deleted', 'question', (int) $question['id'], ['reference' => $question['reference']], $adminId);
    }

    /**
     * Actions groupées (§4.6.2). Retourne le nombre de questions traitées ; les transitions interdites sont ignorées.
     *
     * @param list<int> $ids
     * @param array<string, mixed> $params
     */
    public static function bulk(string $action, array $ids, array $params, int $adminId): int
    {
        $done = 0;
        foreach (array_unique(array_filter(array_map('intval', $ids))) as $id) {
            $question = Question::find($id);
            if ($question === null) {
                continue;
            }
            try {
                switch ($action) {
                    case 'assign':
                        AssignmentService::assign($question, (int) ($params['scholar_id'] ?? 0), $adminId, $params['comment'] ?? null);
                        $done++;
                        break;
                    case 'status':
                        self::changeStatus($question, (string) ($params['status'] ?? ''), $adminId, $params['comment'] ?? null);
                        $done++;
                        break;
                    case 'priority':
                        Question::update($id, ['priority' => ($params['priority'] ?? 'normal') === 'high' ? 'high' : 'normal']);
                        $done++;
                        break;
                    case 'delete':
                        self::softDelete($question, $adminId);
                        $done++;
                        break;
                }
            } catch (WorkflowException|\InvalidArgumentException) {
                // Question dans un état incompatible : on passe à la suivante.
            }
        }
        if ($done > 0) {
            ActivityLog::record('admin.question.bulk', 'question', null, ['action' => $action, 'count' => $done], $adminId);
        }

        return $done;
    }

    /**
     * Import d'un audio reçu sur WhatsApp (§4.6.2) : ajouté au fil de la question comme message du membre
     * (ou du professeur assigné), journalisé dans whatsapp_messages (entrant).
     *
     * @param array<string, mixed> $question
     * @param array<string, mixed> $file Entrée de $_FILES
     * @return int Identifiant du message créé
     */
    public static function importWhatsAppAudio(array $question, array $file, int $adminId, string $kind = 'question'): int
    {
        $fromScholar = $kind === 'answer' && !empty($question['assigned_to']);
        $ownerId = $fromScholar ? (int) $question['assigned_to'] : (int) $question['user_id'];
        $media = AudioService::store($file, $ownerId, null, 'whatsapp');
        $messageKind = $fromScholar ? 'answer' : (self::hasQuestionAudio((int) $question['id']) ? 'follow_up' : 'question');
        Database::execute(
            'INSERT INTO question_messages (question_id, sender_id, kind, body, media_id, language, review_status) VALUES (?, ?, ?, NULL, ?, ?, ?)',
            [(int) $question['id'], $ownerId, $messageKind, (int) $media['id'], $fromScholar ? $question['answer_language'] : $question['spoken_language'], 'not_required'],
        );
        $messageId = Database::lastInsertId();
        if ($messageKind === 'question' && $question['input_type'] === 'text') {
            Question::update((int) $question['id'], ['input_type' => 'mixed']);
        }
        $owner = Database::fetch('SELECT phone, whatsapp FROM users WHERE id = ?', [$ownerId]) ?? [];
        $phone = (string) ($question['whatsapp_number'] ?: ($owner['whatsapp'] ?: ($owner['phone'] ?: 'unknown')));
        Database::execute(
            'INSERT INTO whatsapp_messages (direction, mode, phone, user_id, question_id, type, media_id, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            ['in', 'link', mb_substr($phone, 0, 20), $ownerId, (int) $question['id'], 'audio', (int) $media['id'], 'imported', $adminId],
        );
        ActivityLog::record('admin.question.audio_imported', 'question', (int) $question['id'], ['media' => $media['uuid'], 'kind' => $messageKind], $adminId);

        return $messageId;
    }

    private static function hasQuestionAudio(int $questionId): bool
    {
        return Database::fetch("SELECT 1 FROM question_messages WHERE question_id = ? AND kind = 'question' AND media_id IS NOT NULL LIMIT 1", [$questionId]) !== null;
    }

    /**
     * Professeurs assignables (actifs) avec leur charge actuelle.
     *
     * @return list<array<string, mixed>>
     */
    public static function scholars(): array
    {
        return Database::fetchAll(
            "SELECT u.id, u.full_name, sp.languages, sp.max_open_questions, sp.is_available,
                    (SELECT COUNT(*) FROM questions q WHERE q.assigned_to = u.id AND q.status IN ('assigned','in_progress','needs_info') AND q.deleted_at IS NULL) AS open_count
             FROM users u JOIN roles r ON r.id = u.role_id LEFT JOIN scholar_profiles sp ON sp.user_id = u.id
             WHERE r.slug = 'scholar' AND u.status = 'active' AND u.deleted_at IS NULL ORDER BY u.full_name",
        );
    }

    /** Pays présents parmi les demandeurs (filtre). @return list<string> */
    public static function countries(): array
    {
        return array_map(static fn (array $r): string => (string) $r['country'], Database::fetchAll('SELECT DISTINCT u.country FROM questions q JOIN users u ON u.id = q.user_id WHERE u.country IS NOT NULL AND q.deleted_at IS NULL ORDER BY u.country'));
    }

    /** Fiche complète : question + fil (notes internes incluses) + médias. @return array<string, mixed>|null */
    public static function sheet(int $id): ?array
    {
        $question = Question::findDetailed($id);
        if ($question === null) {
            return null;
        }
        $question['member'] = Database::fetch('SELECT u.*, r.slug AS role FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?', [(int) $question['user_id']]);
        $question['messages'] = Question::messages($id, true, false);
        $question['whatsapp'] = Database::fetchAll('SELECT w.*, u.full_name AS actor FROM whatsapp_messages w LEFT JOIN users u ON u.id = w.created_by WHERE w.question_id = ? ORDER BY w.created_at DESC', [$id]);
        $question['media_owner_name'] = null;

        return $question;
    }
}
