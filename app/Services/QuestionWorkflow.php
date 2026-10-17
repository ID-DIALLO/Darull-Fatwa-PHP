<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Question;

/**
 * Cycle de vie d'une question (§4.2.3). Toute modification de statut passe par transition(), qui
 * valide la transition, met à jour les horodatages, écrit question_status_logs et activity_logs,
 * puis déclenche les notifications.
 */
final class QuestionWorkflow
{
    /** Transitions autorisées : statut de départ => statuts d'arrivée. */
    public const TRANSITIONS = [
        'new'         => ['assigned', 'rejected'],
        'assigned'    => ['in_progress', 'assigned', 'rejected'],
        'in_progress' => ['needs_info', 'answered', 'assigned', 'rejected'],
        'needs_info'  => ['in_progress', 'rejected'],
        'answered'    => ['in_progress', 'published', 'closed'],
        'published'   => ['closed'],
        'rejected'    => [],
        'closed'      => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /**
     * @param array<string, mixed> $question Ligne de la table questions (au moins id, status, user_id, reference, created_at)
     * @param array<string, mixed> $extra Colonnes supplémentaires à mettre à jour (assigned_to, rejection_reason, answer_message_id…)
     * @return array<string, mixed> La question mise à jour
     * @throws WorkflowException si la transition est interdite
     */
    public static function transition(array $question, string $to, ?int $actorId, ?string $comment = null, array $extra = []): array
    {
        $from = (string) $question['status'];
        if (!in_array($to, Question::STATUSES, true) || !self::canTransition($from, $to)) {
            throw new WorkflowException($from, $to);
        }
        $id = (int) $question['id'];
        $now = gmdate('Y-m-d H:i:s');
        $update = ['status' => $to] + $extra;

        switch ($to) {
            case 'assigned':
                $update['assigned_at'] = $now;
                break;
            case 'answered':
                $update['answered_at'] = $now;
                if (empty($question['first_response_minutes'])) {
                    $created = strtotime((string) $question['created_at'] . ' UTC');
                    $update['first_response_minutes'] = max(0, (int) round((time() - (int) $created) / 60));
                }
                break;
            case 'closed':
                $update['closed_at'] = $now;
                break;
            case 'rejected':
                $update['closed_at'] = $now;
                break;
        }

        Database::transaction(static function () use ($id, $update, $from, $to, $actorId, $comment, $question): void {
            Question::update($id, $update);
            Database::execute(
                'INSERT INTO question_status_logs (question_id, from_status, to_status, changed_by, comment) VALUES (?, ?, ?, ?, ?)',
                [$id, $from, $to, $actorId, $comment !== null ? mb_substr($comment, 0, 255) : null],
            );
            ActivityLog::record('question.status.' . $to, 'question', $id, [
                'from'      => $from,
                'to'        => $to,
                'reference' => $question['reference'] ?? null,
                'comment'   => $comment,
            ] + array_intersect_key($update, array_flip(['assigned_to', 'rejection_reason'])), $actorId);
        });

        $fresh = Question::find($id) ?? $question + $update;
        self::afterTransition($fresh, $from, $to, $actorId, $comment);

        return $fresh;
    }

    /** Notifications déclenchées par un changement de statut (§4.12). @param array<string, mixed> $question */
    private static function afterTransition(array $question, string $from, string $to, ?int $actorId, ?string $comment): void
    {
        $userId = (int) $question['user_id'];
        $data = ['reference' => (string) $question['reference']];
        $owner = \App\Models\User::findWithRole($userId);
        $memberUrl = $owner !== null ? route('account.questions.show', ['reference' => $question['reference']], (string) $owner['locale']) : null;

        switch ($to) {
            case 'assigned':
                if (!empty($question['assigned_to'])) {
                    $scholar = \App\Models\User::findWithRole((int) $question['assigned_to']);
                    $url = $scholar !== null && route_exists('scholar.questions.show') ? route('scholar.questions.show', ['reference' => $question['reference']], (string) $scholar['locale']) : null;
                    NotificationService::notify((int) $question['assigned_to'], 'question.assigned', $data, $url);
                }
                break;
            case 'needs_info':
                NotificationService::notify($userId, 'question.needs_info', $data, $memberUrl);
                break;
            case 'answered':
                NotificationService::notify($userId, 'question.answered', $data, $memberUrl);
                break;
            case 'rejected':
                NotificationService::notify($userId, 'question.rejected', $data + ['reason' => (string) ($question['rejection_reason'] ?? $comment ?? '')], $memberUrl);
                break;
            case 'in_progress':
                // Le membre a répondu à une demande de précision ou posé une question de suivi : prévenir le professeur
                if (in_array($from, ['needs_info', 'answered'], true) && !empty($question['assigned_to']) && $actorId === $userId) {
                    $scholar = \App\Models\User::findWithRole((int) $question['assigned_to']);
                    $url = $scholar !== null && route_exists('scholar.questions.show') ? route('scholar.questions.show', ['reference' => $question['reference']], (string) $scholar['locale']) : null;
                    NotificationService::notify((int) $question['assigned_to'], $from === 'needs_info' ? 'question.info_received' : 'question.follow_up', $data, $url);
                }
                break;
        }
    }
}
