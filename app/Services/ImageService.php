<?php

declare(strict_types=1);

namespace App\Services;

use Intervention\Image\ImageManager;
use Ramsey\Uuid\Uuid;

/**
 * Images envoyées par les utilisateurs (avatars, couvertures, bannières) : ré-encodées avec
 * Intervention Image pour supprimer les métadonnées (EXIF) et limiter la taille (§12.1),
 * puis enregistrées en WebP dans public/uploads/{dossier}/ sous un nom UUID.
 */
final class ImageService
{
    /**
     * @param array<string, mixed> $file Entrée de $_FILES
     * @return string Chemin public (ex. /uploads/avatars/xxx.webp)
     */
    public static function store(array $file, string $folder, int $width, ?int $height = null, bool $cover = false, int $quality = 82): string
    {
        $folder = trim(preg_replace('/[^a-z0-9_-]/', '', $folder) ?? '', '/');
        $directory = public_path('uploads/' . $folder);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        $name = Uuid::uuid4()->toString() . '.webp';

        $image = ImageManager::gd()->read((string) $file['tmp_name']);
        if ($cover && $height !== null) {
            $image = $image->cover($width, $height);
        } else {
            $image = $image->scaleDown($width, $height);
        }
        $image->toWebp($quality)->save($directory . '/' . $name);

        return '/uploads/' . $folder . '/' . $name;
    }

    /** Supprime un fichier de public/uploads/ (jamais en dehors). */
    public static function delete(?string $publicPath): void
    {
        if ($publicPath === null || !str_starts_with($publicPath, '/uploads/') || str_contains($publicPath, '..')) {
            return;
        }
        $file = public_path(ltrim($publicPath, '/'));
        if (is_file($file)) {
            @unlink($file);
        }
    }
}
