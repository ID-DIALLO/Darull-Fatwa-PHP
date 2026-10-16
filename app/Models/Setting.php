<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Model;

/**
 * Paramètres du site (table settings), chargés une fois par requête.
 * Si la base n'est pas encore installée, les valeurs par défaut sont utilisées.
 */
class Setting extends Model
{
    protected static string $table = 'settings';

    protected static string $primaryKey = 'setting_key';

    protected static array $fillable = ['setting_key', 'setting_value', 'setting_group', 'is_secret'];

    /** @var array<string, string|null>|null */
    private static ?array $cache = null;

    public static function get(string $key, ?string $default = null): ?string
    {
        $all = self::all();

        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    /** @return array<string, string|null> */
    public static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            try {
                foreach (Database::fetchAll('SELECT setting_key, setting_value FROM settings WHERE is_secret = 0') as $row) {
                    self::$cache[(string) $row['setting_key']] = $row['setting_value'] !== null ? (string) $row['setting_value'] : null;
                }
            } catch (\PDOException $e) {
                // Base absente ou non installée : on continue avec les valeurs par défaut.
                Logger::debug('Paramètres indisponibles : ' . $e->getMessage());
            }
        }

        return self::$cache;
    }

    public static function set(string $key, ?string $value, string $group = 'general'): void
    {
        Database::execute(
            'INSERT INTO settings (setting_key, setting_value, setting_group) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = ?',
            [$key, $value, $group, $value],
        );
        self::$cache = null;
    }

    public static function flush(): void
    {
        self::$cache = null;
    }
}
