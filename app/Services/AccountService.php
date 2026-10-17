<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Models\User;
use Ramsey\Uuid\Uuid;

/**
 * Création de compte, export des données personnelles et suppression du compte (§4.1, §12.2).
 */
final class AccountService
{
    /**
     * Crée un membre et retourne son identifiant.
     *
     * @param array<string, mixed> $data Données validées
     */
    public static function register(array $data, ?string $ip = null): int
    {
        $email = isset($data['email']) && $data['email'] !== '' ? mb_strtolower(trim((string) $data['email'])) : null;
        $phone = isset($data['phone']) && $data['phone'] !== '' ? (string) $data['phone'] : null;

        return User::create([
            'uuid'              => Uuid::uuid4()->toString(),
            'role_id'           => User::roleId('member'),
            'full_name'         => trim((string) $data['full_name']),
            'display_name'      => isset($data['display_name']) && trim((string) $data['display_name']) !== '' ? trim((string) $data['display_name']) : null,
            'email'             => $email,
            'phone'             => $phone,
            'whatsapp'          => isset($data['whatsapp']) && $data['whatsapp'] !== '' ? (string) $data['whatsapp'] : $phone,
            'password_hash'     => Auth::hashPassword((string) $data['password']),
            'locale'            => $data['locale'],
            'gender'            => in_array($data['gender'] ?? '', ['m', 'f'], true) ? $data['gender'] : null,
            'country'           => isset($data['country']) && $data['country'] !== '' ? strtoupper((string) $data['country']) : null,
            'city'              => isset($data['city']) && trim((string) $data['city']) !== '' ? trim((string) $data['city']) : null,
            'status'            => 'active',
            'last_login_at'     => gmdate('Y-m-d H:i:s'),
            'last_login_ip'     => $ip,
        ]);
    }

    /**
     * Toutes les données personnelles d'un membre (export JSON, §4.1).
     *
     * @return array<string, mixed>
     */
    public static function export(int $userId): array
    {
        $user = User::findWithRole($userId) ?? [];
        unset($user['password_hash'], $user['role_id'], $user['last_login_ip']);

        $questions = Database::fetchAll(
            'SELECT q.id, q.reference, q.title, q.input_type, q.spoken_language, q.answer_language, q.answer_channel,
                    q.visibility, q.status, q.created_at, q.answered_at, q.closed_at, rt.name AS rubrique
             FROM questions q
             LEFT JOIN rubrique_translations rt ON rt.rubrique_id = q.rubrique_id AND rt.locale = ?
             WHERE q.user_id = ? AND q.deleted_at IS NULL ORDER BY q.created_at',
            [(string) ($user['locale'] ?? 'fr'), $userId],
        );
        foreach ($questions as &$question) {
            $question['messages'] = Database::fetchAll(
                'SELECT kind, body, language, created_at FROM question_messages
                 WHERE question_id = ? AND kind <> ? ORDER BY created_at',
                [(int) $question['id'], 'internal_note'],
            );
            unset($question['id']);
        }
        unset($question);

        return [
            'exported_at'  => gmdate('c'),
            'profile'      => $user,
            'questions'    => $questions,
            'forum_topics' => Database::fetchAll('SELECT title, body, language, status, created_at FROM forum_topics WHERE user_id = ? AND deleted_at IS NULL ORDER BY created_at', [$userId]),
            'forum_posts'  => Database::fetchAll('SELECT topic_id, body, status, created_at FROM forum_posts WHERE user_id = ? AND deleted_at IS NULL ORDER BY created_at', [$userId]),
            'ideas'        => Database::fetchAll('SELECT title, description, category, status, votes_count, created_at FROM ideas WHERE user_id = ? AND deleted_at IS NULL ORDER BY created_at', [$userId]),
            'comments'     => Database::fetchAll('SELECT commentable_type, body, status, created_at FROM comments WHERE user_id = ? ORDER BY created_at', [$userId]),
            'contact_messages' => $user['email'] !== null
                ? Database::fetchAll('SELECT subject, message, status, created_at FROM contact_messages WHERE email = ? ORDER BY created_at', [$user['email']])
                : [],
        ];
    }

    /**
     * Supprime le compte (R16) : questions privées effacées, Q/R publiées (anonymes) conservées,
     * profil anonymisé et désactivé, jetons et notifications supprimés.
     */
    public static function delete(int $userId): void
    {
        $user = User::find($userId);
        if ($user === null) {
            return;
        }
        Database::transaction(static function () use ($userId, $user): void {
            // Les publications anonymisées gardent question_id = NULL grâce à ON DELETE SET NULL.
            Database::execute('DELETE FROM questions WHERE user_id = ?', [$userId]);
            Database::execute('DELETE FROM user_tokens WHERE user_id = ?', [$userId]);
            Database::execute('DELETE FROM notifications WHERE user_id = ?', [$userId]);
            Database::execute('DELETE FROM favorites WHERE user_id = ?', [$userId]);
            Database::execute('DELETE FROM forum_subscriptions WHERE user_id = ?', [$userId]);
            if (!empty($user['avatar_path'])) {
                ImageService::delete((string) $user['avatar_path']);
            }
            // La contrainte exige un e-mail ou un téléphone : on garde une adresse technique non routable.
            Database::execute(
                'UPDATE users SET full_name = ?, display_name = NULL, email = ?, phone = NULL, whatsapp = NULL,
                        password_hash = ?, avatar_path = NULL, city = NULL, gender = NULL, status = ?, deleted_at = UTC_TIMESTAMP()
                 WHERE id = ?',
                ['Compte supprimé', 'deleted-' . $user['uuid'] . '@invalid.darulfatwa', Auth::hashPassword(bin2hex(random_bytes(32))), 'suspended', $userId],
            );
        });
    }
}
