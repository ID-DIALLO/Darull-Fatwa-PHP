<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

/** Notifications in-app (cloche), §4.12. */
class Notification extends Model
{
    protected static string $table = 'notifications';

    protected static array $fillable = ['user_id', 'type', 'data', 'url', 'read_at'];

    public static function unreadCount(int $userId): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL', [$userId]);
    }

    /** @return list<array<string, mixed>> */
    public static function latest(int $userId, int $limit = 20, int $offset = 0): array
    {
        $rows = Database::fetchAll('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset), [$userId]);
        foreach ($rows as &$row) {
            $data = json_decode((string) ($row['data'] ?? '[]'), true);
            $row['data'] = is_array($data) ? $data : [];
        }

        return $rows;
    }

    public static function markAllRead(int $userId): int
    {
        return Database::execute('UPDATE notifications SET read_at = UTC_TIMESTAMP() WHERE user_id = ? AND read_at IS NULL', [$userId]);
    }

    public static function markRead(int $userId, int $id): int
    {
        return Database::execute('UPDATE notifications SET read_at = COALESCE(read_at, UTC_TIMESTAMP()) WHERE user_id = ? AND id = ?', [$userId, $id]);
    }
}
