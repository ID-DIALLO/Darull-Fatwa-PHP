<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Cache applicatif fichier (storage/cache, §13.2) : menus, rubriques, statistiques de l'accueil, paramètres.
 * Invalidé explicitement par l'administration (forget/flush). Désactivé pendant les tests.
 */
final class Cache
{
    private static string $directory = '';

    private static bool $enabled = true;

    /** @var array<string, mixed> Valeurs déjà lues pendant la requête */
    private static array $memory = [];

    public static function setDirectory(string $directory, bool $enabled = true): void
    {
        self::$directory = rtrim($directory, '/');
        self::$enabled = $enabled;
        self::$memory = [];
    }

    /**
     * Retourne la valeur en cache ou calcule, stocke et retourne le résultat de $callback.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public static function remember(string $key, int $ttlSeconds, callable $callback): mixed
    {
        if (!self::$enabled || self::$directory === '') {
            return $callback();
        }
        if (array_key_exists($key, self::$memory)) {
            return self::$memory[$key];
        }
        $file = self::file($key);
        if (is_file($file)) {
            $raw = @file_get_contents($file);
            $entry = $raw === false ? null : @unserialize($raw, ['allowed_classes' => false]);
            if (is_array($entry) && isset($entry['expires'], $entry['value']) && $entry['expires'] > time()) {
                return self::$memory[$key] = $entry['value'];
            }
        }
        $value = $callback();
        self::put($key, $value, $ttlSeconds);

        return $value;
    }

    public static function put(string $key, mixed $value, int $ttlSeconds): void
    {
        if (!self::$enabled || self::$directory === '') {
            return;
        }
        if (!is_dir(self::$directory)) {
            @mkdir(self::$directory, 0775, true);
        }
        self::$memory[$key] = $value;
        @file_put_contents(self::file($key), serialize(['expires' => time() + $ttlSeconds, 'value' => $value]), LOCK_EX);
    }

    public static function forget(string $key): void
    {
        unset(self::$memory[$key]);
        if (self::$directory !== '') {
            @unlink(self::file($key));
        }
    }

    /** Vide tout le cache (ou seulement les clés commençant par $prefix). */
    public static function flush(string $prefix = ''): void
    {
        foreach (self::$memory as $key => $_) {
            if ($prefix === '' || str_starts_with($key, $prefix)) {
                unset(self::$memory[$key]);
            }
        }
        if (self::$directory === '') {
            return;
        }
        foreach (glob(self::$directory . '/*.cache') ?: [] as $file) {
            $raw = @file_get_contents($file . '.key');
            if ($prefix === '' || ($raw !== false && str_starts_with($raw, $prefix))) {
                @unlink($file);
                @unlink($file . '.key');
            }
        }
    }

    private static function file(string $key): string
    {
        $file = self::$directory . '/' . hash('sha256', $key) . '.cache';
        if (!is_file($file . '.key')) {
            @file_put_contents($file . '.key', $key);
        }

        return $file;
    }
}
