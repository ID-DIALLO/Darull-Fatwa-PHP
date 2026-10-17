<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/** Articles du blog (§4.7). */
class Article extends Model
{
    protected static string $table = 'articles';

    protected static bool $softDeletes = true;

    protected static array $fillable = ['author_id', 'rubrique_id', 'cover_path', 'audio_media_id', 'status', 'is_featured', 'allow_comments', 'views', 'reading_minutes', 'published_at', 'deleted_at'];
}
