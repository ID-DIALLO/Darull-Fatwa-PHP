<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Question;

/**
 * Assignation des questions aux professeurs (§4.6.2) : manuelle (admin, modérateur), auto-attribution
 * (professeur de la rubrique autorisé) et automatique (option « auto_assign ») vers le professeur disponible
 * de la rubrique et de la langue ayant le moins de questions ouvertes.
 */
final class AssignmentService
{
    /**
     * Assigne ou réassigne la question. Retourne la question mise à jour.
     *
     * @param array<string, mixed> $question
     * @return array<string, mixed>
     * @throws WorkflowException si le statut ne permet pas l'assignation
     */
    public static function assign(array $question, int $scholarId, ?int $byUserId, ?string $reason = null): array
    {
        $previous = (int) ($question['assigned_to'] ?? 0);
        if (!in_array($question['status'], ['new', 'assigned', 'in_progress'], true)) {
            throw new WorkflowException((string) $question['status'], 'assigned');
        }
        Database::transaction(static function () use ($question, $scholarId, $byUserId, $reason, $previous): void {
            if ($previous > 0) {
                Database::execute('UPDATE question_assignments SET unassigned_at = UTC_TIMESTAMP() WHERE question_id = ? AND unassigned_at IS NULL', [(int) $question['id']]);
            }
            Database::execute(
                'INSERT INTO question_assignments (question_id, scholar_id, assigned_by, reason) VALUES (?, ?, ?, ?)',
                [(int) $question['id'], $scholarId, $byUserId, $reason !== null ? mb_substr($reason, 0, 255) : null],
            );
        });

        // new/assigned → assigned ; in_progress (réassignation) → assigned
        return QuestionWorkflow::transition($question, 'assigned', $byUserId, $reason ?? ($previous > 0 ? 'Réassignation' : null), ['assigned_to' => $scholarId]);
    }

    /** Le professeur s'attribue lui-même une question de ses rubriques (§4.5). @param array<string, mixed> $question */
    public static function selfAssign(array $question, int $scholarId): array
    {
        if (!self::canSelfAssign($question, $scholarId)) {
            throw new WorkflowException((string) $question['status'], 'assigned');
        }

        return self::assign($question, $scholarId, $scholarId, 'Auto-attribution');
    }

    /** @param array<string, mixed> $question */
    public static function canSelfAssign(array $question, int $scholarId): bool
    {
        if ($question['status'] !== 'new' || !empty($question['assigned_to'])) {
            return false;
        }
        // Question adressée à ce professeur : il peut la prendre même sans auto-attribution générale
        if ((int) ($question['requested_scholar_id'] ?? 0) === $scholarId) {
            return Database::fetch("SELECT 1 FROM users u JOIN scholar_profiles sp ON sp.user_id = u.id WHERE u.id = ? AND u.status = 'active' AND u.deleted_at IS NULL", [$scholarId]) !== null;
        }

        return Database::fetch(
            'SELECT 1 FROM scholar_rubriques sr JOIN scholar_profiles sp ON sp.user_id = sr.user_id
             JOIN users u ON u.id = sp.user_id AND u.status = ? AND u.deleted_at IS NULL
             WHERE sr.user_id = ? AND sr.rubrique_id = ? AND sp.can_self_assign = 1 LIMIT 1',
            ['active', $scholarId, (int) $question['rubrique_id']],
        ) !== null;
    }

    /**
     * Assignation automatique (option) : professeur disponible de la rubrique, parlant la langue de la réponse,
     * avec le moins de questions ouvertes et sous sa charge maximale. Retourne l'identifiant choisi ou null.
     *
     * @param array<string, mixed> $question
     */
    public static function autoAssign(array $question): ?int
    {
        if ($question['status'] !== 'new') {
            return null;
        }
        // Professeur demandé par le membre : assigné directement s'il est actif, disponible et sous sa charge maximale
        $requested = (int) ($question['requested_scholar_id'] ?? 0);
        if ($requested > 0) {
            $ok = Database::fetch(
                "SELECT sp.user_id FROM scholar_profiles sp JOIN users u ON u.id = sp.user_id AND u.status = 'active' AND u.deleted_at IS NULL
                 WHERE sp.user_id = ? AND sp.is_available = 1
                   AND (SELECT COUNT(*) FROM questions q WHERE q.assigned_to = sp.user_id AND q.status IN ('assigned','in_progress','needs_info') AND q.deleted_at IS NULL) < sp.max_open_questions",
                [$requested],
            );
            if ($ok !== null) {
                self::assign($question, $requested, null, 'Professeur demandé par le membre');

                return $requested;
            }
        }
        if ((\App\Models\Setting::get('auto_assign') ?? '0') !== '1') {
            return null;
        }
        $candidates = Database::fetchAll(
            "SELECT sp.user_id, sp.max_open_questions, FIND_IN_SET(?, sp.languages) > 0 AS speaks,
                    (SELECT COUNT(*) FROM questions q WHERE q.assigned_to = sp.user_id AND q.status IN ('assigned','in_progress','needs_info') AND q.deleted_at IS NULL) AS open_count
             FROM scholar_profiles sp
             JOIN scholar_rubriques sr ON sr.user_id = sp.user_id AND sr.rubrique_id = ?
             JOIN users u ON u.id = sp.user_id AND u.status = 'active' AND u.deleted_at IS NULL
             WHERE sp.is_available = 1
             HAVING open_count < sp.max_open_questions
             ORDER BY speaks DESC, open_count ASC, sp.sort_order ASC, sp.user_id ASC",
            [(string) $question['answer_language'], (int) $question['rubrique_id']],
        );
        if ($candidates === []) {
            return null;
        }
        $scholarId = (int) $candidates[0]['user_id'];
        self::assign($question, $scholarId, null, 'Assignation automatique');

        return $scholarId;
    }

    /** Historique des assignations d'une question. @return list<array<string, mixed>> */
    public static function history(int $questionId): array
    {
        return Database::fetchAll(
            'SELECT a.*, s.full_name AS scholar_name, b.full_name AS assigned_by_name FROM question_assignments a
             LEFT JOIN users s ON s.id = a.scholar_id LEFT JOIN users b ON b.id = a.assigned_by
             WHERE a.question_id = ? ORDER BY a.assigned_at, a.id',
            [$questionId],
        );
    }
}
