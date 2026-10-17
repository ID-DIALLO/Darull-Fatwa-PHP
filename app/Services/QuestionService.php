<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\MediaFile;
use App\Models\Question;
use App\Models\User;

/**
 * Création d'une question (§4.2.1) et ajout de messages au fil (précisions, questions de suivi).
 */
final class QuestionService
{
    /** Nombre de questions posées par le membre dans les dernières 24 h. */
    public static function countLast24h(int $userId): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM questions WHERE user_id = ? AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)', [$userId]);
    }

    public static function dailyLimit(): int
    {
        return max(1, (int) (\App\Models\Setting::get('questions_per_day') ?? config('upload.questions_per_day', 5)));
    }

    public static function lateHours(): int
    {
        return max(1, (int) (\App\Models\Setting::get('question_late_hours') ?? config('upload.question_late_hours', 72)));
    }

    /**
     * Crée la question et son premier message dans une transaction ; la référence DF-AAAA-NNNNNN est
     * dérivée de l'identifiant. Retourne la question créée.
     *
     * @param array<string, mixed> $user
     * @param array{rubrique_id: int, title?: string|null, body?: string|null, media_uuid?: string|null, spoken_language: string,
     *               answer_language: string, answer_channel: string, whatsapp_number?: string|null, visibility: string,
     *               source?: string, ip?: string|null, user_agent?: string|null} $data
     * @return array<string, mixed>
     * @throws \InvalidArgumentException si ni texte ni audio
     */
    public static function create(array $user, array $data): array
    {
        $body = Html::fromPlainText($data['body'] ?? null);
        $media = !empty($data['media_uuid']) ? MediaFile::findByUuid((string) $data['media_uuid']) : null;
        if ($media !== null && ((int) $media['owner_id'] !== (int) $user['id'] || MediaFile::isAttached((int) $media['id']))) {
            $media = null;
        }
        if ($body === '' && $media === null) {
            throw new \InvalidArgumentException('empty_question');
        }
        $inputType = $media !== null ? ($body !== '' ? 'mixed' : 'audio') : 'text';

        return Database::transaction(static function () use ($user, $data, $body, $media, $inputType): array {
            $id = Question::create([
                'user_id'         => (int) $user['id'],
                'rubrique_id'     => (int) $data['rubrique_id'],
                'title'           => isset($data['title']) && trim((string) $data['title']) !== '' ? mb_substr(trim((string) $data['title']), 0, 200) : null,
                'input_type'      => $inputType,
                'source'          => $data['source'] ?? 'web',
                'spoken_language' => $data['spoken_language'],
                'answer_language' => $data['answer_language'],
                'answer_channel'  => $data['answer_channel'],
                'whatsapp_number' => $data['answer_channel'] !== 'platform' ? ($data['whatsapp_number'] ?? null) : null,
                'visibility'      => $data['visibility'],
                'status'          => 'new',
                'ip_address'      => $data['ip'] ?? null,
                'user_agent'      => isset($data['user_agent']) ? mb_substr((string) $data['user_agent'], 0, 255) : null,
            ]);
            $reference = Question::makeReference($id);
            Question::update($id, ['reference' => $reference]);

            Database::execute(
                'INSERT INTO question_messages (question_id, sender_id, kind, body, media_id, language, review_status) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$id, (int) $user['id'], 'question', $body !== '' ? $body : null, $media !== null ? (int) $media['id'] : null, $data['spoken_language'], 'not_required'],
            );
            Database::execute('INSERT INTO question_status_logs (question_id, from_status, to_status, changed_by, comment) VALUES (?, NULL, ?, ?, NULL)', [$id, 'new', (int) $user['id']]);
            ActivityLog::record('question.created', 'question', $id, ['reference' => $reference, 'input_type' => $inputType, 'source' => $data['source'] ?? 'web'], (int) $user['id']);

            return Question::find($id) ?? [];
        });
    }

    /** Notifications après création (hors transaction) : confirmation au membre, alerte à l'équipe. @param array<string, mixed> $question */
    public static function notifyCreated(array $question): void
    {
        $owner = User::findWithRole((int) $question['user_id']);
        if ($owner !== null) {
            NotificationService::notify(
                (int) $owner['id'],
                'question.received',
                ['reference' => (string) $question['reference']],
                route('account.questions.show', ['reference' => $question['reference']], (string) $owner['locale']),
                'question_received',
                ['reference' => (string) $question['reference'], 'late_hours' => self::lateHours()],
            );
        }
        // Modérateurs et administrateurs : nouvelle question à assigner (sans e-mail individuel : récapitulatif quotidien en Phase 4)
        $staff = Database::fetchAll("SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug IN ('moderator','admin','superadmin') AND u.status = 'active' AND u.deleted_at IS NULL");
        NotificationService::notifyMany(
            array_map(static fn (array $r): int => (int) $r['id'], $staff),
            'question.new_for_staff',
            ['reference' => (string) $question['reference']],
            route_exists('admin.questions.show') ? route('admin.questions.show', ['id' => $question['id']]) : '/admin',
            null,
        );
    }

    /**
     * Ajoute un message du membre au fil : précision (needs_info → in_progress) ou question de suivi
     * (answered → in_progress). Retourne le message créé.
     *
     * @param array<string, mixed> $question
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    public static function addMemberMessage(array $question, array $user, ?string $text, ?string $mediaUuid): array
    {
        $body = Html::fromPlainText($text);
        $media = $mediaUuid !== null && $mediaUuid !== '' ? MediaFile::findByUuid($mediaUuid) : null;
        if ($media !== null && ((int) $media['owner_id'] !== (int) $user['id'] || MediaFile::isAttached((int) $media['id']))) {
            $media = null;
        }
        if ($body === '' && $media === null) {
            throw new \InvalidArgumentException('empty_message');
        }
        Database::execute(
            'INSERT INTO question_messages (question_id, sender_id, kind, body, media_id, language, review_status) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [(int) $question['id'], (int) $user['id'], 'follow_up', $body !== '' ? $body : null, $media !== null ? (int) $media['id'] : null, $question['spoken_language'], 'not_required'],
        );
        $messageId = Database::lastInsertId();
        ActivityLog::record('question.follow_up', 'question', (int) $question['id'], ['reference' => $question['reference']], (int) $user['id']);

        if (in_array($question['status'], ['needs_info', 'answered'], true)) {
            QuestionWorkflow::transition($question, 'in_progress', (int) $user['id'], $question['status'] === 'needs_info' ? 'Précision du membre' : 'Question de suivi');
        } elseif (!empty($question['assigned_to']) && in_array($question['status'], ['assigned', 'in_progress'], true)) {
            $scholar = User::findWithRole((int) $question['assigned_to']);
            NotificationService::notify((int) $question['assigned_to'], 'question.info_received', ['reference' => (string) $question['reference']],
                $scholar !== null && route_exists('scholar.questions.show') ? route('scholar.questions.show', ['reference' => $question['reference']], (string) $scholar['locale']) : null);
        }

        return Database::fetch('SELECT * FROM question_messages WHERE id = ?', [$messageId]) ?? [];
    }

    /** Le membre clôt sa question (« Ma question est résolue »). @param array<string, mixed> $question */
    public static function resolve(array $question, int $userId): array
    {
        return QuestionWorkflow::transition($question, 'closed', $userId, 'Résolue par le membre');
    }

    /**
     * Droit de lecture d'une question (§11.5) : propriétaire, professeur assigné, professeur de la rubrique
     * (si l'auto-attribution est autorisée), modérateur ou administrateur.
     *
     * @param array<string, mixed> $question
     * @param array<string, mixed>|null $user
     */
    public static function canView(array $question, ?array $user): bool
    {
        if ($user === null) {
            return false;
        }
        $userId = (int) $user['id'];
        if ((int) $question['user_id'] === $userId) {
            return true;
        }
        $role = (string) ($user['role'] ?? '');
        if (in_array($role, ['moderator', 'admin', 'superadmin'], true)) {
            return true;
        }
        if ($role === 'scholar') {
            if ((int) ($question['assigned_to'] ?? 0) === $userId) {
                return true;
            }
            $allowed = Database::fetch(
                'SELECT 1 FROM scholar_rubriques sr JOIN scholar_profiles sp ON sp.user_id = sr.user_id
                 WHERE sr.user_id = ? AND sr.rubrique_id = ? AND sp.can_self_assign = 1 LIMIT 1',
                [$userId, (int) $question['rubrique_id']],
            );

            return $allowed !== null && in_array($question['status'], ['new', 'assigned', 'in_progress', 'needs_info'], true) && empty($question['assigned_to']);
        }

        return false;
    }
}
