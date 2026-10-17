<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\ActivityLog;
use App\Services\AudioException;
use App\Services\AudioService;

/**
 * API d'envoi des enregistrements (§11.5) : POST /api/audio → { media_uuid, duration, url } ;
 * DELETE /api/audio/{uuid} pour un enregistrement non encore rattaché à une question.
 */
final class AudioUploadController extends Controller
{
    public function store(Request $request): Response
    {
        $file = $request->file('audio');
        if ($file === null) {
            return $this->json(['message' => __('questions.audio.errors.upload_failed')], 422);
        }
        $duration = $request->input('duration');
        try {
            $media = AudioService::store($file, Auth::id(), is_numeric($duration) ? (int) round((float) $duration) : null);
        } catch (AudioException $e) {
            return $this->json(['message' => $e->userMessage(), 'reason' => $e->reason], 422);
        }
        ActivityLog::record('media.uploaded', 'media_file', (int) $media['id'], ['mime' => $media['mime_type'], 'size' => $media['size_bytes'], 'duration' => $media['duration_seconds']]);

        return $this->json([
            'media_uuid' => $media['uuid'],
            'duration'   => $media['duration_seconds'] !== null ? (int) $media['duration_seconds'] : null,
            'mime_type'  => $media['mime_type'],
            'url'        => route('media.audio', ['uuid' => $media['uuid']]),
        ], 201);
    }

    public function destroy(Request $request, string $uuid): Response
    {
        if (!AudioService::deleteUnattached($uuid, (int) Auth::id())) {
            return $this->json(['message' => __('common.errors.404.title')], 404);
        }

        return $this->json(['deleted' => true]);
    }
}
