<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Utilisateur courant et permissions (§2.1).
 * Phase 0 : lecture de l'utilisateur en session et contrôle can().
 * Phase 1 : connexion, déconnexion, « se souvenir de moi », vérifications de compte.
 */
final class Auth
{
    /** @var array<string, mixed>|null|false false = pas encore chargé */
    private static array|null|false $user = false;

    /** @var array<string, list<string>> */
    private static array $permissions = [];

    /** @param array<string, list<string>> $permissions */
    public static function setPermissions(array $permissions): void
    {
        self::$permissions = $permissions;
    }

    /** @return array<string, mixed>|null */
    public static function user(): ?array
    {
        if (self::$user === false) {
            self::$user = null;
            $id = Session::get('user_id');
            if (is_int($id) && $id > 0) {
                try {
                    self::$user = Database::fetch(
                        'SELECT u.*, r.slug AS role FROM users u JOIN roles r ON r.id = u.role_id
                         WHERE u.id = ? AND u.deleted_at IS NULL AND u.status = ? LIMIT 1',
                        [$id, 'active'],
                    );
                } catch (\PDOException $e) {
                    Logger::exception($e);
                }
            }
        }

        return self::$user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        $user = self::user();

        return $user !== null ? (int) $user['id'] : null;
    }

    public static function role(): ?string
    {
        return self::user()['role'] ?? null;
    }

    public static function hasRole(string ...$roles): bool
    {
        return in_array(self::role(), $roles, true);
    }

    /** Le rôle donné (ou celui de l'utilisateur courant) possède-t-il la permission ? */
    public static function can(string $permission, ?string $role = null): bool
    {
        $role ??= self::role();
        if ($role === null) {
            return false;
        }
        $granted = self::$permissions[$role] ?? [];

        return in_array('*', $granted, true) || in_array($permission, $granted, true);
    }

    /** Hachage des mots de passe : Argon2id, repli bcrypt (§12.1). */
    public static function hashPassword(string $password): string
    {
        return defined('PASSWORD_ARGON2ID')
            ? password_hash($password, PASSWORD_ARGON2ID)
            : password_hash($password, PASSWORD_BCRYPT);
    }

    /** Ouvre la session de l'utilisateur (identifiant de session régénéré). */
    public static function login(int $userId): void
    {
        Session::regenerate();
        Session::set('user_id', $userId);
        Csrf::refresh();
        self::$user = false;
    }

    public static function logout(): void
    {
        Session::invalidate();
        Csrf::refresh();
        self::$user = null;
    }

    /** Force un utilisateur (tests). @param array<string, mixed>|null $user */
    public static function setUser(?array $user): void
    {
        self::$user = $user;
    }
}
