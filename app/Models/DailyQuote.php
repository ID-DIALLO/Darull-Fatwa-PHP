<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Cache;
use App\Core\Database;
use App\Core\Lang;
use App\Core\Model;

/**
 * Verset / hadith du jour (§6) : citation programmée pour la date, sinon rotation automatique.
 */
class DailyQuote extends Model
{
    protected static string $table = 'daily_quotes';

    protected static array $fillable = ['type', 'arabic_text', 'reference', 'display_date', 'is_active'];

    /** @return array<string, mixed>|null */
    public static function today(?string $locale = null): ?array
    {
        $locale ??= Lang::getLocale();
        $date = date('Y-m-d');

        return Cache::remember('daily_quote.' . $date . '.' . $locale, 3600, static function () use ($locale, $date): ?array {
            $select = 'SELECT q.*, COALESCE(t.translation, d.translation) AS translation
                       FROM daily_quotes q
                       LEFT JOIN daily_quote_translations t ON t.quote_id = q.id AND t.locale = ?
                       LEFT JOIN daily_quote_translations d ON d.quote_id = q.id AND d.locale = ?
                       WHERE q.is_active = 1';
            $planned = Database::fetch($select . ' AND q.display_date = ? LIMIT 1', [$locale, Lang::getDefault(), $date]);
            if ($planned !== null) {
                return $planned;
            }
            $rows = Database::fetchAll($select . ' AND q.display_date IS NULL ORDER BY q.id', [$locale, Lang::getDefault()]);
            if ($rows === []) {
                return null;
            }

            return $rows[(int) date('z') % count($rows)];
        });
    }
}
