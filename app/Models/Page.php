<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Cache;
use App\Core\Database;
use App\Core\Lang;
use App\Core\Model;

/**
 * Pages CMS (À propos, CGU, confidentialité, FAQ, charte du forum) avec traductions et repli.
 */
class Page extends Model
{
    protected static string $table = 'pages';

    protected static array $fillable = ['code', 'template', 'show_in_footer', 'is_active', 'sort_order'];

    private const SELECT = 'SELECT p.*,
            COALESCE(t.title, d.title) AS title, COALESCE(t.slug, d.slug) AS slug,
            COALESCE(t.content, d.content) AS content,
            COALESCE(t.meta_title, d.meta_title) AS meta_title,
            COALESCE(t.meta_description, d.meta_description) AS meta_description,
            (t.locale IS NULL) AS is_fallback
        FROM pages p
        LEFT JOIN page_translations t ON t.page_id = p.id AND t.locale = ?
        LEFT JOIN page_translations d ON d.page_id = p.id AND d.locale = ?';

    /** @return array<string, mixed>|null */
    public static function findBySlug(string $slug, ?string $locale = null): ?array
    {
        $locale ??= Lang::getLocale();

        return Database::fetch(
            self::SELECT . ' WHERE p.is_active = 1 AND (t.slug = ? OR (t.slug IS NULL AND d.slug = ?)) LIMIT 1',
            [$locale, Lang::getDefault(), $slug, $slug],
        );
    }

    /** @return array<string, mixed>|null */
    public static function findByCode(string $code, ?string $locale = null): ?array
    {
        $locale ??= Lang::getLocale();

        return Database::fetch(self::SELECT . ' WHERE p.code = ? AND p.is_active = 1 LIMIT 1', [$locale, Lang::getDefault(), $code]);
    }

    /** Pages affichées dans le pied de page. @return list<array<string, mixed>> */
    public static function footer(?string $locale = null): array
    {
        $locale ??= Lang::getLocale();

        return Cache::remember('pages.footer.' . $locale, 600, static fn (): array => Database::fetchAll(
            'SELECT p.id, p.code, COALESCE(t.title, d.title) AS title, COALESCE(t.slug, d.slug) AS slug
             FROM pages p
             LEFT JOIN page_translations t ON t.page_id = p.id AND t.locale = ?
             LEFT JOIN page_translations d ON d.page_id = p.id AND d.locale = ?
             WHERE p.is_active = 1 AND p.show_in_footer = 1 ORDER BY p.sort_order, p.id',
            [$locale, Lang::getDefault()],
        ));
    }

    /** @return array<string, string> locale => slug */
    public static function slugs(int $id): array
    {
        $slugs = [];
        foreach (Database::fetchAll('SELECT locale, slug FROM page_translations WHERE page_id = ?', [$id]) as $row) {
            $slugs[(string) $row['locale']] = (string) $row['slug'];
        }

        return $slugs;
    }
}
