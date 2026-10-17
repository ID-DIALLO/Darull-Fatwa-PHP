<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

/**
 * Membres, professeurs, modérateurs et administrateurs (table users).
 */
class User extends Model
{
    protected static string $table = 'users';

    protected static bool $softDeletes = true;

    protected static array $fillable = [
        'uuid', 'role_id', 'full_name', 'display_name', 'email', 'phone', 'whatsapp', 'password_hash',
        'locale', 'gender', 'country', 'city', 'avatar_path', 'preferred_channel', 'notify_email',
        'notify_whatsapp', 'email_verified_at', 'phone_verified_at', 'forum_rules_accepted_at',
        'status', 'last_login_at', 'last_login_ip', 'deleted_at',
    ];

    /** Rôles : identifiant => slug (seed 01_roles.sql). */
    public const ROLES = [1 => 'member', 2 => 'scholar', 3 => 'moderator', 4 => 'admin', 5 => 'superadmin'];

    /** @return array<string, mixed>|null Utilisateur avec le slug de son rôle */
    public static function findWithRole(int $id, bool $withTrashed = false): ?array
    {
        return Database::fetch(
            'SELECT u.*, r.slug AS role FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?' . ($withTrashed ? '' : ' AND u.deleted_at IS NULL') . ' LIMIT 1',
            [$id],
        );
    }

    /** Recherche par e-mail (insensible à la casse) ou par téléphone E.164. @return array<string, mixed>|null */
    public static function findByIdentifier(string $identifier): ?array
    {
        $column = str_contains($identifier, '@') ? 'email' : 'phone';
        $value = $column === 'email' ? mb_strtolower(trim($identifier)) : $identifier;

        return Database::fetch(
            "SELECT u.*, r.slug AS role FROM users u JOIN roles r ON r.id = u.role_id WHERE u.{$column} = ? AND u.deleted_at IS NULL LIMIT 1",
            [$value],
        );
    }

    /** Nom affiché publiquement : pseudonyme s'il existe, sinon nom complet. @param array<string, mixed> $user */
    public static function publicName(array $user): string
    {
        $display = trim((string) ($user['display_name'] ?? ''));

        return $display !== '' ? $display : (string) ($user['full_name'] ?? '');
    }

    public static function roleId(string $slug): int
    {
        $id = array_search($slug, self::ROLES, true);

        return $id === false ? 1 : (int) $id;
    }
}
