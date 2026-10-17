<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/** Commentaires (articles, Q/R) : membres connectés, modération a priori (§4.7). */
final class CommentService
{
    public const TYPES = ['article', 'qa'];

    public const STATUSES = ['pending', 'approved', 'rejected', 'spam'];

    /** Dépose un commentaire en attente de modération. */
    public static function add(string $type, int $targetId, int $userId, string $body, ?int $parentId = null): int
    {
        if (!in_array($type, self::TYPES, true)) {
            throw new \InvalidArgumentException('invalid_target');
        }
        $body = trim(mb_substr($body, 0, 3000));
        if (mb_strlen($body) < 3) {
            throw new \InvalidArgumentException('too_short');
        }
        if ($parentId !== null && Database::fetch("SELECT 1 FROM comments WHERE id = ? AND commentable_type = ? AND commentable_id = ? AND status = 'approved'", [$parentId, $type, $targetId]) === null) {
            $parentId = null;
        }
        Database::execute(
            'INSERT INTO comments (commentable_type, commentable_id, user_id, parent_id, body, status) VALUES (?, ?, ?, ?, ?, ?)',
            [$type, $targetId, $userId, $parentId, $body, 'pending'],
        );
        $id = Database::lastInsertId();
        ActivityLog::record('comment.created', 'comment', $id, ['type' => $type, 'target' => $targetId], $userId);
        $staff = Database::fetchAll("SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug IN ('moderator','admin','superadmin') AND u.status = 'active' AND u.deleted_at IS NULL");
        NotificationService::notifyMany(array_map(static fn (array $u): int => (int) $u['id'], $staff), 'comment.pending', ['count' => 1], route('admin.comments', ['status' => 'pending']), null);

        return $id;
    }

    /** Commentaires approuvés d'une cible, avec l'auteur (nom public). @return list<array<string, mixed>> */
    public static function approvedFor(string $type, int $targetId): array
    {
        return Database::fetchAll(
            "SELECT c.*, u.full_name, u.display_name, u.avatar_path, r.slug AS role FROM comments c JOIN users u ON u.id = c.user_id JOIN roles r ON r.id = u.role_id
             WHERE c.commentable_type = ? AND c.commentable_id = ? AND c.status = 'approved' ORDER BY c.created_at ASC",
            [$type, $targetId],
        );
    }

    /** Commentaires en attente d'un membre sur une cible (affichés à lui seul). @return list<array<string, mixed>> */
    public static function pendingOf(int $userId, string $type, int $targetId): array
    {
        return Database::fetchAll("SELECT * FROM comments WHERE user_id = ? AND commentable_type = ? AND commentable_id = ? AND status = 'pending' ORDER BY created_at", [$userId, $type, $targetId]);
    }

    public static function moderate(int $id, string $status, int $moderatorId): void
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException('invalid_status');
        }
        Database::execute('UPDATE comments SET status = ?, moderated_by = ? WHERE id = ?', [$status, $moderatorId, $id]);
        ActivityLog::record('admin.comment.' . $status, 'comment', $id, [], $moderatorId);
    }

    public static function delete(int $id, int $moderatorId): void
    {
        Database::execute('DELETE FROM comments WHERE id = ?', [$id]);
        ActivityLog::record('admin.comment.deleted', 'comment', $id, [], $moderatorId);
    }

    /**
     * Liste pour la modération.
     *
     * @param array<string, string> $filters
     * @return array{data: list<array<string, mixed>>, total: int, page: int, per_page: int, last_page: int}
     */
    public static function adminList(array $filters, int $page = 1, int $perPage = 30): array
    {
        $where = ['1 = 1'];
        $bindings = [];
        if (in_array($filters['status'] ?? '', self::STATUSES, true)) {
            $where[] = 'c.status = ?';
            $bindings[] = $filters['status'];
        }
        if (in_array($filters['type'] ?? '', self::TYPES, true)) {
            $where[] = 'c.commentable_type = ?';
            $bindings[] = $filters['type'];
        }
        $sql = ' FROM comments c JOIN users u ON u.id = c.user_id WHERE ' . implode(' AND ', $where);
        $total = (int) Database::value('SELECT COUNT(*)' . $sql, $bindings);
        $page = max(1, $page);
        $rows = Database::fetchAll(
            'SELECT c.*, u.full_name, u.email,
                    (SELECT COALESCE(MAX(t.title), \'\') FROM article_translations t WHERE c.commentable_type = \'article\' AND t.article_id = c.commentable_id) AS article_title,
                    (SELECT COALESCE(MAX(t.title), \'\') FROM qa_publication_translations t WHERE c.commentable_type = \'qa\' AND t.publication_id = c.commentable_id) AS qa_title' . $sql .
            ' ORDER BY FIELD(c.status, \'pending\') DESC, c.created_at DESC LIMIT ' . (int) $perPage . ' OFFSET ' . (int) (($page - 1) * $perPage),
            $bindings,
        );

        return ['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'last_page' => max(1, (int) ceil($total / $perPage))];
    }

    /** @return array<string, int> */
    public static function counts(): array
    {
        $counts = [];
        foreach (Database::fetchAll('SELECT status, COUNT(*) AS n FROM comments GROUP BY status') as $row) {
            $counts[(string) $row['status']] = (int) $row['n'];
        }

        return $counts;
    }
}
