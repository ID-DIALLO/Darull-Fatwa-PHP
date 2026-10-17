<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Boîte à idées (§4.10) : proposition (modération a priori), vote unique annulable, commentaires,
 * statuts et réponse officielle de l'administration.
 */
final class IdeaService
{
    public const CATEGORIES = ['platform', 'content', 'event', 'other'];

    public const STATUSES = ['pending', 'new', 'under_review', 'accepted', 'done', 'declined'];

    /** Statuts visibles publiquement. */
    public const PUBLIC_STATUSES = ['new', 'under_review', 'accepted', 'done', 'declined'];

    private const COLUMNS = 'SELECT i.*, u.full_name AS author_name, u.display_name AS author_display_name, rb.full_name AS responder_name';
    private const FROM = ' FROM ideas i JOIN users u ON u.id = i.user_id LEFT JOIN users rb ON rb.id = i.responded_by';
    private const SELECT = self::COLUMNS . self::FROM;

    /**
     * @param array{sort?: string, status?: string, category?: string} $filters
     * @return array{data: list<array<string, mixed>>, total: int, page: int, per_page: int, last_page: int}
     */
    public static function publicList(array $filters, int $page = 1, int $perPage = 12, ?int $viewerId = null): array
    {
        $where = ['i.deleted_at IS NULL'];
        $bindings = [];
        if (in_array($filters['status'] ?? '', self::PUBLIC_STATUSES, true)) {
            $where[] = 'i.status = ?';
            $bindings[] = $filters['status'];
        } else {
            $where[] = "(i.status IN ('new','under_review','accepted','done','declined')" . ($viewerId !== null ? " OR (i.status = 'pending' AND i.user_id = ?)" : '') . ')';
            if ($viewerId !== null) {
                $bindings[] = $viewerId;
            }
        }
        if (in_array($filters['category'] ?? '', self::CATEGORIES, true)) {
            $where[] = 'i.category = ?';
            $bindings[] = $filters['category'];
        }
        $order = match ($filters['sort'] ?? 'popular') {
            'recent' => 'i.created_at DESC',
            'status' => "FIELD(i.status,'done','accepted','under_review','new','pending','declined'), i.votes_count DESC",
            default  => 'i.votes_count DESC, i.created_at DESC',
        };
        $whereSql = ' WHERE ' . implode(' AND ', $where);
        $total = (int) Database::value('SELECT COUNT(*) FROM ideas i' . $whereSql, $bindings);
        $page = max(1, $page);
        $rows = Database::fetchAll(self::SELECT . $whereSql . ' ORDER BY ' . $order . ' LIMIT ' . (int) $perPage . ' OFFSET ' . (int) (($page - 1) * $perPage), $bindings);
        if ($viewerId !== null && $rows !== []) {
            $voted = array_map('intval', array_column(Database::fetchAll('SELECT idea_id FROM idea_votes WHERE user_id = ? AND idea_id IN (' . implode(',', array_map(static fn (array $r): int => (int) $r['id'], $rows)) . ')', [$viewerId]), 'idea_id'));
            foreach ($rows as &$row) {
                $row['voted'] = in_array((int) $row['id'], $voted, true);
            }
            unset($row);
        }

        return ['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'last_page' => max(1, (int) ceil($total / $perPage))];
    }

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::fetch(self::SELECT . ' WHERE i.id = ? AND i.deleted_at IS NULL', [$id]);
    }

    public static function canView(array $idea, ?array $user): bool
    {
        if (in_array($idea['status'], self::PUBLIC_STATUSES, true)) {
            return true;
        }

        return $user !== null && ((int) $idea['user_id'] === (int) $user['id'] || \App\Core\Auth::can('ideas.moderate'));
    }

    /** @param array<string, mixed> $user */
    public static function create(array $user, string $category, string $title, string $description): int
    {
        Database::execute('INSERT INTO ideas (user_id, category, title, description, status) VALUES (?, ?, ?, ?, ?)', [(int) $user['id'], $category, mb_substr(trim($title), 0, 200), trim($description), 'pending']);
        $id = Database::lastInsertId();
        ActivityLog::record('idea.created', 'idea', $id, ['category' => $category], (int) $user['id']);
        $staff = Database::fetchAll("SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug IN ('moderator','admin','superadmin') AND u.status = 'active' AND u.deleted_at IS NULL");
        NotificationService::notifyMany(array_map(static fn (array $u): int => (int) $u['id'], $staff), 'idea.pending', ['title' => mb_substr(trim($title), 0, 200)], route('admin.ideas', ['status' => 'pending']), null);

        return $id;
    }

    /** Vote unique par membre, annulable (contrôle serveur par clé primaire). @return array{0: bool, 1: int} */
    public static function toggleVote(int $userId, int $ideaId): array
    {
        $idea = self::find($ideaId);
        if ($idea === null || !in_array($idea['status'], ['new', 'under_review', 'accepted'], true)) {
            throw new \InvalidArgumentException('not_votable');
        }
        $voted = Database::fetch('SELECT 1 FROM idea_votes WHERE idea_id = ? AND user_id = ?', [$ideaId, $userId]) !== null;
        if ($voted) {
            Database::execute('DELETE FROM idea_votes WHERE idea_id = ? AND user_id = ?', [$ideaId, $userId]);
        } else {
            Database::execute('INSERT IGNORE INTO idea_votes (idea_id, user_id) VALUES (?, ?)', [$ideaId, $userId]);
        }
        $count = (int) Database::value('SELECT COUNT(*) FROM idea_votes WHERE idea_id = ?', [$ideaId]);
        Database::execute('UPDATE ideas SET votes_count = ? WHERE id = ?', [$count, $ideaId]);

        return [!$voted, $count];
    }

    public static function hasVoted(?int $userId, int $ideaId): bool
    {
        return $userId !== null && Database::fetch('SELECT 1 FROM idea_votes WHERE idea_id = ? AND user_id = ?', [$ideaId, $userId]) !== null;
    }

    /** Commentaire d'un membre : visible immédiatement sauf membre en modération (mêmes règles que le forum) ou mot interdit. */
    public static function comment(array $idea, array $user, string $body): int
    {
        $body = trim(mb_substr($body, 0, 2000));
        if (mb_strlen($body) < 3) {
            throw new \InvalidArgumentException('too_short');
        }
        $pending = ForumService::needsModeration($user) || ForumService::containsBannedWord($body) !== null;
        Database::execute('INSERT INTO idea_comments (idea_id, user_id, body, status) VALUES (?, ?, ?, ?)', [(int) $idea['id'], (int) $user['id'], $body, $pending ? 'pending' : 'visible']);
        $id = Database::lastInsertId();
        self::refreshCommentsCount((int) $idea['id']);
        ActivityLog::record('idea.comment_created', 'idea_comment', $id, ['idea' => (int) $idea['id'], 'pending' => $pending], (int) $user['id']);
        if (!$pending && (int) $idea['user_id'] !== (int) $user['id']) {
            NotificationService::notify((int) $idea['user_id'], 'idea.comment', ['title' => (string) $idea['title']], route('ideas.show', ['id' => (int) $idea['id']]) . '#comments');
        }

        return $id;
    }

    /** @return list<array<string, mixed>> */
    public static function comments(int $ideaId, ?int $viewerId, bool $moderator = false): array
    {
        $where = "c.idea_id = ? AND (c.status = 'visible'" . ($moderator ? " OR c.status = 'pending'" : ($viewerId !== null ? " OR (c.status = 'pending' AND c.user_id = ?)" : '')) . ')';
        $bindings = [$ideaId];
        if (!$moderator && $viewerId !== null) {
            $bindings[] = $viewerId;
        }

        return Database::fetchAll('SELECT c.*, u.full_name, u.display_name, u.avatar_path, r.slug AS role FROM idea_comments c JOIN users u ON u.id = c.user_id JOIN roles r ON r.id = u.role_id WHERE ' . $where . ' ORDER BY c.created_at ASC', $bindings);
    }

    public static function moderateComment(int $commentId, string $status, int $moderatorId): void
    {
        if (!in_array($status, ['visible', 'hidden', 'pending'], true)) {
            throw new \InvalidArgumentException('invalid_status');
        }
        Database::execute('UPDATE idea_comments SET status = ? WHERE id = ?', [$status, $commentId]);
        $ideaId = (int) Database::value('SELECT idea_id FROM idea_comments WHERE id = ?', [$commentId]);
        self::refreshCommentsCount($ideaId);
        ActivityLog::record('admin.idea.comment_' . $status, 'idea_comment', $commentId, [], $moderatorId);
    }

    private static function refreshCommentsCount(int $ideaId): void
    {
        Database::execute("UPDATE ideas SET comments_count = (SELECT COUNT(*) FROM idea_comments WHERE idea_id = ? AND status = 'visible') WHERE id = ?", [$ideaId, $ideaId]);
    }

    /** Changement de statut et/ou réponse officielle : l'auteur est notifié. */
    public static function setStatus(int $ideaId, string $status, ?string $response, int $adminId): void
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException('invalid_status');
        }
        $idea = self::find($ideaId);
        if ($idea === null) {
            throw new \InvalidArgumentException('not_found');
        }
        $response = $response !== null && trim($response) !== '' ? trim(mb_substr($response, 0, 5000)) : null;
        if ($response !== null) {
            Database::execute('UPDATE ideas SET status = ?, admin_response = ?, responded_by = ?, responded_at = UTC_TIMESTAMP() WHERE id = ?', [$status, $response, $adminId, $ideaId]);
        } else {
            Database::execute('UPDATE ideas SET status = ? WHERE id = ?', [$status, $ideaId]);
        }
        ActivityLog::record('admin.idea.status', 'idea', $ideaId, ['from' => $idea['status'], 'to' => $status, 'response' => $response !== null], $adminId);
        if ($idea['status'] !== $status || $response !== null) {
            NotificationService::notify((int) $idea['user_id'], $response !== null ? 'idea.response' : 'idea.status', ['title' => (string) $idea['title'], 'status' => __('ideas.status.' . $status)], route('ideas.show', ['id' => $ideaId]));
        }
    }

    public static function delete(int $ideaId, int $adminId): void
    {
        Database::execute('UPDATE ideas SET deleted_at = UTC_TIMESTAMP() WHERE id = ?', [$ideaId]);
        ActivityLog::record('admin.idea.deleted', 'idea', $ideaId, [], $adminId);
    }

    /**
     * Liste d'administration.
     *
     * @param array<string, string> $filters
     * @return array{data: list<array<string, mixed>>, total: int, page: int, per_page: int, last_page: int}
     */
    public static function adminList(array $filters, int $page = 1, int $perPage = 25): array
    {
        $where = ['i.deleted_at IS NULL'];
        $bindings = [];
        if (in_array($filters['status'] ?? '', self::STATUSES, true)) {
            $where[] = 'i.status = ?';
            $bindings[] = $filters['status'];
        }
        if (in_array($filters['category'] ?? '', self::CATEGORIES, true)) {
            $where[] = 'i.category = ?';
            $bindings[] = $filters['category'];
        }
        $whereSql = ' WHERE ' . implode(' AND ', $where);
        $total = (int) Database::value('SELECT COUNT(*) FROM ideas i' . $whereSql, $bindings);
        $page = max(1, $page);
        $rows = Database::fetchAll(self::COLUMNS . ", (SELECT COUNT(*) FROM idea_comments c WHERE c.idea_id = i.id AND c.status = 'pending') AS pending_comments" . self::FROM . $whereSql . " ORDER BY FIELD(i.status,'pending') DESC, i.created_at DESC LIMIT " . (int) $perPage . ' OFFSET ' . (int) (($page - 1) * $perPage), $bindings);

        return ['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'last_page' => max(1, (int) ceil($total / $perPage))];
    }

    /** Idées d'un membre. @return list<array<string, mixed>> */
    public static function ideasOf(int $userId): array
    {
        return Database::fetchAll(self::SELECT . ' WHERE i.user_id = ? AND i.deleted_at IS NULL ORDER BY i.created_at DESC LIMIT 100', [$userId]);
    }
}
