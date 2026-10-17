<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Cache;
use App\Core\Database;
use App\Core\Model;

/**
 * Sections de la page d'accueil (§6) : activation et ordre gérés dans l'administration.
 */
class HomeSection extends Model
{
    protected static string $table = 'home_sections';

    protected static string $primaryKey = 'code';

    protected static array $fillable = ['code', 'sort_order', 'is_active', 'settings'];

    /** Codes des sections actives, dans l'ordre. @return list<string> */
    public static function activeCodes(): array
    {
        return Cache::remember('home.sections', 600, static fn (): array => array_map(
            static fn (array $r): string => (string) $r['code'],
            Database::fetchAll('SELECT code FROM home_sections WHERE is_active = 1 ORDER BY sort_order, code'),
        ));
    }
}
