<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Modèle de base : find, where, create, update, softDelete, paginate.
 * Les lignes sont retournées sous forme de tableaux associatifs. Requêtes préparées uniquement ;
 * les noms de colonnes passent par Database::identifier() (liste blanche de caractères).
 */
abstract class Model
{
    protected static string $table = '';

    protected static string $primaryKey = 'id';

    /** @var list<string> Colonnes modifiables par create()/update() */
    protected static array $fillable = [];

    /** La table possède-t-elle une colonne deleted_at ? */
    protected static bool $softDeletes = false;

    public static function table(): string
    {
        return Database::identifier(static::$table);
    }

    /** @return array<string, mixed>|null */
    public static function find(int|string $id, bool $withTrashed = false): ?array
    {
        $sql = 'SELECT * FROM ' . static::table() . ' WHERE ' . Database::identifier(static::$primaryKey) . ' = ?';
        if (static::$softDeletes && !$withTrashed) {
            $sql .= ' AND `deleted_at` IS NULL';
        }

        return Database::fetch($sql . ' LIMIT 1', [$id]);
    }

    /**
     * @param array<string, mixed> $conditions colonne => valeur (null → IS NULL, tableau → IN)
     * @param array<string, string> $orderBy colonne => ASC|DESC
     * @return list<array<string, mixed>>
     */
    public static function where(array $conditions = [], array $orderBy = [], ?int $limit = null, int $offset = 0): array
    {
        [$where, $bindings] = static::buildWhere($conditions);
        $sql = 'SELECT * FROM ' . static::table() . $where . static::buildOrder($orderBy);
        if ($limit !== null) {
            $sql .= ' LIMIT ? OFFSET ?';
            $bindings[] = $limit;
            $bindings[] = $offset;
        }

        return Database::fetchAll($sql, $bindings);
    }

    /**
     * @param array<string, mixed> $conditions
     * @return array<string, mixed>|null
     */
    public static function firstWhere(array $conditions, array $orderBy = []): ?array
    {
        return static::where($conditions, $orderBy, 1)[0] ?? null;
    }

    /** @param array<string, mixed> $conditions */
    public static function count(array $conditions = []): int
    {
        [$where, $bindings] = static::buildWhere($conditions);

        return (int) Database::value('SELECT COUNT(*) FROM ' . static::table() . $where, $bindings);
    }

    /**
     * Insère une ligne (colonnes limitées à $fillable) et retourne son identifiant.
     *
     * @param array<string, mixed> $data
     */
    public static function create(array $data): int
    {
        $data = static::onlyFillable($data);
        if ($data === []) {
            throw new \InvalidArgumentException('Aucune colonne autorisée à insérer.');
        }
        $columns = implode(', ', array_map([Database::class, 'identifier'], array_keys($data)));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        Database::execute('INSERT INTO ' . static::table() . " ({$columns}) VALUES ({$placeholders})", array_values($data));

        return Database::lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public static function update(int|string $id, array $data): int
    {
        $data = static::onlyFillable($data);
        if ($data === []) {
            return 0;
        }
        $sets = implode(', ', array_map(static fn (string $c): string => Database::identifier($c) . ' = ?', array_keys($data)));
        $bindings = array_values($data);
        $bindings[] = $id;

        return Database::execute('UPDATE ' . static::table() . " SET {$sets} WHERE " . Database::identifier(static::$primaryKey) . ' = ?', $bindings);
    }

    /** Suppression logique (deleted_at) si la table le permet, sinon suppression réelle. */
    public static function softDelete(int|string $id): int
    {
        if (!static::$softDeletes) {
            return static::destroy($id);
        }

        return Database::execute('UPDATE ' . static::table() . ' SET `deleted_at` = UTC_TIMESTAMP() WHERE ' . Database::identifier(static::$primaryKey) . ' = ?', [$id]);
    }

    public static function destroy(int|string $id): int
    {
        return Database::execute('DELETE FROM ' . static::table() . ' WHERE ' . Database::identifier(static::$primaryKey) . ' = ?', [$id]);
    }

    /**
     * Pagination simple.
     *
     * @param array<string, mixed> $conditions
     * @param array<string, string> $orderBy
     * @return array{data: list<array<string, mixed>>, total: int, page: int, per_page: int, last_page: int}
     */
    public static function paginate(int $page = 1, int $perPage = 20, array $conditions = [], array $orderBy = []): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $total = static::count($conditions);

        return [
            'data'      => static::where($conditions, $orderBy, $perPage, ($page - 1) * $perPage),
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /**
     * @param array<string, mixed> $conditions
     * @return array{0: string, 1: list<mixed>}
     */
    protected static function buildWhere(array $conditions): array
    {
        $clauses = [];
        $bindings = [];
        foreach ($conditions as $column => $value) {
            $col = Database::identifier($column);
            if ($value === null) {
                $clauses[] = "{$col} IS NULL";
            } elseif (is_array($value)) {
                if ($value === []) {
                    $clauses[] = '1 = 0';
                    continue;
                }
                $clauses[] = "{$col} IN (" . implode(', ', array_fill(0, count($value), '?')) . ')';
                array_push($bindings, ...array_values($value));
            } else {
                $clauses[] = "{$col} = ?";
                $bindings[] = $value;
            }
        }
        if (static::$softDeletes && !array_key_exists('deleted_at', $conditions)) {
            $clauses[] = '`deleted_at` IS NULL';
        }

        return [$clauses === [] ? '' : ' WHERE ' . implode(' AND ', $clauses), $bindings];
    }

    /** @param array<string, string> $orderBy */
    protected static function buildOrder(array $orderBy): string
    {
        if ($orderBy === []) {
            return '';
        }
        $parts = [];
        foreach ($orderBy as $column => $direction) {
            $parts[] = Database::identifier($column) . (strtoupper($direction) === 'DESC' ? ' DESC' : ' ASC');
        }

        return ' ORDER BY ' . implode(', ', $parts);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected static function onlyFillable(array $data): array
    {
        return array_intersect_key($data, array_flip(static::$fillable));
    }
}
