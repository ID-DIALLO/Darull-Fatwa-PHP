<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Lang;

/** Favoris des membres (§4.4) : articles et Q/R publiques. */
final class FavoriteService
{
    public const TYPES = ['article', 'qa'];

    /** Ajoute ou retire ; retourne true si le favori est désormais présent. */
    public static function toggle(int $userId, string $type, int $id): bool
    {
        if (!in_array($type, self::TYPES, true) || !self::exists($type, $id)) {
            throw new \InvalidArgumentException('invalid_target');
        }
        if (self::has($userId, $type, $id)) {
            Database::execute('DELETE FROM favorites WHERE user_id = ? AND favoritable_type = ? AND favoritable_id = ?', [$userId, $type, $id]);

            return false;
        }
        Database::execute('INSERT IGNORE INTO favorites (user_id, favoritable_type, favoritable_id) VALUES (?, ?, ?)', [$userId, $type, $id]);

        return true;
    }

    public static function has(?int $userId, string $type, int $id): bool
    {
        if ($userId === null) {
            return false;
        }

        return Database::fetch('SELECT 1 FROM favorites WHERE user_id = ? AND favoritable_type = ? AND favoritable_id = ?', [$userId, $type, $id]) !== null;
    }

    public static function count(string $type, int $id): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM favorites WHERE favoritable_type = ? AND favoritable_id = ?', [$type, $id]);
    }

    private static function exists(string $type, int $id): bool
    {
        return $type === 'article'
            ? Database::fetch("SELECT 1 FROM articles WHERE id = ? AND status = 'published' AND deleted_at IS NULL", [$id]) !== null
            : Database::fetch("SELECT 1 FROM qa_publications WHERE id = ? AND status = 'published'", [$id]) !== null;
    }

    /**
     * Favoris d'un membre avec titre et adresse dans la langue courante (repli : langue par défaut).
     *
     * @return list<array{type: string, id: int, title: string, url: string, rubrique: string|null, created_at: string}>
     */
    public static function listFor(int $userId, ?string $locale = null): array
    {
        $locale ??= Lang::getLocale();
        $items = [];
        foreach (Database::fetchAll('SELECT * FROM favorites WHERE user_id = ? ORDER BY created_at DESC LIMIT 200', [$userId]) as $fav) {
            $id = (int) $fav['favoritable_id'];
            if ($fav['favoritable_type'] === 'article') {
                $row = Database::fetch(
                    'SELECT COALESCE(t.title, d.title) AS title, COALESCE(t.slug, d.slug) AS slug, COALESCE(t.locale, d.locale) AS locale, COALESCE(rt.name, rd.name) AS rubrique
                     FROM articles a LEFT JOIN article_translations t ON t.article_id = a.id AND t.locale = ? LEFT JOIN article_translations d ON d.article_id = a.id AND d.locale = ?
                     LEFT JOIN rubriques r ON r.id = a.rubrique_id LEFT JOIN rubrique_translations rt ON rt.rubrique_id = r.id AND rt.locale = ? LEFT JOIN rubrique_translations rd ON rd.rubrique_id = r.id AND rd.locale = ?
                     WHERE a.id = ? AND a.status = \'published\' AND a.deleted_at IS NULL',
                    [$locale, Lang::getDefault(), $locale, Lang::getDefault(), $id],
                );
                $route = 'blog.show';
            } else {
                $row = Database::fetch(
                    'SELECT COALESCE(t.title, d.title) AS title, COALESCE(t.slug, d.slug) AS slug, COALESCE(t.locale, d.locale) AS locale, COALESCE(rt.name, rd.name) AS rubrique
                     FROM qa_publications p LEFT JOIN qa_publication_translations t ON t.publication_id = p.id AND t.locale = ? LEFT JOIN qa_publication_translations d ON d.publication_id = p.id AND d.locale = ?
                     JOIN rubriques r ON r.id = p.rubrique_id LEFT JOIN rubrique_translations rt ON rt.rubrique_id = r.id AND rt.locale = ? LEFT JOIN rubrique_translations rd ON rd.rubrique_id = r.id AND rd.locale = ?
                     WHERE p.id = ? AND p.status = \'published\'',
                    [$locale, Lang::getDefault(), $locale, Lang::getDefault(), $id],
                );
                $route = 'qa.show';
            }
            if ($row === null || $row['title'] === null || !route_exists($route)) {
                continue;
            }
            $items[] = ['type' => (string) $fav['favoritable_type'], 'id' => $id, 'title' => (string) $row['title'], 'url' => route($route, ['slug' => $row['slug']], (string) $row['locale']), 'rubrique' => $row['rubrique'], 'created_at' => (string) $fav['created_at']];
        }

        return $items;
    }
}
