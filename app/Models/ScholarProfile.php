<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Lang;
use App\Core\Model;

/**
 * Professeurs (scholar_profiles + users + traductions + rubriques), §4.5.
 */
class ScholarProfile extends Model
{
    protected static string $table = 'scholar_profiles';

    protected static string $primaryKey = 'user_id';

    protected static array $fillable = ['user_id', 'languages', 'max_open_questions', 'can_self_assign', 'requires_review', 'is_public', 'is_available', 'photo_path', 'sort_order'];

    private const SELECT = 'SELECT u.id, u.full_name, u.display_name, u.locale AS user_locale, u.country, u.city, u.avatar_path,
            sp.languages, sp.is_public, sp.is_available, sp.photo_path, sp.sort_order, sp.requires_review, sp.can_self_assign,
            COALESCE(t.title, d.title) AS title, COALESCE(t.bio, d.bio) AS bio, (t.locale IS NULL AND d.locale IS NOT NULL) AS is_fallback,
            (SELECT COUNT(*) FROM questions q WHERE q.assigned_to = u.id AND q.status IN (\'answered\',\'published\',\'closed\') AND q.deleted_at IS NULL) AS answers_count
        FROM scholar_profiles sp
        JOIN users u ON u.id = sp.user_id AND u.deleted_at IS NULL AND u.status = \'active\'
        LEFT JOIN scholar_translations t ON t.user_id = sp.user_id AND t.locale = ?
        LEFT JOIN scholar_translations d ON d.user_id = sp.user_id AND d.locale = ?';

    /** Profils publics, dans l'ordre d'affichage. @return list<array<string, mixed>> */
    public static function publicList(?string $locale = null, ?int $limit = null): array
    {
        $locale ??= Lang::getLocale();
        $sql = self::SELECT . ' WHERE sp.is_public = 1 ORDER BY sp.sort_order, u.full_name';
        if ($limit !== null) {
            $sql .= ' LIMIT ' . max(1, $limit);
        }
        $rows = Database::fetchAll($sql, [$locale, Lang::getDefault()]);

        return self::attachRubriques($rows, $locale);
    }

    /** @return array<string, mixed>|null */
    public static function findPublic(int $userId, ?string $locale = null): ?array
    {
        $locale ??= Lang::getLocale();
        $row = Database::fetch(self::SELECT . ' WHERE sp.user_id = ? AND sp.is_public = 1 LIMIT 1', [$locale, Lang::getDefault(), $userId]);

        return $row === null ? null : self::attachRubriques([$row], $locale)[0];
    }

    /** Segment d'URL d'un professeur : « 12-cheick-moussa-kone ». @param array<string, mixed> $scholar */
    public static function slug(array $scholar): string
    {
        $slug = \App\Services\SlugService::make((string) ($scholar['full_name'] ?? ''), 60);

        return (int) $scholar['id'] . ($slug !== '' && $slug !== 'item' ? '-' . $slug : '');
    }

    /** Identifiant extrait d'un segment « 12-cheick-moussa-kone ». */
    public static function idFromSlug(string $slug): int
    {
        return (int) explode('-', $slug, 2)[0];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private static function attachRubriques(array $rows, string $locale): array
    {
        if ($rows === []) {
            return [];
        }
        $ids = array_map(static fn (array $r): int => (int) $r['id'], $rows);
        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $links = Database::fetchAll(
            "SELECT sr.user_id, r.id, r.code, r.icon, r.color, COALESCE(t.name, d.name) AS name, COALESCE(t.slug, d.slug) AS slug
             FROM scholar_rubriques sr
             JOIN rubriques r ON r.id = sr.rubrique_id AND r.is_active = 1
             LEFT JOIN rubrique_translations t ON t.rubrique_id = r.id AND t.locale = ?
             LEFT JOIN rubrique_translations d ON d.rubrique_id = r.id AND d.locale = ?
             WHERE sr.user_id IN ({$placeholders}) ORDER BY r.sort_order",
            array_merge([$locale, Lang::getDefault()], $ids),
        );
        $byUser = [];
        foreach ($links as $link) {
            $byUser[(int) $link['user_id']][] = $link;
        }
        foreach ($rows as &$row) {
            $row['rubriques'] = $byUser[(int) $row['id']] ?? [];
            $row['languages_list'] = array_values(array_filter(explode(',', (string) ($row['languages'] ?? ''))));
        }

        return $rows;
    }
}
