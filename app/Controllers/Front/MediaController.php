<?php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\MediaFile;
use App\Services\ActivityLog;
use App\Services\QuestionService;

/**
 * Diffusion des audios privés (§4.2.2, §11.5) : contrôle d'accès puis flux avec HTTP Range (206).
 * Tout accès refusé renvoie 404 pour ne pas révéler l'existence du fichier ; chaque écoute par
 * l'équipe est journalisée.
 */
final class MediaController extends Controller
{
    public function audio(Request $request, string $uuid): Response
    {
        $media = MediaFile::findByUuid($uuid);
        if ($media === null || $media['kind'] !== 'audio') {
            throw new HttpException(404);
        }
        $user = Auth::user();
        if (!self::canAccess($media, $user)) {
            throw new HttpException(404);
        }
        $path = MediaFile::absolutePath($media);
        if (!is_file($path)) {
            throw new HttpException(404);
        }
        if ($user !== null && (int) $media['owner_id'] !== (int) $user['id'] && $request->header('Range') === null) {
            ActivityLog::record('media.listened', 'media_file', (int) $media['id'], ['uuid' => $uuid]);
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $mime = $media['converted_path'] ? 'audio/mpeg' : (string) $media['mime_type'];

        return Response::file($path, $mime, $request, 'inline', 'darul-fatwa-' . substr($uuid, 0, 8) . '.' . $extension)
            ->header('X-Content-Type-Options', 'nosniff');
    }

    /**
     * Règle d'accès (§11.5) : propriétaire du média ou de la question, professeur assigné, professeur de la
     * rubrique (auto-attribution), modérateur, administrateur. Un audio de publication publique (voix du
     * professeur) est accessible à tous.
     *
     * @param array<string, mixed> $media
     * @param array<string, mixed>|null $user
     */
    public static function canAccess(array $media, ?array $user): bool
    {
        $mediaId = (int) $media['id'];
        // Audio d'une Q/R publiée ou d'un article publié : public
        $public = Database::fetch(
            'SELECT 1 FROM qa_publications WHERE audio_media_id = ? AND status = ?
             UNION SELECT 1 FROM articles WHERE audio_media_id = ? AND status = ? AND deleted_at IS NULL
             UNION SELECT 1 FROM forum_topics WHERE media_id = ? AND status IN (\'open\',\'locked\') AND deleted_at IS NULL
             UNION SELECT 1 FROM forum_posts WHERE media_id = ? AND status = \'visible\' AND deleted_at IS NULL LIMIT 1',
            [$mediaId, 'published', $mediaId, 'published', $mediaId, $mediaId],
        );
        if ($public !== null) {
            return true;
        }
        if ($user === null) {
            return false;
        }
        if ((int) $media['owner_id'] === (int) $user['id']) {
            return true;
        }
        $questions = Database::fetchAll(
            'SELECT q.* FROM questions q JOIN question_messages m ON m.question_id = q.id WHERE m.media_id = ? AND q.deleted_at IS NULL',
            [$mediaId],
        );
        foreach ($questions as $question) {
            if (QuestionService::canView($question, $user)) {
                return true;
            }
        }

        return false;
    }
}
