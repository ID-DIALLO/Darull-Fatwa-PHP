<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Models\User;

/**
 * Invitations des comptes créés par l'administration (§2.1, §4.6.2) : professeurs, modérateurs, administrateurs,
 * membres. L'administrateur transmet un lien d'activation ET un code à 6 chiffres (par e-mail automatique si le
 * compte en a une, sinon par WhatsApp ou oralement). La personne ouvre le lien, saisit son identifiant (e-mail ou
 * téléphone) et le code, choisit son mot de passe et se retrouve connectée dans son espace. Le compte reste
 * « en attente » (aucune connexion possible) tant que l'invitation n'est pas acceptée.
 */
final class StaffInvitationService
{
    public const TTL_DAYS = 7;

    /**
     * Crée (ou remplace) l'invitation d'un compte. Retourne le lien, le code en clair (à afficher une seule fois)
     * et la date d'expiration.
     *
     * @return array{url: string, code: string, expires_at: string, mailed: bool}
     */
    public static function create(int $userId, ?int $adminId, bool $sendMail = true): array
    {
        $user = User::findWithRole($userId);
        if ($user === null) {
            throw new \InvalidArgumentException('user');
        }
        $token = TokenService::create($userId, TokenService::INVITE, self::TTL_DAYS * 86400);
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Database::execute('UPDATE user_tokens SET code_hash = ? WHERE token_hash = ?', [self::codeHash($token, $code), TokenService::hash($token)]);
        // Tant que le mot de passe n'a pas été choisi, le compte ne peut pas se connecter
        if ($user['status'] === 'active' && $user['last_login_at'] === null) {
            User::update($userId, ['status' => 'pending']);
        }
        $url = route('auth.activate', ['token' => $token], (string) ($user['locale'] ?? 'fr'), true);
        $expires = gmdate('Y-m-d H:i:s', time() + self::TTL_DAYS * 86400);
        $mailed = false;
        if ($sendMail && !empty($user['email'])) {
            $mailed = MailService::send((string) $user['email'], 'staff_invitation', [
                'name' => (string) $user['full_name'], 'url' => $url, 'code' => $code, 'days' => self::TTL_DAYS,
                'role' => __('account.roles.' . $user['role'], [], (string) ($user['locale'] ?? 'fr')),
            ], (string) ($user['locale'] ?? 'fr'), (string) $user['full_name']);
        }
        ActivityLog::record('admin.user.invited', 'user', $userId, ['mailed' => $mailed, 'role' => $user['role']], $adminId);

        return ['url' => $url, 'code' => $code, 'expires_at' => $expires, 'mailed' => $mailed];
    }

    /** L'invitation (lien) est-elle encore valable ? @return array<string, mixed>|null l'utilisateur concerné */
    public static function userForToken(string $token): ?array
    {
        $found = TokenService::find($token, TokenService::INVITE);
        if ($found === null) {
            return null;
        }
        $user = User::findWithRole($found['user_id']);

        return $user !== null && in_array($user['status'], ['pending', 'active'], true) ? $user : null;
    }

    /**
     * Vérifie identifiant + code pour un lien donné. Retourne l'utilisateur ou null (sans préciser ce qui est faux).
     *
     * @return array<string, mixed>|null
     */
    public static function verify(string $token, string $identifier, string $code): ?array
    {
        $user = self::userForToken($token);
        if ($user === null) {
            return null;
        }
        $row = Database::fetch('SELECT code_hash FROM user_tokens WHERE token_hash = ?', [TokenService::hash($token)]);
        if ($row === null || $row['code_hash'] === null || !hash_equals((string) $row['code_hash'], self::codeHash($token, preg_replace('/\D/', '', $code) ?? ''))) {
            return null;
        }
        $identifier = trim($identifier);
        $matches = str_contains($identifier, '@')
            ? mb_strtolower($identifier) === mb_strtolower((string) ($user['email'] ?? ''))
            : PhoneNumber::normalize($identifier) === ($user['phone'] ?? null);

        return $matches ? $user : null;
    }

    /** Accepte l'invitation : mot de passe enregistré, compte activé, identifiant vérifié, session ouverte. */
    public static function accept(string $token, array $user, string $password): void
    {
        $now = gmdate('Y-m-d H:i:s');
        User::update((int) $user['id'], [
            'password_hash'     => Auth::hashPassword($password),
            'status'            => 'active',
            'email_verified_at' => !empty($user['email']) ? ($user['email_verified_at'] ?? $now) : null,
            'phone_verified_at' => !empty($user['phone']) ? ($user['phone_verified_at'] ?? $now) : null,
        ]);
        TokenService::consume($token, TokenService::INVITE);
        TokenService::deleteAll((int) $user['id'], TokenService::REMEMBER);
        ActivityLog::record('user.invitation_accepted', 'user', (int) $user['id'], ['role' => $user['role']], (int) $user['id']);
        Auth::login((int) $user['id']);
        User::update((int) $user['id'], ['last_login_at' => $now]);
    }

    /** Page d'accueil de la personne selon son rôle. */
    public static function homeRoute(string $role): string
    {
        return Auth::homeRouteFor($role);
    }

    /** Invitation en cours d'un compte (pour la fiche admin). @return array{expires_at: string}|null */
    public static function pending(int $userId): ?array
    {
        $row = Database::fetch("SELECT expires_at FROM user_tokens WHERE user_id = ? AND type = 'invite' AND used_at IS NULL AND expires_at > UTC_TIMESTAMP() ORDER BY id DESC LIMIT 1", [$userId]);

        return $row === null ? null : ['expires_at' => (string) $row['expires_at']];
    }

    private static function codeHash(string $token, string $code): string
    {
        return hash_hmac('sha256', $code, TokenService::hash($token));
    }
}
