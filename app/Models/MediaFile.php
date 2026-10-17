<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

/** Fichiers envoyés (audio privé hors racine web, images publiques), §4.2.2. */
class MediaFile extends Model
{
    protected static string $table = 'media_files';

    protected static array $fillable = ['uuid', 'owner_id', 'kind', 'disk', 'path', 'original_name', 'mime_type', 'size_bytes', 'duration_seconds', 'checksum_sha256', 'conversion_status', 'converted_path', 'transcript'];

    /** @return array<string, mixed>|null */
    public static function findByUuid(string $uuid): ?array
    {
        if (!preg_match('/^[0-9a-f-]{36}$/', $uuid)) {
            return null;
        }

        return Database::fetch('SELECT * FROM media_files WHERE uuid = ? LIMIT 1', [$uuid]);
    }

    /** Chemin absolu du fichier sur le disque. @param array<string, mixed> $media */
    public static function absolutePath(array $media): string
    {
        $relative = (string) ($media['converted_path'] ?: $media['path']);

        return ($media['disk'] === 'public' ? public_path('uploads') : storage_path()) . '/' . ltrim($relative, '/');
    }

    /** Un média est « rattaché » s'il est utilisé par un message, une publication, un article ou un sujet. */
    public static function isAttached(int $mediaId): bool
    {
        return Database::fetch(
            'SELECT 1 FROM question_messages WHERE media_id = ?
             UNION SELECT 1 FROM qa_publications WHERE audio_media_id = ?
             UNION SELECT 1 FROM articles WHERE audio_media_id = ?
             UNION SELECT 1 FROM forum_topics WHERE media_id = ?
             UNION SELECT 1 FROM forum_posts WHERE media_id = ? LIMIT 1',
            [$mediaId, $mediaId, $mediaId, $mediaId, $mediaId],
        ) !== null;
    }
}
