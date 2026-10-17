<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Core\Cache;
use App\Core\Database;
use App\Core\Lang;
use App\Models\User;
use App\Services\ActivityLog;
use App\Services\ImageService;
use App\Services\Translations;

/**
 * Fiches professeurs (§4.6.3) : rubriques, langues, charge maximale, relecture obligatoire,
 * auto-attribution, visibilité publique, ordre d'affichage, photo, titre et biographie traduits.
 */
final class ScholarAdminService
{
    /** @return list<array<string, mixed>> */
    public static function all(): array
    {
        return Database::fetchAll(
            "SELECT u.id, u.full_name, u.email, u.phone, u.status, u.country, sp.languages, sp.max_open_questions, sp.can_self_assign, sp.requires_review,
                    sp.is_public, sp.is_available, sp.sort_order, sp.photo_path,
                    (SELECT COUNT(*) FROM questions q WHERE q.assigned_to = u.id AND q.status IN ('assigned','in_progress','needs_info') AND q.deleted_at IS NULL) AS open_count,
                    (SELECT COUNT(*) FROM questions q WHERE q.assigned_to = u.id AND q.status IN ('answered','published','closed') AND q.deleted_at IS NULL) AS answered_count,
                    (SELECT GROUP_CONCAT(COALESCE(rt.name, rd.name) ORDER BY r.sort_order SEPARATOR ', ') FROM scholar_rubriques sr JOIN rubriques r ON r.id = sr.rubrique_id
                        LEFT JOIN rubrique_translations rt ON rt.rubrique_id = r.id AND rt.locale = ? LEFT JOIN rubrique_translations rd ON rd.rubrique_id = r.id AND rd.locale = ?
                        WHERE sr.user_id = u.id) AS rubriques
             FROM users u JOIN roles r ON r.id = u.role_id LEFT JOIN scholar_profiles sp ON sp.user_id = u.id
             WHERE r.slug = 'scholar' AND u.deleted_at IS NULL ORDER BY COALESCE(sp.sort_order, 999), u.full_name",
            [Lang::getLocale(), Lang::getDefault()],
        );
    }

    /** @return array<string, mixed>|null */
    public static function find(int $userId): ?array
    {
        $user = User::findWithRole($userId);
        if ($user === null || $user['role'] !== 'scholar') {
            return null;
        }
        $user['profile'] = Database::fetch('SELECT * FROM scholar_profiles WHERE user_id = ?', [$userId]) ?? [];
        $user['rubrique_ids'] = array_map(static fn (array $r): int => (int) $r['rubrique_id'], Database::fetchAll('SELECT rubrique_id FROM scholar_rubriques WHERE user_id = ?', [$userId]));
        $user['translations'] = Translations::all('scholar_translations', 'user_id', $userId);

        return $user;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed>|null $photo Entrée de $_FILES
     */
    public static function save(int $userId, array $data, ?array $photo, int $adminId): void
    {
        $languages = array_values(array_intersect((array) ($data['languages'] ?? []), ['fr', 'ar', 'en', 'bm', 'other']));
        $current = Database::fetch('SELECT photo_path FROM scholar_profiles WHERE user_id = ?', [$userId]);
        $photoPath = $current['photo_path'] ?? null;
        if ($photo !== null && ($photo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            ImageService::delete($photoPath);
            $photoPath = ImageService::store($photo, 'scholars', 480, 480, true);
        } elseif (!empty($data['remove_photo'])) {
            ImageService::delete($photoPath);
            $photoPath = null;
        }
        Database::execute(
            'INSERT INTO scholar_profiles (user_id, languages, max_open_questions, can_self_assign, requires_review, is_public, is_available, sort_order, photo_path)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE languages = VALUES(languages), max_open_questions = VALUES(max_open_questions), can_self_assign = VALUES(can_self_assign),
                requires_review = VALUES(requires_review), is_public = VALUES(is_public), is_available = VALUES(is_available), sort_order = VALUES(sort_order), photo_path = VALUES(photo_path)',
            [
                $userId, implode(',', $languages !== [] ? $languages : ['fr']),
                max(1, min(500, (int) ($data['max_open_questions'] ?? 20))),
                !empty($data['can_self_assign']) ? 1 : 0, !empty($data['requires_review']) ? 1 : 0,
                !empty($data['is_public']) ? 1 : 0, !empty($data['is_available']) ? 1 : 0,
                (int) ($data['sort_order'] ?? 0), $photoPath,
            ],
        );
        Database::execute('DELETE FROM scholar_rubriques WHERE user_id = ?', [$userId]);
        foreach (array_unique(array_map('intval', (array) ($data['rubriques'] ?? []))) as $rubriqueId) {
            if ($rubriqueId > 0) {
                Database::execute('INSERT IGNORE INTO scholar_rubriques (user_id, rubrique_id) VALUES (?, ?)', [$userId, $rubriqueId]);
            }
        }
        $translations = [];
        foreach (Lang::locales() as $locale) {
            $translations[$locale] = [
                'title' => Translations::text($data['title'][$locale] ?? null, 80),
                'bio'   => Translations::text($data['bio'][$locale] ?? null),
            ];
        }
        Translations::save('scholar_translations', 'user_id', $userId, $translations, ['title', 'bio']);
        Cache::flush('scholars.');
        Cache::flush('stats.');
        ActivityLog::record('admin.scholar.updated', 'user', $userId, ['rubriques' => $data['rubriques'] ?? [], 'requires_review' => !empty($data['requires_review'])], $adminId);
    }
}
