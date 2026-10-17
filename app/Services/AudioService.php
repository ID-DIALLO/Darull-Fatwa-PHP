<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\MediaFile;
use Ramsey\Uuid\Uuid;

/**
 * Enregistrements vocaux (§4.2.2) : vérification du type réel (finfo), de l'extension, de la taille et de la
 * durée ; nom UUID ; stockage hors de la racine web (storage/audio/AAAA/MM/) ; empreinte SHA-256 ;
 * ligne media_files. Les formats autres que MP3 (repli MediaRecorder, notes WhatsApp) sont conservés tels quels
 * avec conversion_status = 'pending'.
 */
final class AudioService
{
    public const MIN_SECONDS = 2;

    /** Extension → types MIME réels acceptés (finfo). */
    public const MIMES = [
        'mp3'  => ['audio/mpeg', 'audio/mp3', 'audio/mpeg3', 'audio/x-mpeg-3'],
        'webm' => ['audio/webm', 'video/webm'],
        'ogg'  => ['audio/ogg', 'application/ogg', 'video/ogg'],
        'oga'  => ['audio/ogg', 'application/ogg'],
        'opus' => ['audio/ogg', 'application/ogg', 'audio/opus'],
        'm4a'  => ['audio/mp4', 'audio/x-m4a', 'audio/m4a', 'video/mp4', 'audio/aac'],
        'mp4'  => ['audio/mp4', 'video/mp4'],
        'aac'  => ['audio/aac', 'audio/x-aac', 'audio/x-hx-aac-adts'],
        'wav'  => ['audio/wav', 'audio/x-wav', 'audio/wave', 'audio/vnd.wave'],
        'amr'  => ['audio/amr', 'audio/AMR'],
    ];

    /**
     * Enregistre un fichier audio envoyé et retourne la ligne media_files.
     *
     * @param array<string, mixed> $file Entrée de $_FILES (name, tmp_name, size, error)
     * @param int|null $clientDuration Durée annoncée par le navigateur (secondes), utilisée si le format ne permet pas de la calculer
     * @return array<string, mixed>
     * @throws AudioException
     */
    public static function store(array $file, ?int $ownerId, ?int $clientDuration = null, string $source = 'web'): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_file((string) ($file['tmp_name'] ?? ''))) {
            throw new AudioException('upload_failed');
        }
        $tmp = (string) $file['tmp_name'];
        $size = (int) ($file['size'] ?? filesize($tmp));
        $maxBytes = self::maxBytes();
        if ($size <= 0) {
            throw new AudioException('empty');
        }
        if ($size > $maxBytes) {
            throw new AudioException('too_large', ['max' => (int) round($maxBytes / 1048576)]);
        }

        $extension = self::detectExtension($file);
        if ($extension === null) {
            throw new AudioException('invalid_type');
        }
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        $duration = $extension === 'mp3' ? self::mp3Duration($tmp) : null;
        $duration ??= $clientDuration !== null && $clientDuration > 0 ? min($clientDuration, 24 * 3600) : null;
        $maxSeconds = self::maxSeconds();
        if ($duration !== null && $duration > $maxSeconds + 5) {
            throw new AudioException('too_long', ['max' => (int) round($maxSeconds / 60)]);
        }
        if ($duration !== null && $duration < self::MIN_SECONDS && $source === 'web') {
            throw new AudioException('too_short', ['min' => self::MIN_SECONDS]);
        }

        $uuid = Uuid::uuid4()->toString();
        $relative = 'audio/' . gmdate('Y') . '/' . gmdate('m') . '/' . $uuid . '.' . $extension;
        $target = storage_path($relative);
        $directory = dirname($target);
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new AudioException('storage_unavailable');
        }
        $moved = PHP_SAPI === 'cli' || !is_uploaded_file($tmp) ? rename($tmp, $target) || copy($tmp, $target) : move_uploaded_file($tmp, $target);
        if (!$moved) {
            throw new AudioException('storage_unavailable');
        }
        @chmod($target, 0640);

        $id = MediaFile::create([
            'uuid'              => $uuid,
            'owner_id'          => $ownerId,
            'kind'              => 'audio',
            'disk'              => 'private',
            'path'              => $relative,
            'original_name'     => mb_substr((string) ($file['name'] ?? ''), 0, 255) ?: null,
            'mime_type'         => mb_substr($mime, 0, 100),
            'size_bytes'        => $size,
            'duration_seconds'  => $duration !== null ? min(65535, $duration) : null,
            'checksum_sha256'   => hash_file('sha256', $target),
            'conversion_status' => $extension === 'mp3' ? 'none' : 'pending',
        ]);

        return MediaFile::find($id) ?? [];
    }

    /** Supprime un média non rattaché appartenant à l'utilisateur (DELETE /api/audio/{uuid}). */
    public static function deleteUnattached(string $uuid, int $ownerId): bool
    {
        $media = MediaFile::findByUuid($uuid);
        if ($media === null || (int) $media['owner_id'] !== $ownerId || MediaFile::isAttached((int) $media['id'])) {
            return false;
        }
        self::deleteFile($media);
        Database::execute('DELETE FROM media_files WHERE id = ?', [(int) $media['id']]);

        return true;
    }

    /** @param array<string, mixed> $media */
    public static function deleteFile(array $media): void
    {
        foreach ([$media['path'] ?? null, $media['converted_path'] ?? null] as $relative) {
            if ($relative) {
                $file = ($media['disk'] === 'public' ? public_path('uploads') : storage_path()) . '/' . ltrim((string) $relative, '/');
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }
    }

    /**
     * Extension retenue : celle du nom d'origine si elle est en liste blanche et cohérente avec le type réel,
     * sinon déduite du type réel. Null si le fichier n'est pas un audio accepté.
     *
     * @param array<string, mixed> $file
     */
    public static function detectExtension(array $file): ?string
    {
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
        $declared = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        $allowedExtensions = (array) config('upload.audio_extensions', array_keys(self::MIMES));
        if ($declared !== '' && isset(self::MIMES[$declared]) && in_array($declared, $allowedExtensions, true) && in_array($mime, self::MIMES[$declared], true)) {
            return $declared === 'mp4' ? 'm4a' : $declared;
        }
        foreach (self::MIMES as $extension => $mimes) {
            if (in_array($extension, $allowedExtensions, true) && in_array($mime, $mimes, true)) {
                return $extension;
            }
        }

        return null;
    }

    /**
     * Durée d'un MP3 en secondes, par lecture des en-têtes de trames (CBR et VBR), sans dépendance externe.
     */
    public static function mp3Duration(string $path): ?int
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return null;
        }
        $bitrates = [
            1 => [0, 32, 40, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 320], // MPEG-1 couche III
            2 => [0, 8, 16, 24, 32, 40, 48, 56, 64, 80, 96, 112, 128, 144, 160],    // MPEG-2/2.5 couche III
        ];
        $rates = [0 => [44100, 48000, 32000], 2 => [22050, 24000, 16000], 3 => [11025, 12000, 8000]];
        $offset = 0;
        $head = fread($handle, 10);
        if ($head !== false && str_starts_with($head, 'ID3')) {
            $offset = 10 + ((ord($head[6]) & 0x7F) << 21 | (ord($head[7]) & 0x7F) << 14 | (ord($head[8]) & 0x7F) << 7 | (ord($head[9]) & 0x7F));
        }
        fseek($handle, $offset);
        $seconds = 0.0;
        $frames = 0;
        $size = (int) filesize($path);
        while ($offset + 4 <= $size && $frames < 200000) {
            fseek($handle, $offset);
            $bytes = fread($handle, 4);
            if ($bytes === false || strlen($bytes) < 4) {
                break;
            }
            $h = unpack('N', $bytes)[1];
            if (($h & 0xFFE00000) !== 0xFFE00000) { // pas une synchro : on avance d'un octet (données parasites)
                $offset++;
                continue;
            }
            $versionBits = ($h >> 19) & 3;   // 3 = MPEG-1, 2 = MPEG-2, 0 = MPEG-2.5
            $layerBits = ($h >> 17) & 3;     // 1 = couche III
            $bitrateIndex = ($h >> 12) & 15;
            $rateIndex = ($h >> 10) & 3;
            $padding = ($h >> 9) & 1;
            if ($versionBits === 1 || $layerBits !== 1 || $bitrateIndex === 0 || $bitrateIndex === 15 || $rateIndex === 3) {
                $offset++;
                continue;
            }
            $mpeg1 = $versionBits === 3;
            $bitrate = $bitrates[$mpeg1 ? 1 : 2][$bitrateIndex] * 1000;
            $sampleRate = $rates[$mpeg1 ? 0 : ($versionBits === 2 ? 2 : 3)][$rateIndex];
            $samples = $mpeg1 ? 1152 : 576;
            $length = (int) floor($samples / 8 * $bitrate / $sampleRate) + $padding;
            if ($length < 4) {
                $offset++;
                continue;
            }
            $seconds += $samples / $sampleRate;
            $frames++;
            $offset += $length;
        }
        fclose($handle);

        return $frames > 0 ? (int) round($seconds) : null;
    }

    public static function maxBytes(): int
    {
        $mb = (int) (\App\Models\Setting::get('audio_max_mb') ?? config('upload.audio_max_mb', 10));

        return max(1, $mb) * 1048576;
    }

    public static function maxSeconds(): int
    {
        return max(10, (int) (\App\Models\Setting::get('voice_max_seconds') ?? config('upload.voice_max_seconds', 300)));
    }
}
