<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Cache;
use App\Core\Database;

/**
 * Chiffres clés de l'accueil (§6, section 11) : questions répondues, membres, articles, professeurs.
 * Valeurs manuelles possibles via les paramètres stats_* (sinon calcul automatique, en cache 10 min).
 */
final class SiteStats
{
    /** @return array{answered: int, members: int, articles: int, scholars: int} */
    public static function home(): array
    {
        return Cache::remember('stats.home', 600, static function (): array {
            $auto = [
                'answered' => (int) Database::value("SELECT COUNT(*) FROM questions WHERE status IN ('answered','published','closed') AND deleted_at IS NULL"),
                'members'  => (int) Database::value("SELECT COUNT(*) FROM users WHERE deleted_at IS NULL AND status = 'active'"),
                'articles' => (int) Database::value("SELECT COUNT(*) FROM articles WHERE status = 'published' AND deleted_at IS NULL"),
                'scholars' => (int) Database::value("SELECT COUNT(*) FROM scholar_profiles sp JOIN users u ON u.id = sp.user_id WHERE sp.is_public = 1 AND u.deleted_at IS NULL AND u.status = 'active'"),
            ];
            foreach ($auto as $key => $value) {
                $manual = \App\Models\Setting::get('stats_' . $key);
                if ($manual !== null && $manual !== '' && is_numeric($manual)) {
                    $auto[$key] = (int) $manual;
                }
            }

            return $auto;
        });
    }
}
