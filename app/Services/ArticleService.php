<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Cache;
use App\Core\Database;
use App\Core\Lang;
use App\Models\MediaFile;

/**
 * Blog (§4.7) : articles multilingues (1 à 3 langues), tags par langue, image à la une, version audio,
 * statuts brouillon → soumis (professeur) → planifié / publié → archivé, temps de lecture, vues.
 */
final class ArticleService
{
    public const STATUSES = ['draft', 'submitted', 'scheduled', 'published', 'archived'];

    private const SELECT = 'SELECT a.*, t.title, t.slug, t.excerpt, t.content, t.meta_title, t.meta_description, t.locale AS content_locale,
            u.full_name AS author_name, u.display_name AS author_display_name, sp.photo_path AS author_photo, sp.is_public AS author_public,
            COALESCE(rt.name, rd.name) AS rubrique_name, COALESCE(rt.slug, rd.slug) AS rubrique_slug, r.icon AS rubrique_icon, r.code AS rubrique_code,
            mf.uuid AS audio_uuid, mf.duration_seconds AS audio_duration, mf.mime_type AS audio_mime
        FROM articles a
        JOIN article_translations t ON t.article_id = a.id AND t.locale = ?
        LEFT JOIN users u ON u.id = a.author_id
        LEFT JOIN scholar_profiles sp ON sp.user_id = u.id
        LEFT JOIN rubriques r ON r.id = a.rubrique_id
        LEFT JOIN rubrique_translations rt ON rt.rubrique_id = r.id AND rt.locale = ?
        LEFT JOIN rubrique_translations rd ON rd.rubrique_id = r.id AND rd.locale = ?
        LEFT JOIN media_files mf ON mf.id = a.audio_media_id';

    private const PUBLIC = " a.status = 'published' AND a.deleted_at IS NULL AND (a.published_at IS NULL OR a.published_at <= UTC_TIMESTAMP())";

    /**
     * Liste publique paginée dans la langue courante (seuls les articles traduits dans cette langue).
     *
     * @param array{rubrique?: string, tag?: string, q?: string} $filters
     * @return array{data: list<array<string, mixed>>, total: int, page: int, per_page: int, last_page: int}
     */
    public static function publicList(array $filters, int $page = 1, int $perPage = 9, ?string $locale = null): array
    {
        $locale ??= Lang::getLocale();
        $where = [self::PUBLIC];
        $bindings = [];
        if (ctype_digit((string) ($filters['rubrique'] ?? ''))) {
            $where[] = '(a.rubrique_id = ? OR r.parent_id = ?)';
            array_push($bindings, (int) $filters['rubrique'], (int) $filters['rubrique']);
        }
        if (!empty($filters['tag'])) {
            $where[] = 'EXISTS (SELECT 1 FROM article_tag at JOIN tags tg ON tg.id = at.tag_id WHERE at.article_id = a.id AND tg.locale = ? AND tg.slug = ?)';
            array_push($bindings, $locale, (string) $filters['tag']);
        }
        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            [$sql, $params] = SearchService::matchClause(['t.title', 't.excerpt', 't.content'], $search);
            $where[] = $sql;
            array_push($bindings, ...$params);
        }
        $whereSql = ' WHERE ' . implode(' AND ', $where);
        $from = ' FROM articles a JOIN article_translations t ON t.article_id = a.id AND t.locale = ? LEFT JOIN rubriques r ON r.id = a.rubrique_id';
        $total = (int) Database::value('SELECT COUNT(*)' . $from . $whereSql, array_merge([$locale], $bindings));
        $page = max(1, $page);
        $rows = Database::fetchAll(
            self::SELECT . $whereSql . ' ORDER BY a.is_featured DESC, a.published_at DESC LIMIT ' . (int) $perPage . ' OFFSET ' . (int) (($page - 1) * $perPage),
            array_merge([$locale, $locale, Lang::getDefault()], $bindings),
        );

        return ['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'last_page' => max(1, (int) ceil($total / $perPage))];
    }

    /** @return array<string, mixed>|null */
    public static function findPublic(string $slug, ?string $locale = null): ?array
    {
        $locale ??= Lang::getLocale();
        $article = Database::fetch(self::SELECT . ' WHERE t.slug = ? AND' . self::PUBLIC . ' LIMIT 1', [$locale, $locale, Lang::getDefault(), $slug]);
        if ($article === null) {
            return null;
        }
        $article['tags'] = self::tags((int) $article['id'], $locale);

        return $article;
    }

    /** Article (toute langue, tout statut) pour l'administration et les professeurs. @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        $row = Database::fetch('SELECT a.*, u.full_name AS author_name FROM articles a LEFT JOIN users u ON u.id = a.author_id WHERE a.id = ? AND a.deleted_at IS NULL', [$id]);
        if ($row === null) {
            return null;
        }
        $row['translations'] = Translations::all('article_translations', 'article_id', $id);
        $row['tags'] = [];
        foreach (Lang::locales() as $code) {
            $row['tags'][$code] = implode(', ', array_map(static fn (array $t): string => (string) $t['name'], self::tags($id, $code)));
        }
        $row['audio'] = $row['audio_media_id'] ? MediaFile::find((int) $row['audio_media_id']) : null;

        return $row;
    }

    /** @return list<array<string, mixed>> */
    public static function tags(int $articleId, string $locale): array
    {
        return Database::fetchAll('SELECT tg.* FROM article_tag at JOIN tags tg ON tg.id = at.tag_id WHERE at.article_id = ? AND tg.locale = ? ORDER BY tg.name', [$articleId, $locale]);
    }

    /** Slugs par langue (sélecteur de langue). @return array<string, string> */
    public static function slugs(int $articleId): array
    {
        $slugs = [];
        foreach (Database::fetchAll('SELECT locale, slug FROM article_translations WHERE article_id = ?', [$articleId]) as $row) {
            $slugs[(string) $row['locale']] = (string) $row['slug'];
        }

        return $slugs;
    }

    /** @return list<array<string, mixed>> */
    public static function related(array $article, ?string $locale = null, int $limit = 3): array
    {
        $locale ??= Lang::getLocale();

        return Database::fetchAll(
            self::SELECT . ' WHERE' . self::PUBLIC . ' AND a.id <> ? AND (a.rubrique_id = ? OR ? IS NULL) ORDER BY a.published_at DESC LIMIT ' . (int) $limit,
            [$locale, $locale, Lang::getDefault(), (int) $article['id'], $article['rubrique_id'], $article['rubrique_id']],
        );
    }

    /** @return list<array<string, mixed>> */
    public static function popular(?string $locale = null, int $limit = 5): array
    {
        $locale ??= Lang::getLocale();

        return Cache::remember('articles.popular.' . $locale, 600, static fn (): array => Database::fetchAll(
            'SELECT a.id, a.views, a.published_at, t.title, t.slug FROM articles a JOIN article_translations t ON t.article_id = a.id AND t.locale = ? WHERE' . self::PUBLIC . ' ORDER BY a.views DESC, a.published_at DESC LIMIT ' . (int) $limit,
            [$locale],
        ));
    }

    /** Tags utilisés par des articles publiés dans une langue. @return list<array<string, mixed>> */
    public static function tagCloud(?string $locale = null, int $limit = 30): array
    {
        $locale ??= Lang::getLocale();

        return Cache::remember('articles.tags.' . $locale, 600, static fn (): array => Database::fetchAll(
            'SELECT tg.name, tg.slug, COUNT(*) AS n FROM tags tg JOIN article_tag at ON at.tag_id = tg.id JOIN articles a ON a.id = at.article_id
             WHERE tg.locale = ? AND' . self::PUBLIC . ' GROUP BY tg.id ORDER BY n DESC, tg.name LIMIT ' . (int) $limit,
            [$locale],
        ));
    }

    public static function incrementViews(int $articleId): void
    {
        Database::execute('UPDATE articles SET views = views + 1 WHERE id = ?', [$articleId]);
    }

    /**
     * Liste pour l'administration (tous statuts) ou pour un auteur.
     *
     * @param array<string, string> $filters
     * @return array{data: list<array<string, mixed>>, total: int, page: int, per_page: int, last_page: int}
     */
    public static function manageList(array $filters, ?int $authorId, int $page = 1, int $perPage = 20): array
    {
        $where = ['a.deleted_at IS NULL'];
        $bindings = [Lang::getLocale(), Lang::getDefault(), Lang::getLocale(), Lang::getDefault()];
        if ($authorId !== null) {
            $where[] = 'a.author_id = ?';
            $bindings[] = $authorId;
        }
        if (in_array($filters['status'] ?? '', self::STATUSES, true)) {
            $where[] = 'a.status = ?';
            $bindings[] = $filters['status'];
        }
        if (ctype_digit((string) ($filters['rubrique'] ?? ''))) {
            $where[] = 'a.rubrique_id = ?';
            $bindings[] = (int) $filters['rubrique'];
        }
        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
            $where[] = 'EXISTS (SELECT 1 FROM article_translations s WHERE s.article_id = a.id AND (s.title LIKE ? OR s.content LIKE ?))';
            array_push($bindings, $like, $like);
        }
        $sql = 'FROM articles a
                LEFT JOIN article_translations t ON t.article_id = a.id AND t.locale = ?
                LEFT JOIN article_translations d ON d.article_id = a.id AND d.locale = ?
                LEFT JOIN users u ON u.id = a.author_id
                LEFT JOIN rubriques r ON r.id = a.rubrique_id
                LEFT JOIN rubrique_translations rt ON rt.rubrique_id = r.id AND rt.locale = ?
                LEFT JOIN rubrique_translations rd ON rd.rubrique_id = r.id AND rd.locale = ?
                WHERE ' . implode(' AND ', $where);
        $total = (int) Database::value('SELECT COUNT(*) ' . $sql, $bindings);
        $page = max(1, $page);
        $rows = Database::fetchAll(
            'SELECT a.*, COALESCE(t.title, d.title) AS title, COALESCE(t.slug, d.slug) AS slug, u.full_name AS author_name, COALESCE(rt.name, rd.name) AS rubrique_name,
                    (SELECT GROUP_CONCAT(l.locale ORDER BY l.locale) FROM article_translations l WHERE l.article_id = a.id) AS locales,
                    (SELECT COUNT(*) FROM comments c WHERE c.commentable_type = \'article\' AND c.commentable_id = a.id AND c.status = \'pending\') AS pending_comments ' . $sql .
            " ORDER BY FIELD(a.status, 'submitted') DESC, COALESCE(a.published_at, a.created_at) DESC LIMIT " . (int) $perPage . ' OFFSET ' . (int) (($page - 1) * $perPage),
            $bindings,
        );

        return ['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'last_page' => max(1, (int) ceil($total / $perPage))];
    }

    /**
     * Création / mise à jour (administration ou professeur).
     *
     * @param array<string, mixed> $data Champs du formulaire
     * @param array<string, mixed>|null $cover Entrée $_FILES de l'image à la une
     * @return int Identifiant de l'article
     */
    public static function save(?int $id, array $data, int $actorId, ?array $cover, bool $byScholar): int
    {
        $current = $id !== null ? Database::fetch('SELECT * FROM articles WHERE id = ? AND deleted_at IS NULL', [$id]) : null;
        if ($id !== null && $current === null) {
            throw new \InvalidArgumentException('not_found');
        }
        $status = (string) ($data['status'] ?? 'draft');
        if ($byScholar) {
            // Un professeur ne publie pas lui-même : brouillon ou soumission (§2.1)
            $status = $status === 'submitted' ? 'submitted' : (in_array($current['status'] ?? 'draft', ['draft', 'submitted'], true) ? ($status === 'submitted' ? 'submitted' : 'draft') : (string) $current['status']);
        } elseif (!in_array($status, self::STATUSES, true)) {
            $status = (string) ($current['status'] ?? 'draft');
        }
        $publishedAt = self::utc($data['published_at'] ?? null) ?? ($current['published_at'] ?? null);
        if ($status === 'published' && ($publishedAt === null || $publishedAt > gmdate('Y-m-d H:i:s'))) {
            $publishedAt = $publishedAt !== null && $publishedAt > gmdate('Y-m-d H:i:s') ? $publishedAt : gmdate('Y-m-d H:i:s');
            if ($publishedAt > gmdate('Y-m-d H:i:s')) {
                $status = 'scheduled';
            }
        }
        if ($status === 'scheduled' && ($publishedAt === null || $publishedAt <= gmdate('Y-m-d H:i:s'))) {
            $status = 'published';
            $publishedAt ??= gmdate('Y-m-d H:i:s');
        }
        $coverPath = $current['cover_path'] ?? null;
        if ($cover !== null && ($cover['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            ImageService::delete($coverPath);
            $coverPath = ImageService::store($cover, 'articles', 1280, 720, true, 80);
        } elseif (!empty($data['remove_cover'])) {
            ImageService::delete($coverPath);
            $coverPath = null;
        }
        $audioId = $current['audio_media_id'] ?? null;
        if (!empty($data['remove_audio'])) {
            $audioId = null;
        }
        if (!empty($data['audio_uuid'])) {
            $media = MediaFile::findByUuid((string) $data['audio_uuid']);
            if ($media !== null && $media['kind'] === 'audio' && !MediaFile::isAttached((int) $media['id'])) {
                $audioId = (int) $media['id'];
            }
        }
        $translations = [];
        $defaultContent = '';
        foreach (Lang::locales() as $code) {
            $title = Translations::text(((array) ($data['title'] ?? []))[$code] ?? null, 200);
            $content = Html::purify((string) (((array) ($data['content'] ?? []))[$code] ?? ''), 'rich');
            if ($title === null) {
                $translations[$code] = ['title' => null];
                continue;
            }
            if ($defaultContent === '' || $code === Lang::getDefault()) {
                $defaultContent = $content;
            }
            $translations[$code] = [
                'title'            => $title,
                'slug'             => null,
                'excerpt'          => Translations::text(((array) ($data['excerpt'] ?? []))[$code] ?? null, 500) ?? excerpt($content, 300),
                'content'          => $content,
                'meta_title'       => Translations::text(((array) ($data['meta_title'] ?? []))[$code] ?? null, 160),
                'meta_description' => Translations::text(((array) ($data['meta_description'] ?? []))[$code] ?? null, 255),
            ];
        }
        $attributes = [
            'author_id'       => $byScholar ? ($current['author_id'] ?? $actorId) : (ctype_digit((string) ($data['author_id'] ?? '')) ? (int) $data['author_id'] : ($current['author_id'] ?? $actorId)),
            'rubrique_id'     => ctype_digit((string) ($data['rubrique_id'] ?? '')) ? (int) $data['rubrique_id'] : null,
            'cover_path'      => $coverPath,
            'audio_media_id'  => $audioId,
            'status'          => $status,
            'is_featured'     => !$byScholar && !empty($data['is_featured']) ? 1 : (int) ($byScholar ? ($current['is_featured'] ?? 0) : 0),
            'allow_comments'  => !empty($data['allow_comments']) ? 1 : 0,
            'reading_minutes' => max(1, (int) ceil(str_word_count(strip_tags($defaultContent)) / 200)),
            'published_at'    => in_array($status, ['published', 'scheduled', 'archived'], true) ? $publishedAt : $publishedAt,
        ];

        return Database::transaction(static function () use ($id, $attributes, $translations, $data): int {
            if ($id === null) {
                $id = \App\Models\Article::create($attributes);
            } else {
                \App\Models\Article::update($id, $attributes);
            }
            $existing = Translations::all('article_translations', 'article_id', $id);
            foreach ($translations as $locale => &$row) {
                if ($row['title'] === null) {
                    continue;
                }
                $row['slug'] = isset($existing[$locale]) && $existing[$locale]['title'] === $row['title']
                    ? $existing[$locale]['slug']
                    : SlugService::unique((string) $row['title'], 'article_translations', $locale, ['article_id' => $id]);
            }
            unset($row);
            Translations::save('article_translations', 'article_id', $id, $translations, ['title']);
            self::syncTags($id, (array) ($data['tags'] ?? []));
            Cache::flush('articles.');
            Cache::flush('stats.');

            return $id;
        });
    }

    /** Tags saisis « mot1, mot2 » par langue : création à la volée (slug unique par langue). @param array<string, string> $byLocale */
    private static function syncTags(int $articleId, array $byLocale): void
    {
        Database::execute('DELETE FROM article_tag WHERE article_id = ?', [$articleId]);
        foreach (Lang::locales() as $locale) {
            foreach (array_unique(array_filter(array_map('trim', explode(',', (string) ($byLocale[$locale] ?? ''))))) as $name) {
                $name = mb_substr($name, 0, 60);
                $slug = SlugService::make($name, 80);
                if ($slug === '') {
                    continue;
                }
                $tag = Database::fetch('SELECT id FROM tags WHERE locale = ? AND slug = ?', [$locale, $slug]);
                if ($tag === null) {
                    Database::execute('INSERT INTO tags (locale, name, slug) VALUES (?, ?, ?)', [$locale, $name, $slug]);
                    $tagId = Database::lastInsertId();
                } else {
                    $tagId = (int) $tag['id'];
                }
                Database::execute('INSERT IGNORE INTO article_tag (article_id, tag_id) VALUES (?, ?)', [$articleId, $tagId]);
            }
        }
    }

    public static function setStatus(int $id, string $status, int $actorId): void
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException('invalid_status');
        }
        $publishedAt = $status === 'published' ? 'COALESCE(published_at, UTC_TIMESTAMP())' : 'published_at';
        Database::execute('UPDATE articles SET status = ?, published_at = ' . $publishedAt . ' WHERE id = ?', [$status, $id]);
        ActivityLog::record('admin.article.status', 'article', $id, ['status' => $status], $actorId);
        Cache::flush('articles.');
        Cache::flush('stats.');
    }

    public static function delete(int $id, int $actorId): void
    {
        \App\Models\Article::softDelete($id);
        ActivityLog::record('admin.article.deleted', 'article', $id, [], $actorId);
        Cache::flush('articles.');
        Cache::flush('stats.');
    }

    /** Publication des articles planifiés dont la date est atteinte (cron). */
    public static function publishScheduled(): int
    {
        $count = Database::execute("UPDATE articles SET status = 'published' WHERE status = 'scheduled' AND published_at IS NOT NULL AND published_at <= UTC_TIMESTAMP() AND deleted_at IS NULL");
        if ($count > 0) {
            Cache::flush('articles.');
            Cache::flush('stats.');
        }

        return $count;
    }

    /** Date-heure locale du formulaire → UTC (base). */
    private static function utc(mixed $value): ?string
    {
        $value = trim((string) (is_scalar($value) ? $value : ''));
        if ($value === '') {
            return null;
        }
        try {
            return (new \DateTimeImmutable($value, new \DateTimeZone((string) config('app.timezone', 'UTC'))))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (\Exception) {
            return null;
        }
    }

    /** Date UTC de la base → valeur d'un champ datetime-local. */
    public static function localInput(?string $utc): string
    {
        if ($utc === null || $utc === '') {
            return '';
        }

        return (new \DateTimeImmutable($utc, new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone((string) config('app.timezone', 'UTC')))->format('Y-m-d\TH:i');
    }
}
