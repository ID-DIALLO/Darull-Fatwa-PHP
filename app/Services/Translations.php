<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Lang;

/**
 * Tables de traduction ({table}_translations : clé étrangère + locale + colonnes) : lecture par langue
 * et enregistrement « upsert » depuis les formulaires d'administration (champ[locale]).
 */
final class Translations
{
    /**
     * @return array<string, array<string, mixed>> locale => ligne
     */
    public static function all(string $table, string $foreignKey, int|string $id): array
    {
        $rows = [];
        foreach (Database::fetchAll('SELECT * FROM ' . Database::identifier($table) . ' WHERE ' . Database::identifier($foreignKey) . ' = ?', [$id]) as $row) {
            $rows[(string) $row['locale']] = $row;
        }

        return $rows;
    }

    /**
     * Enregistre les traductions d'un objet. $values : locale => [colonne => valeur]. Une langue dont tous
     * les champs obligatoires ($required) sont vides est supprimée (le repli sur la langue par défaut s'applique).
     *
     * @param array<string, array<string, mixed>> $values
     * @param list<string> $required
     */
    public static function save(string $table, string $foreignKey, int|string $id, array $values, array $required = []): void
    {
        $tableSql = Database::identifier($table);
        $fkSql = Database::identifier($foreignKey);
        foreach (Lang::locales() as $locale) {
            $data = $values[$locale] ?? [];
            $empty = true;
            foreach ($required === [] ? array_keys($data) : $required as $column) {
                if (trim((string) ($data[$column] ?? '')) !== '') {
                    $empty = false;
                    break;
                }
            }
            if ($empty) {
                Database::execute('DELETE FROM ' . $tableSql . ' WHERE ' . $fkSql . ' = ? AND locale = ?', [$id, $locale]);
                continue;
            }
            $columns = array_keys($data);
            $set = implode(', ', array_map(static fn (string $c): string => Database::identifier($c) . ' = VALUES(' . Database::identifier($c) . ')', $columns));
            $sql = 'INSERT INTO ' . $tableSql . ' (' . $fkSql . ', locale, ' . implode(', ', array_map([Database::class, 'identifier'], $columns)) . ')
                    VALUES (?, ?, ' . implode(', ', array_fill(0, count($columns), '?')) . ')
                    ON DUPLICATE KEY UPDATE ' . $set;
            Database::execute($sql, array_merge([$id, $locale], array_values($data)));
        }
    }

    /** Valeur textuelle normalisée d'un champ de formulaire (null si vide). */
    public static function text(mixed $value, int $max = 65535): ?string
    {
        $value = trim((string) (is_scalar($value) ? $value : ''));

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
