<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Slugs d'URL (§7.1) : caractères latins translittérés (é → e), lettres arabes conservées
 * (slug arabe autorisé, encodé en UTF-8 dans l'adresse), unicité vérifiée en base.
 */
final class SlugService
{
    private const LATIN = [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'ae', 'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o', 'œ' => 'oe',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ý' => 'y', 'ÿ' => 'y', 'ß' => 'ss', 'ɛ' => 'e', 'ɔ' => 'o', 'ŋ' => 'ng', 'ɲ' => 'ny',
        'ʼ' => '', '’' => '', 'ʻ' => '',
    ];

    public static function make(string $text, int $maxLength = 120): string
    {
        $text = mb_strtolower(trim($text));
        $text = strtr($text, self::LATIN);
        // Lettres (latines et arabes) et chiffres conservés ; tout le reste devient un tiret
        $text = preg_replace('/[^\p{L}\p{N}]+/u', '-', $text) ?? '';
        // Diacritiques arabes (harakat) retirés pour des adresses stables
        $text = preg_replace('/[\x{064B}-\x{0652}\x{0670}\x{0640}]/u', '', $text) ?? '';
        $text = trim(preg_replace('/-+/', '-', $text) ?? '', '-');
        if (mb_strlen($text) > $maxLength) {
            $text = rtrim(mb_substr($text, 0, $maxLength), '-');
        }

        return $text !== '' ? $text : 'item';
    }

    /**
     * Slug unique dans une table de traductions (colonne slug + locale), suffixé -2, -3… si besoin.
     *
     * @param array<string, mixed> $ignore colonne => valeur à ignorer (la ligne en cours de modification)
     */
    public static function unique(string $text, string $table, string $locale, array $ignore = [], int $maxLength = 120): string
    {
        $base = self::make($text, $maxLength - 4);
        $slug = $base;
        $n = 1;
        while (self::exists($table, $locale, $slug, $ignore)) {
            $n++;
            $slug = $base . '-' . $n;
        }

        return $slug;
    }

    /** @param array<string, mixed> $ignore */
    private static function exists(string $table, string $locale, string $slug, array $ignore): bool
    {
        $sql = 'SELECT 1 FROM ' . Database::identifier($table) . ' WHERE slug = ? AND locale = ?';
        $bindings = [$slug, $locale];
        foreach ($ignore as $column => $value) {
            $sql .= ' AND ' . Database::identifier($column) . ' <> ?';
            $bindings[] = $value;
        }

        return Database::fetch($sql . ' LIMIT 1', $bindings) !== null;
    }
}
