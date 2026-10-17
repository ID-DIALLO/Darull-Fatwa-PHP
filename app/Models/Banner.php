<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Cache;
use App\Core\Database;
use App\Core\Lang;
use App\Core\Model;

/**
 * Bannières du Hero (§6, Annexe D.1). Les liens des boutons sont stockés sous la forme
 * « route:nom.de.route » (résolus dans la langue courante) ou en URL complète.
 */
class Banner extends Model
{
    protected static string $table = 'banners';

    protected static array $fillable = ['image_desktop', 'image_mobile', 'link_url', 'link2_url', 'sort_order', 'is_active', 'starts_at', 'ends_at'];

    /** Bannières actives et dans leur période, traduites (repli sur la langue par défaut). @return list<array<string, mixed>> */
    public static function active(?string $locale = null): array
    {
        $locale ??= Lang::getLocale();

        return Cache::remember('banners.active.' . $locale, 300, static fn (): array => Database::fetchAll(
            'SELECT b.*, COALESCE(t.title, d.title) AS title, COALESCE(t.subtitle, d.subtitle) AS subtitle,
                    COALESCE(t.button_label, d.button_label) AS button_label, COALESCE(t.button2_label, d.button2_label) AS button2_label,
                    COALESCE(t.image_alt, d.image_alt) AS image_alt
             FROM banners b
             LEFT JOIN banner_translations t ON t.banner_id = b.id AND t.locale = ?
             LEFT JOIN banner_translations d ON d.banner_id = b.id AND d.locale = ?
             WHERE b.is_active = 1
               AND (b.starts_at IS NULL OR b.starts_at <= UTC_TIMESTAMP())
               AND (b.ends_at IS NULL OR b.ends_at >= UTC_TIMESTAMP())
               AND COALESCE(t.title, d.title) IS NOT NULL
             ORDER BY b.sort_order, b.id',
            [$locale, Lang::getDefault()],
        ));
    }

    /** Le lien pointe-t-il vers le formulaire « Poser une question » ? */
    public static function isAskLink(?string $link): bool
    {
        return in_array($link, ['route:questions.create', 'route:questions.store'], true);
    }

    /** Résout « route:questions.create » en chemin ; retourne null si la route n'existe pas encore. */
    public static function resolveLink(?string $link): ?string
    {
        if ($link === null || $link === '') {
            return null;
        }
        if (str_starts_with($link, 'route:')) {
            $name = substr($link, 6);
            // Le bouton « Poser une question » mène l'équipe (professeur, administration) vers son espace
            if (self::isAskLink($link)) {
                return ask_url() ?? space_url();
            }

            return route_exists($name) ? route($name) : null;
        }

        return $link;
    }
}
