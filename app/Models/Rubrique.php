<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Cache;
use App\Core\Database;
use App\Core\Lang;
use App\Core\Model;

/**
 * Rubriques (thématiques, §3) avec leurs traductions. Chaque ligne retournée contient :
 * name, slug, short_description, description, meta_title, meta_description dans la langue demandée
 * (repli sur la langue par défaut, « is_fallback » = 1) et le nombre de Q/R publiées.
 */
class Rubrique extends Model
{
    protected static string $table = 'rubriques';

    protected static array $fillable = ['parent_id', 'code', 'icon', 'color', 'image_path', 'sort_order', 'is_active'];

    private const SELECT = 'SELECT r.*,
            COALESCE(t.name, d.name) AS name, COALESCE(t.slug, d.slug) AS slug,
            COALESCE(t.short_description, d.short_description) AS short_description,
            COALESCE(t.description, d.description) AS description,
            COALESCE(t.meta_title, d.meta_title) AS meta_title,
            COALESCE(t.meta_description, d.meta_description) AS meta_description,
            (t.locale IS NULL) AS is_fallback,
            (SELECT COUNT(*) FROM qa_publications p WHERE p.rubrique_id = r.id AND p.status = \'published\') AS qa_count
        FROM rubriques r
        LEFT JOIN rubrique_translations t ON t.rubrique_id = r.id AND t.locale = ?
        LEFT JOIN rubrique_translations d ON d.rubrique_id = r.id AND d.locale = ?';

    /** Rubriques actives de premier niveau, triées. @return list<array<string, mixed>> */
    public static function allActive(?string $locale = null): array
    {
        $locale ??= Lang::getLocale();

        return Cache::remember('rubriques.active.' . $locale, 600, static fn (): array => Database::fetchAll(
            self::SELECT . ' WHERE r.is_active = 1 AND r.parent_id IS NULL ORDER BY r.sort_order, r.id',
            [$locale, Lang::getDefault()],
        ));
    }

    /** Sous-rubriques actives d'une rubrique. @return list<array<string, mixed>> */
    public static function children(int $parentId, ?string $locale = null): array
    {
        $locale ??= Lang::getLocale();

        return Database::fetchAll(
            self::SELECT . ' WHERE r.is_active = 1 AND r.parent_id = ? ORDER BY r.sort_order, r.id',
            [$locale, Lang::getDefault(), $parentId],
        );
    }

    /** @return array<string, mixed>|null */
    public static function findBySlug(string $slug, ?string $locale = null): ?array
    {
        $locale ??= Lang::getLocale();

        return Database::fetch(
            self::SELECT . ' WHERE r.is_active = 1 AND (t.slug = ? OR (t.slug IS NULL AND d.slug = ?)) LIMIT 1',
            [$locale, Lang::getDefault(), $slug, $slug],
        );
    }

    /** @return array<string, mixed>|null */
    public static function findTranslated(int $id, ?string $locale = null): ?array
    {
        $locale ??= Lang::getLocale();

        return Database::fetch(self::SELECT . ' WHERE r.id = ? LIMIT 1', [$locale, Lang::getDefault(), $id]);
    }

    /** Slugs de la rubrique dans chaque langue (sélecteur de langue, hreflang). @return array<string, string> */
    public static function slugs(int $id): array
    {
        $slugs = [];
        foreach (Database::fetchAll('SELECT locale, slug FROM rubrique_translations WHERE rubrique_id = ?', [$id]) as $row) {
            $slugs[(string) $row['locale']] = (string) $row['slug'];
        }

        return $slugs;
    }

    /** Rubrique par code stable (tawhid, islam, coran…). @return array<string, mixed>|null */
    public static function findByCode(string $code, ?string $locale = null): ?array
    {
        $locale ??= Lang::getLocale();

        return Database::fetch(self::SELECT . ' WHERE r.code = ? LIMIT 1', [$locale, Lang::getDefault(), $code]);
    }
}
