<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Core\Auth;
use App\Core\Database;
use App\Models\User;
use App\Services\AccountService;
use App\Services\ActivityLog;
use App\Services\MailService;
use App\Services\TokenService;
use Ramsey\Uuid\Uuid;

/**
 * Gestion des utilisateurs (§4.6.3) : liste filtrable, fiche membre, création, rôle, statut
 * (suspension, bannissement), lien de réinitialisation, suppression (anonymisation).
 */
final class UserAdminService
{
    /**
     * @param array<string, string> $filters
     * @return array{data: list<array<string, mixed>>, total: int, page: int, per_page: int, last_page: int}
     */
    public static function list(array $filters, int $page = 1, int $perPage = 25): array
    {
        $where = ['u.deleted_at IS NULL'];
        $bindings = [];
        if (in_array($filters['role'] ?? '', User::ROLES, true)) {
            $where[] = 'r.slug = ?';
            $bindings[] = $filters['role'];
        }
        if (in_array($filters['status'] ?? '', ['active', 'pending', 'suspended', 'banned'], true)) {
            $where[] = 'u.status = ?';
            $bindings[] = $filters['status'];
        }
        if (preg_match('/^[A-Z]{2}$/', (string) ($filters['country'] ?? ''))) {
            $where[] = 'u.country = ?';
            $bindings[] = $filters['country'];
        }
        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
            $where[] = '(u.full_name LIKE ? OR u.display_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
            array_push($bindings, $like, $like, $like, $like);
        }
        if (($filters['trashed'] ?? '') === '1') {
            $where[0] = 'u.deleted_at IS NOT NULL';
        }
        $sql = 'FROM users u JOIN roles r ON r.id = u.role_id WHERE ' . implode(' AND ', $where);
        $total = (int) Database::value('SELECT COUNT(*) ' . $sql, $bindings);
        $page = max(1, $page);
        $rows = Database::fetchAll(
            'SELECT u.id, u.full_name, u.display_name, u.email, u.phone, u.country, u.status, u.locale, u.created_at, u.last_login_at, u.email_verified_at, r.slug AS role,
                    (SELECT COUNT(*) FROM questions q WHERE q.user_id = u.id AND q.deleted_at IS NULL) AS questions_count ' . $sql .
            ' ORDER BY u.created_at DESC LIMIT ' . (int) $perPage . ' OFFSET ' . (int) (($page - 1) * $perPage),
            $bindings,
        );

        return ['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'last_page' => max(1, (int) ceil($total / $perPage))];
    }

    /**
     * Fiche membre (§4.6.2) : profil, statistiques, questions, messages du forum, idées, journal.
     *
     * @return array<string, mixed>|null
     */
    public static function sheet(int $id): ?array
    {
        $user = User::findWithRole($id, true);
        if ($user === null) {
            return null;
        }
        $user['stats'] = [
            'questions' => (int) Database::value('SELECT COUNT(*) FROM questions WHERE user_id = ? AND deleted_at IS NULL', [$id]),
            'answered'  => (int) Database::value("SELECT COUNT(*) FROM questions WHERE user_id = ? AND deleted_at IS NULL AND status IN ('answered','published','closed')", [$id]),
            'posts'     => (int) Database::value('SELECT COUNT(*) FROM forum_posts WHERE user_id = ? AND deleted_at IS NULL', [$id]),
            'topics'    => (int) Database::value('SELECT COUNT(*) FROM forum_topics WHERE user_id = ? AND deleted_at IS NULL', [$id]),
            'ideas'     => (int) Database::value('SELECT COUNT(*) FROM ideas WHERE user_id = ? AND deleted_at IS NULL', [$id]),
            'comments'  => (int) Database::value('SELECT COUNT(*) FROM comments WHERE user_id = ?', [$id]),
        ];
        $user['questions'] = Database::fetchAll(
            'SELECT q.id, q.reference, q.title, q.status, q.input_type, q.created_at, q.answered_at, q.first_response_minutes, s.full_name AS scholar_name
             FROM questions q LEFT JOIN users s ON s.id = q.assigned_to WHERE q.user_id = ? AND q.deleted_at IS NULL ORDER BY q.created_at DESC LIMIT 100',
            [$id],
        );
        $user['activity'] = Database::fetchAll('SELECT * FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 50', [$id]);
        $user['scholar_profile'] = $user['role'] === 'scholar' ? Database::fetch('SELECT * FROM scholar_profiles WHERE user_id = ?', [$id]) : null;

        return $user;
    }

    /**
     * Création par l'administration (mot de passe temporaire envoyé par lien de réinitialisation).
     *
     * @param array<string, mixed> $data
     */
    public static function create(array $data, int $adminId): int
    {
        $id = User::create([
            'uuid'              => Uuid::uuid4()->toString(),
            'role_id'           => User::roleId((string) $data['role']),
            'full_name'         => $data['full_name'],
            'display_name'      => $data['display_name'] ?? null,
            'email'             => $data['email'] ?? null,
            'phone'             => $data['phone'] ?? null,
            'whatsapp'          => $data['whatsapp'] ?? null,
            'password_hash'     => Auth::hashPassword(bin2hex(random_bytes(16))),
            'locale'            => $data['locale'] ?? 'fr',
            'country'           => $data['country'] ?? null,
            'status'            => 'active',
            'email_verified_at' => !empty($data['email']) ? gmdate('Y-m-d H:i:s') : null,
        ]);
        if ($data['role'] === 'scholar') {
            Database::execute('INSERT IGNORE INTO scholar_profiles (user_id, languages) VALUES (?, ?)', [$id, 'fr']);
        }
        ActivityLog::record('admin.user.created', 'user', $id, ['role' => $data['role']], $adminId);
        if (!empty($data['email'])) {
            self::sendResetLink($id, $adminId, true);
        }

        return $id;
    }

    /** @param array<string, mixed> $data */
    public static function update(int $id, array $data, int $adminId): void
    {
        $allowed = array_intersect_key($data, array_flip(['full_name', 'display_name', 'email', 'phone', 'whatsapp', 'locale', 'country', 'city']));
        foreach ($allowed as $key => $value) {
            if (is_string($value) && trim($value) === '' && $key !== 'full_name') {
                $allowed[$key] = null;
            }
        }
        User::update($id, $allowed);
        ActivityLog::record('admin.user.updated', 'user', $id, ['fields' => array_keys($allowed)], $adminId);
    }

    /** Changement de rôle. Seul un super-administrateur peut attribuer ou retirer les rôles admin/superadmin. */
    public static function setRole(int $id, string $role, int $adminId): void
    {
        $user = User::findWithRole($id);
        if ($user === null || !in_array($role, User::ROLES, true)) {
            throw new \InvalidArgumentException('invalid_role');
        }
        $sensitive = in_array($role, ['admin', 'superadmin'], true) || in_array($user['role'], ['admin', 'superadmin'], true);
        if ($sensitive && !Auth::hasRole('superadmin')) {
            throw new \DomainException('superadmin_required');
        }
        User::update($id, ['role_id' => User::roleId($role)]);
        if ($role === 'scholar') {
            Database::execute('INSERT IGNORE INTO scholar_profiles (user_id, languages) VALUES (?, ?)', [$id, 'fr']);
        }
        ActivityLog::record('admin.user.role', 'user', $id, ['from' => $user['role'], 'to' => $role], $adminId);
    }

    /** Statut : active | suspended | banned. Un administrateur ne peut pas se suspendre lui-même. */
    public static function setStatus(int $id, string $status, int $adminId, ?string $reason = null): void
    {
        if (!in_array($status, ['active', 'suspended', 'banned'], true)) {
            throw new \InvalidArgumentException('invalid_status');
        }
        if ($id === $adminId) {
            throw new \DomainException('self');
        }
        $target = User::findWithRole($id);
        if ($target === null) {
            throw new \InvalidArgumentException('not_found');
        }
        if (in_array($target['role'], ['admin', 'superadmin'], true) && !Auth::hasRole('superadmin')) {
            throw new \DomainException('superadmin_required');
        }
        User::update($id, ['status' => $status]);
        if ($status !== 'active') {
            TokenService::deleteAll($id);
        }
        ActivityLog::record('admin.user.status', 'user', $id, ['status' => $status, 'reason' => $reason], $adminId);
    }

    /** Envoie un lien de réinitialisation du mot de passe (valable 60 min). */
    public static function sendResetLink(int $id, int $adminId, bool $welcome = false): bool
    {
        $user = User::find($id);
        if ($user === null || empty($user['email'])) {
            return false;
        }
        $token = TokenService::create($id, TokenService::PASSWORD_RESET, 60 * 60);
        $sent = MailService::send((string) $user['email'], $welcome ? 'account_created' : 'reset_password', [
            'name' => (string) $user['full_name'],
            'url'  => route('auth.reset', ['token' => $token], (string) ($user['locale'] ?? 'fr'), true),
            'minutes' => 60,
        ], (string) ($user['locale'] ?? 'fr'), (string) $user['full_name']);
        ActivityLog::record('admin.user.reset_link', 'user', $id, [], $adminId);

        return $sent;
    }

    /** Suppression (anonymisation, §4.4) par l'administration. */
    public static function delete(int $id, int $adminId): void
    {
        if ($id === $adminId) {
            throw new \DomainException('self');
        }
        $target = User::findWithRole($id);
        if ($target !== null && in_array($target['role'], ['admin', 'superadmin'], true) && !Auth::hasRole('superadmin')) {
            throw new \DomainException('superadmin_required');
        }
        AccountService::delete($id);
        ActivityLog::record('admin.user.deleted', 'user', $id, [], $adminId);
    }
}
