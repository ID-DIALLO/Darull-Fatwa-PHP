<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\MediaFile;
use App\Models\Question;
use App\Models\User;

/**
 * Réponses des professeurs (§4.2.4) : réponse officielle (texte riche et/ou vocal + références structurées),
 * relecture obligatoire, demande de précision, note interne, proposition de publication, envoi WhatsApp.
 */
final class AnswerService
{
    public const COLLECTIONS = ['bukhari', 'muslim', 'abudawud', 'tirmidhi', 'nasai', 'ibnmajah', 'muwatta', 'ahmad', 'other'];

    /**
     * Enregistre la réponse officielle. Si le professeur est soumis à relecture, la réponse attend la validation
     * d'un administrateur (review_status = pending) et le membre ne la voit pas encore.
     *
     * @param array<string, mixed> $question
     * @param array<string, mixed> $scholar
     * @param list<array<string, mixed>> $references
     * @return array{message_id: int, review: bool}
     */
    public static function answer(array $question, array $scholar, ?string $html, ?string $mediaUuid, array $references, string $language): array
    {
        $body = Html::purify($html, 'rich');
        $media = self::ownMedia($mediaUuid, (int) $scholar['id']);
        if ($body === '' && $media === null) {
            throw new \InvalidArgumentException('empty_answer');
        }
        if (!in_array($question['status'], ['assigned', 'in_progress', 'needs_info'], true) || (int) $question['assigned_to'] !== (int) $scholar['id']) {
            throw new WorkflowException((string) $question['status'], 'answered');
        }
        $requiresReview = (int) (Database::value('SELECT requires_review FROM scholar_profiles WHERE user_id = ?', [(int) $scholar['id']]) ?? 0) === 1;

        Database::execute(
            'INSERT INTO question_messages (question_id, sender_id, kind, body, media_id, references_json, language, review_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                (int) $question['id'], (int) $scholar['id'], 'answer', $body !== '' ? $body : null,
                $media !== null ? (int) $media['id'] : null,
                $references !== [] ? json_encode(self::cleanReferences($references), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : null,
                $language, $requiresReview ? 'pending' : 'not_required',
            ],
        );
        $messageId = Database::lastInsertId();
        ActivityLog::record('question.answer_' . ($requiresReview ? 'submitted' : 'sent'), 'question', (int) $question['id'], ['reference' => $question['reference'], 'message_id' => $messageId], (int) $scholar['id']);

        if ($question['status'] === 'assigned') {
            $question = QuestionWorkflow::transition($question, 'in_progress', (int) $scholar['id']);
        }
        if ($requiresReview) {
            // La question reste « en cours » jusqu'à la validation ; les administrateurs sont prévenus.
            $staff = Database::fetchAll("SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug IN ('admin','superadmin') AND u.status = 'active' AND u.deleted_at IS NULL");
            NotificationService::notifyMany(
                array_map(static fn (array $r): int => (int) $r['id'], $staff),
                'question.review_pending',
                ['reference' => (string) $question['reference']],
                route_exists('admin.questions.show') ? route('admin.questions.show', ['id' => $question['id']]) : '/admin',
            );
        } else {
            QuestionWorkflow::transition($question, 'answered', (int) $scholar['id'], null, ['answer_message_id' => $messageId]);
        }

        return ['message_id' => $messageId, 'review' => $requiresReview];
    }

    /** Validation par un administrateur d'une réponse en relecture (§4.2.4). @param array<string, mixed> $question */
    public static function approve(array $question, int $messageId, int $adminId): array
    {
        Database::execute('UPDATE question_messages SET review_status = ?, reviewed_by = ?, reviewed_at = UTC_TIMESTAMP() WHERE id = ? AND question_id = ?', ['approved', $adminId, $messageId, (int) $question['id']]);
        ActivityLog::record('question.answer_approved', 'question', (int) $question['id'], ['message_id' => $messageId], $adminId);
        $scholarId = (int) (Database::value('SELECT sender_id FROM question_messages WHERE id = ?', [$messageId]) ?? 0);
        if ($scholarId > 0) {
            NotificationService::notify($scholarId, 'question.review_approved', ['reference' => (string) $question['reference']], route_exists('scholar.questions.show') ? route('scholar.questions.show', ['reference' => $question['reference']]) : null);
        }
        if (in_array($question['status'], ['assigned', 'needs_info'], true)) {
            $question = QuestionWorkflow::transition($question, 'in_progress', $adminId);
        }

        return QuestionWorkflow::transition($question, 'answered', $adminId, 'Réponse validée', ['answer_message_id' => $messageId]);
    }

    /** Refus d'une réponse en relecture : note interne pour le professeur, qui doit reformuler. @param array<string, mixed> $question */
    public static function reject(array $question, int $messageId, int $adminId, string $reason): void
    {
        Database::execute('UPDATE question_messages SET review_status = ?, reviewed_by = ?, reviewed_at = UTC_TIMESTAMP() WHERE id = ? AND question_id = ?', ['rejected', $adminId, $messageId, (int) $question['id']]);
        self::internalNote($question, $adminId, $reason);
        ActivityLog::record('question.answer_rejected', 'question', (int) $question['id'], ['message_id' => $messageId, 'reason' => $reason], $adminId);
        $scholarId = (int) (Database::value('SELECT sender_id FROM question_messages WHERE id = ?', [$messageId]) ?? 0);
        if ($scholarId > 0) {
            NotificationService::notify($scholarId, 'question.review_rejected', ['reference' => (string) $question['reference'], 'reason' => $reason], route_exists('scholar.questions.show') ? route('scholar.questions.show', ['reference' => $question['reference']]) : null);
        }
    }

    /** Demande de précision au membre (in_progress → needs_info). @param array<string, mixed> $question */
    public static function requestInfo(array $question, int $scholarId, ?string $text, ?string $mediaUuid): array
    {
        $body = Html::purify($text, 'basic');
        $media = self::ownMedia($mediaUuid, $scholarId);
        if ($body === '' && $media === null) {
            throw new \InvalidArgumentException('empty_message');
        }
        if ($question['status'] === 'assigned') {
            $question = QuestionWorkflow::transition($question, 'in_progress', $scholarId);
        }
        Database::execute(
            'INSERT INTO question_messages (question_id, sender_id, kind, body, media_id, review_status) VALUES (?, ?, ?, ?, ?, ?)',
            [(int) $question['id'], $scholarId, 'info_request', $body !== '' ? $body : null, $media !== null ? (int) $media['id'] : null, 'not_required'],
        );

        return QuestionWorkflow::transition($question, 'needs_info', $scholarId, 'Demande de précision');
    }

    /** Note interne, visible uniquement par l'équipe. @param array<string, mixed> $question */
    public static function internalNote(array $question, int $authorId, string $text): int
    {
        $body = Html::fromPlainText($text);
        if ($body === '') {
            throw new \InvalidArgumentException('empty_message');
        }
        Database::execute(
            'INSERT INTO question_messages (question_id, sender_id, kind, body, review_status) VALUES (?, ?, ?, ?, ?)',
            [(int) $question['id'], $authorId, 'internal_note', $body, 'not_required'],
        );
        ActivityLog::record('question.internal_note', 'question', (int) $question['id'], [], $authorId);

        return Database::lastInsertId();
    }

    /** « Marqué comme envoyé sur WhatsApp » : horodatage + journal whatsapp_messages. @param array<string, mixed> $question */
    public static function markSentViaWhatsApp(array $question, int $scholarId, string $text): void
    {
        $phone = (string) ($question['whatsapp_number'] ?? '');
        Database::execute('UPDATE question_messages SET sent_via_whatsapp_at = UTC_TIMESTAMP() WHERE question_id = ? AND kind = ? AND sent_via_whatsapp_at IS NULL', [(int) $question['id'], 'answer']);
        \App\Services\WhatsApp\LinkGateway::logManual('out', $phone, $text, ['question_id' => (int) $question['id'], 'user_id' => (int) $question['user_id'], 'created_by' => $scholarId, 'type' => 'link']);
        ActivityLog::record('question.whatsapp_sent', 'question', (int) $question['id'], ['reference' => $question['reference']], $scholarId);
    }

    /** Proposition de publication anonymisée (titre et question reformulés, sans donnée personnelle). @param array<string, mixed> $question */
    public static function proposePublication(array $question, int $scholarId, string $title, string $rephrased): void
    {
        $note = __('scholar.publication.note_prefix', [], \App\Core\Lang::getDefault()) . "\n" . trim($title) . "\n\n" . trim($rephrased);
        self::internalNote($question, $scholarId, $note);
        ActivityLog::record('question.publication_proposed', 'question', (int) $question['id'], ['reference' => $question['reference'], 'title' => $title], $scholarId);
        $staff = Database::fetchAll("SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug IN ('admin','superadmin') AND u.status = 'active' AND u.deleted_at IS NULL");
        NotificationService::notifyMany(array_map(static fn (array $r): int => (int) $r['id'], $staff), 'question.publication_proposed', ['reference' => (string) $question['reference']], route_exists('admin.questions.show') ? route('admin.questions.show', ['id' => $question['id']]) : '/admin');
    }

    /** Demande de réassignation (avec motif) : note interne + notification aux modérateurs/administrateurs. @param array<string, mixed> $question */
    public static function requestReassignment(array $question, int $scholarId, string $reason): void
    {
        self::internalNote($question, $scholarId, __('scholar.reassign.note_prefix', [], \App\Core\Lang::getDefault()) . ' ' . trim($reason));
        ActivityLog::record('question.reassign_requested', 'question', (int) $question['id'], ['reference' => $question['reference'], 'reason' => $reason], $scholarId);
        $staff = Database::fetchAll("SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug IN ('moderator','admin','superadmin') AND u.status = 'active' AND u.deleted_at IS NULL");
        NotificationService::notifyMany(array_map(static fn (array $r): int => (int) $r['id'], $staff), 'question.reassign_requested', ['reference' => (string) $question['reference'], 'reason' => $reason], route_exists('admin.questions.show') ? route('admin.questions.show', ['id' => $question['id']]) : '/admin');
    }

    /** Transcription manuelle d'un audio (utile pour la publication). */
    public static function saveTranscript(int $mediaId, ?string $text): void
    {
        Database::execute('UPDATE media_files SET transcript = ? WHERE id = ?', [$text !== null && trim($text) !== '' ? trim($text) : null, $mediaId]);
    }

    /**
     * Nettoyage des références structurées envoyées par le formulaire.
     *
     * @param list<array<string, mixed>> $references
     * @return list<array<string, mixed>>
     */
    public static function cleanReferences(array $references): array
    {
        $clean = [];
        foreach ($references as $ref) {
            if (!is_array($ref)) {
                continue;
            }
            $type = (string) ($ref['type'] ?? '');
            if ($type === 'quran') {
                $surah = (int) ($ref['surah'] ?? 0);
                $from = (int) ($ref['ayah_from'] ?? 0);
                if ($surah < 1 || $surah > 114 || $from < 1) {
                    continue;
                }
                $to = max($from, (int) ($ref['ayah_to'] ?? $from));
                $clean[] = ['type' => 'quran', 'surah' => $surah, 'ayah_from' => $from, 'ayah_to' => $to];
            } elseif ($type === 'hadith') {
                $collection = (string) ($ref['collection'] ?? 'other');
                $number = trim((string) ($ref['number'] ?? ''));
                if ($number === '') {
                    continue;
                }
                $clean[] = array_filter([
                    'type'            => 'hadith',
                    'collection'      => in_array($collection, self::COLLECTIONS, true) ? $collection : 'other',
                    'collection_name' => $collection === 'other' ? mb_substr(trim((string) ($ref['collection_name'] ?? '')), 0, 120) : null,
                    'number'          => mb_substr($number, 0, 40),
                    'grade'           => mb_substr(trim((string) ($ref['grade'] ?? '')), 0, 80) ?: null,
                ], static fn ($v) => $v !== null && $v !== '');
            } elseif ($type === 'book') {
                $title = trim((string) ($ref['title'] ?? ''));
                if ($title === '') {
                    continue;
                }
                $clean[] = array_filter([
                    'type'   => 'book',
                    'title'  => mb_substr($title, 0, 200),
                    'author' => mb_substr(trim((string) ($ref['author'] ?? '')), 0, 120) ?: null,
                    'page'   => mb_substr(trim((string) ($ref['page'] ?? '')), 0, 40) ?: null,
                ], static fn ($v) => $v !== null && $v !== '');
            }
        }

        return $clean;
    }

    /** @return array<string, mixed>|null */
    private static function ownMedia(?string $uuid, int $ownerId): ?array
    {
        if ($uuid === null || $uuid === '') {
            return null;
        }
        $media = MediaFile::findByUuid($uuid);
        if ($media === null || (int) $media['owner_id'] !== $ownerId || MediaFile::isAttached((int) $media['id'])) {
            return null;
        }

        return $media;
    }

    /** Résumé texte d'une réponse (pour WhatsApp). */
    public static function summary(?string $html, int $length = 300): string
    {
        return excerpt($html, $length);
    }

    /** Nom à afficher au professeur pour le demandeur (pseudonyme ou nom, §4.5). @param array<string, mixed> $member */
    public static function askerLabel(array $member): string
    {
        return User::publicName($member);
    }

    /** @param array<string, mixed> $question */
    public static function phoneForScholar(array $question): array
    {
        $number = (string) ($question['whatsapp_number'] ?? '');
        $visible = in_array($question['answer_channel'], ['whatsapp', 'both'], true) && $number !== '';

        return ['visible' => $visible, 'display' => $visible ? PhoneNumber::format($number) : mask_phone($number !== '' ? $number : null), 'raw' => $visible ? $number : null];
    }

    /** Les autres questions d'un membre (titres seulement), §4.5. @return list<array<string, mixed>> */
    public static function otherQuestions(int $userId, int $exceptId): array
    {
        return Database::fetchAll(
            'SELECT reference, title, status, created_at FROM questions WHERE user_id = ? AND id <> ? AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 10',
            [$userId, $exceptId],
        );
    }

    /** @return array<string, mixed>|null */
    public static function pendingReview(int $questionId): ?array
    {
        return Database::fetch('SELECT * FROM question_messages WHERE question_id = ? AND kind = ? AND review_status = ? ORDER BY id DESC LIMIT 1', [$questionId, 'answer', 'pending']);
    }
}
