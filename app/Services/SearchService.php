<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Lang;

/**
 * Recherche plein texte (§4.13) : MySQL FULLTEXT en mode booléen (préfixes « mot* ») pour les mots
 * d'au moins 3 lettres, repli LIKE pour les mots plus courts ; résultats regroupés dans la langue courante.
 */
final class SearchService
{
    public const MIN_TOKEN = 3;

    /**
     * Clause SQL de recherche sur des colonnes indexées FULLTEXT (la même liste et le même ordre que l'index).
     *
     * @param list<string> $columns
     * @return array{0: string, 1: list<string>}
     */
    public static function matchClause(array $columns, string $query): array
    {
        $words = self::words($query);
        $long = array_values(array_filter($words, static fn (string $w): bool => mb_strlen($w) >= self::MIN_TOKEN));
        if ($long !== []) {
            $boolean = implode(' ', array_map(static fn (string $w): string => '+' . $w . '*', $long));

            return ['MATCH(' . implode(', ', $columns) . ') AGAINST (? IN BOOLEAN MODE)', [$boolean]];
        }
        // Mots courts uniquement : LIKE sur chaque colonne
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], implode(' ', $words)) . '%';
        $parts = array_map(static fn (string $c): string => $c . ' LIKE ?', $columns);

        return ['(' . implode(' OR ', $parts) . ')', array_fill(0, count($columns), $like)];
    }

    /** Mots significatifs d'une requête (caractères de recherche booléens retirés). @return list<string> */
    public static function words(string $query): array
    {
        $clean = preg_replace('/[+\-><()~*"@]+/u', ' ', mb_substr(trim($query), 0, 120)) ?? '';
        $words = preg_split('/\s+/u', trim($clean), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_slice(array_unique($words), 0, 8));
    }

    /**
     * Résultats regroupés : Q/R publiques, articles, sujets du forum.
     *
     * @return array{qa: list<array<string, mixed>>, articles: list<array<string, mixed>>, topics: list<array<string, mixed>>, total: int}
     */
    public static function search(string $query, ?string $locale = null, int $limit = 10): array
    {
        $locale ??= Lang::getLocale();
        $results = ['qa' => [], 'articles' => [], 'topics' => [], 'total' => 0];
        if (self::words($query) === []) {
            return $results;
        }
        [$qaSql, $qaParams] = self::matchClause(['t.title', 't.question_text', 't.answer_html'], $query);
        $results['qa'] = Database::fetchAll(
            'SELECT p.id, p.published_at, p.views, p.audio_media_id, t.title, t.slug, t.question_text, COALESCE(rt.name, rd.name) AS rubrique_name
             FROM qa_publications p JOIN qa_publication_translations t ON t.publication_id = p.id AND t.locale = ?
             JOIN rubriques r ON r.id = p.rubrique_id LEFT JOIN rubrique_translations rt ON rt.rubrique_id = r.id AND rt.locale = ? LEFT JOIN rubrique_translations rd ON rd.rubrique_id = r.id AND rd.locale = ?
             WHERE p.status = ? AND ' . $qaSql . ' ORDER BY p.published_at DESC LIMIT ' . (int) $limit,
            array_merge([$locale, $locale, Lang::getDefault(), 'published'], $qaParams),
        );
        [$artSql, $artParams] = self::matchClause(['t.title', 't.excerpt', 't.content'], $query);
        $results['articles'] = Database::fetchAll(
            'SELECT a.id, a.cover_path, a.published_at, a.reading_minutes, t.title, t.slug, t.excerpt, u.full_name AS author_name, COALESCE(rt.name, rd.name) AS rubrique_name
             FROM articles a JOIN article_translations t ON t.article_id = a.id AND t.locale = ?
             LEFT JOIN users u ON u.id = a.author_id LEFT JOIN rubriques r ON r.id = a.rubrique_id
             LEFT JOIN rubrique_translations rt ON rt.rubrique_id = r.id AND rt.locale = ? LEFT JOIN rubrique_translations rd ON rd.rubrique_id = r.id AND rd.locale = ?
             WHERE a.status = ? AND a.deleted_at IS NULL AND (a.published_at IS NULL OR a.published_at <= UTC_TIMESTAMP()) AND ' . $artSql . ' ORDER BY a.published_at DESC LIMIT ' . (int) $limit,
            array_merge([$locale, $locale, Lang::getDefault(), 'published'], $artParams),
        );
        if (route_exists('forum.topic')) {
            [$topicSql, $topicParams] = self::matchClause(['ft.title', 'ft.body'], $query);
            $results['topics'] = Database::fetchAll(
                "SELECT ft.id, ft.title, ft.slug, ft.replies_count, ft.created_at, ft.locale FROM forum_topics ft
                 WHERE ft.status IN ('open','locked') AND ft.deleted_at IS NULL AND ft.locale = ? AND " . $topicSql . ' ORDER BY ft.created_at DESC LIMIT ' . (int) $limit,
                array_merge([$locale], $topicParams),
            );
        }
        $results['total'] = count($results['qa']) + count($results['articles']) + count($results['topics']);

        return $results;
    }
}
