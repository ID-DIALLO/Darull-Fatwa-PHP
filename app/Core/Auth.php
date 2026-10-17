<?php

declare(strict_types=1);

namespace App\Core;

use App\Services\TokenService;

/**
 * Utilisateur courant, connexion, déconnexion, « se souvenir de moi » et permissions (§2.1, §4.1, §12.1).
 */
final class Auth
{
    public const REMEMBER_COOKIE = 'df_remember';
    public const REMEMBER_DAYS = 30;

    /** @var array<string, mixed>|null|false false = pas encore chargé */
    private static array|null|false $user = false;

    /** @var array<string, list<string>> */
    private static array $permissions = [];

    /** @var array{value: string, expires: int}|null Cookie « se souvenir de moi » à poser sur la réponse */
    private static ?array $pendingCookie = null;

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
                    $user = Database::fetch(
                        'SELECT u.*, r.slug AS role FROM users u JOIN roles r ON r.id = u.role_id
                         WHERE u.id = ? AND u.deleted_at IS NULL AND u.status = ? LIMIT 1',
                        [$id, 'active'],
                    );
                    // La session n'est valable que pour le mot de passe en vigueur : en changer déconnecte les autres appareils.
                    if ($user !== null && hash_equals(self::sessionHash((string) $user['password_hash']), (string) Session::get('auth_hash', ''))) {
                        self::$user = $user;
                    } else {
                        Session::forget('user_id');
                        Session::forget('auth_hash');
                    }
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

    /**
     * Vérifie un mot de passe et le re-hache si l'algorithme a évolué.
     *
     * @param array<string, mixed> $user
     */
    public static function verifyPassword(array $user, string $password): bool
    {
        $hash = (string) ($user['password_hash'] ?? '');
        if ($hash === '' || !password_verify($password, $hash)) {
            return false;
        }
        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
        if (password_needs_rehash($hash, $algo)) {
            Database::execute('UPDATE users SET password_hash = ? WHERE id = ?', [self::hashPassword($password), (int) $user['id']]);
        }

        return true;
    }

    /** Ouvre la session de l'utilisateur (identifiant de session régénéré). */
    public static function login(int $userId, bool $remember = false): void
    {
        $hash = (string) (Database::value('SELECT password_hash FROM users WHERE id = ?', [$userId]) ?? '');
        Session::regenerate();
        Session::set('user_id', $userId);
        Session::set('auth_hash', self::sessionHash($hash));
        Csrf::refresh();
        self::$user = false;

        if ($remember) {
            $token = TokenService::create($userId, TokenService::REMEMBER, self::REMEMBER_DAYS * 86400, false);
            self::$pendingCookie = ['value' => $token, 'expires' => time() + self::REMEMBER_DAYS * 86400];
        }
    }

    public static function logout(?Request $request = null): void
    {
        $cookie = $request?->cookie(self::REMEMBER_COOKIE);
        if (is_string($cookie) && $cookie !== '') {
            TokenService::delete($cookie);
        }
        Session::invalidate();
        Csrf::refresh();
        self::$user = null;
        self::$pendingCookie = ['value' => '', 'expires' => time() - 86400];
    }

    /** Après un changement de mot de passe : la session courante reste valable, les autres appareils sont déconnectés. */
    public static function refreshSessionHash(int $userId): void
    {
        $hash = (string) (Database::value('SELECT password_hash FROM users WHERE id = ?', [$userId]) ?? '');
        Session::set('auth_hash', self::sessionHash($hash));
        TokenService::deleteAll($userId, TokenService::REMEMBER);
        self::$user = false;
    }

    /**
     * Reconnexion automatique depuis le cookie « se souvenir de moi » (jeton renouvelé à chaque usage).
     */
    public static function restoreFromCookie(Request $request): void
    {
        if (Session::get('user_id') !== null) {
            return;
        }
        $token = $request->cookie(self::REMEMBER_COOKIE);
        if (!is_string($token) || $token === '') {
            return;
        }
        try {
            $found = TokenService::find($token, TokenService::REMEMBER);
            if ($found === null) {
                self::$pendingCookie = ['value' => '', 'expires' => time() - 86400];

                return;
            }
            TokenService::delete($token);
            self::login($found['user_id'], true);
            if (self::user() === null) {
                self::$pendingCookie = ['value' => '', 'expires' => time() - 86400];
            }
        } catch (\PDOException $e) {
            Logger::exception($e);
        }
    }

    /** Pose (ou supprime) le cookie « se souvenir de moi » sur la réponse. */
    public static function applyCookies(Response $response, bool $secure): void
    {
        if (self::$pendingCookie === null) {
            return;
        }
        $response->cookie(self::REMEMBER_COOKIE, self::$pendingCookie['value'], [
            'expires'  => self::$pendingCookie['expires'],
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        self::$pendingCookie = null;
    }

    /** Force un utilisateur (tests). @param array<string, mixed>|null $user */
    public static function setUser(?array $user): void
    {
        self::$user = $user;
    }

    /** Oublie l'utilisateur chargé (tests, changement de requête). */
    public static function reset(): void
    {
        self::$user = false;
        self::$pendingCookie = null;
    }

    private static function sessionHash(string $passwordHash): string
    {
        return substr(hash('sha256', $passwordHash), 0, 32);
    }
}
