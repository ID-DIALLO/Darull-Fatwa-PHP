<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Lang;

/**
 * Base publique de questions-réponses (§4.8) : publications anonymisées dans la langue courante,
 * recherche plein texte, tri, détail avec professeur, audio (voix du professeur uniquement) et références.
 */
final class QaService
{
    private const SELECT = 'SELECT p.*, t.title, t.slug, t.question_text, t.answer_html, t.references_json, t.meta_description,
            COALESCE(rt.name, rd.name) AS rubrique_name, COALESCE(rt.slug, rd.slug) AS rubrique_slug, r.icon AS rubrique_icon, r.code AS rubrique_code,
            s.id AS scholar_user_id, s.full_name AS scholar_name, sp.photo_path AS scholar_photo, sp.is_public AS scholar_public, st.title AS scholar_title,
            mf.uuid AS audio_uuid, mf.duration_seconds AS audio_duration, mf.mime_type AS audio_mime
        FROM qa_publications p
        JOIN qa_publication_translations t ON t.publication_id = p.id AND t.locale = ?
        JOIN rubriques r ON r.id = p.rubrique_id
        LEFT JOIN rubrique_translations rt ON rt.rubrique_id = r.id AND rt.locale = ?
        LEFT JOIN rubrique_translations rd ON rd.rubrique_id = r.id AND rd.locale = ?
        LEFT JOIN users s ON s.id = p.scholar_id
        LEFT JOIN scholar_profiles sp ON sp.user_id = s.id
        LEFT JOIN scholar_translations st ON st.user_id = s.id AND st.locale = ?
        LEFT JOIN media_files mf ON mf.id = p.audio_media_id';

    /**
     * @param array{rubrique?: string, sort?: string, q?: string} $filters
     * @return array{data: list<array<string, mixed>>, total: int, page: int, per_page: int, last_page: int}
     */
    public static function publicList(array $filters, int $page = 1, int $perPage = 12, ?string $locale = null): array
    {
        $locale ??= Lang::getLocale();
        $where = ["p.status = 'published'"];
        $bindings = [];
        if (ctype_digit((string) ($filters['rubrique'] ?? ''))) {
            $where[] = '(p.rubrique_id = ? OR r.parent_id = ?)';
            array_push($bindings, (int) $filters['rubrique'], (int) $filters['rubrique']);
        }
        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            [$sql, $params] = SearchService::matchClause(['t.title', 't.question_text', 't.answer_html'], $search);
            $where[] = $sql;
            array_push($bindings, ...$params);
        }
        $order = ($filters['sort'] ?? 'recent') === 'popular' ? 'p.views DESC, p.published_at DESC' : 'p.published_at DESC';
        $whereSql = ' WHERE ' . implode(' AND ', $where);
        $total = (int) Database::value('SELECT COUNT(*) FROM qa_publications p JOIN qa_publication_translations t ON t.publication_id = p.id AND t.locale = ? JOIN rubriques r ON r.id = p.rubrique_id' . $whereSql, array_merge([$locale], $bindings));
        $page = max(1, $page);
        $rows = Database::fetchAll(
            self::SELECT . $whereSql . ' ORDER BY ' . $order . ' LIMIT ' . (int) $perPage . ' OFFSET ' . (int) (($page - 1) * $perPage),
            array_merge([$locale, $locale, Lang::getDefault(), $locale], $bindings),
        );

        return ['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'last_page' => max(1, (int) ceil($total / $perPage))];
    }

    /** @return array<string, mixed>|null */
    public static function findPublic(string $slug, ?string $locale = null): ?array
    {
        $locale ??= Lang::getLocale();
        $row = Database::fetch(self::SELECT . " WHERE t.slug = ? AND p.status = 'published' LIMIT 1", [$locale, $locale, Lang::getDefault(), $locale, $slug]);
        if ($row === null) {
            return null;
        }
        $refs = json_decode((string) ($row['references_json'] ?? '[]'), true);
        $row['references'] = is_array($refs) ? $refs : [];
        // Le professeur n'est nommé que si son profil est public (§4.5)
        if ((int) ($row['scholar_public'] ?? 0) !== 1) {
            $row['scholar_name'] = null;
            $row['scholar_photo'] = null;
            $row['scholar_title'] = null;
        }

        return $row;
    }

    /** @return array<string, string> locale => slug */
    public static function slugs(int $publicationId): array
    {
        $slugs = [];
        foreach (Database::fetchAll('SELECT locale, slug FROM qa_publication_translations WHERE publication_id = ?', [$publicationId]) as $row) {
            $slugs[(string) $row['locale']] = (string) $row['slug'];
        }

        return $slugs;
    }

    /** @return list<array<string, mixed>> */
    public static function related(array $publication, ?string $locale = null, int $limit = 4): array
    {
        $locale ??= Lang::getLocale();

        return Database::fetchAll(
            self::SELECT . " WHERE p.status = 'published' AND p.id <> ? AND p.rubrique_id = ? ORDER BY p.published_at DESC LIMIT " . (int) $limit,
            [$locale, $locale, Lang::getDefault(), $locale, (int) $publication['id'], (int) $publication['rubrique_id']],
        );
    }

    public static function incrementViews(int $id): void
    {
        Database::execute('UPDATE qa_publications SET views = views + 1 WHERE id = ?', [$id]);
    }

    /** Nombre de Q/R publiées par rubrique (filtre). @return array<int, int> */
    public static function countsByRubrique(?string $locale = null): array
    {
        $locale ??= Lang::getLocale();
        $counts = [];
        foreach (Database::fetchAll("SELECT p.rubrique_id, COUNT(*) AS n FROM qa_publications p JOIN qa_publication_translations t ON t.publication_id = p.id AND t.locale = ? WHERE p.status = 'published' GROUP BY p.rubrique_id", [$locale]) as $row) {
            $counts[(int) $row['rubrique_id']] = (int) $row['n'];
        }

        return $counts;
    }
}
