<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Model;
use App\Services\Crypto;

/**
 * Paramètres du site (table settings, §10.4). Les paramètres secrets (is_secret = 1) sont chiffrés
 * avec APP_KEY et ne sont jamais chargés dans le cache général ni affichés en clair dans l'administration.
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

    /** @return array<string, string|null> Paramètres non secrets */
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
            'INSERT INTO settings (setting_key, setting_value, setting_group, is_secret) VALUES (?, ?, ?, 0)
             ON DUPLICATE KEY UPDATE setting_value = ?, is_secret = 0',
            [$key, $value, $group, $value],
        );
        self::$cache = null;
    }

    /** Valeur déchiffrée d'un paramètre secret (null si absent, vide ou illisible). */
    public static function secret(string $key): ?string
    {
        try {
            $row = Database::fetch('SELECT setting_value FROM settings WHERE setting_key = ? AND is_secret = 1 LIMIT 1', [$key]);
        } catch (\PDOException) {
            return null;
        }
        if ($row === null || $row['setting_value'] === null || $row['setting_value'] === '') {
            return null;
        }
        $plain = Crypto::decrypt((string) $row['setting_value']);

        return $plain === '' ? null : $plain;
    }

    /** Un secret est-il renseigné ? (sans le révéler) */
    public static function hasSecret(string $key): bool
    {
        return self::secret($key) !== null;
    }

    /** Enregistre un paramètre secret chiffré ; une valeur vide efface le secret. */
    public static function setSecret(string $key, ?string $value, string $group = 'general'): void
    {
        $stored = $value === null || $value === '' ? null : Crypto::encrypt($value);
        Database::execute(
            'INSERT INTO settings (setting_key, setting_value, setting_group, is_secret) VALUES (?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE setting_value = ?, is_secret = 1',
            [$key, $stored, $group, $stored],
        );
        self::$cache = null;
    }

    public static function flush(): void
    {
        self::$cache = null;
    }
}
