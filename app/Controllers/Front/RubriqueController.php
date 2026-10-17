<?php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\Rubrique;
use App\Models\ScholarProfile;

/**
 * Rubriques (§3) : liste et détail avec les Q/R publiées et les articles de la rubrique.
 */
final class RubriqueController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('front/rubriques/index', ['rubriques' => Rubrique::allActive()]);
    }

    public function show(Request $request, string $slug): Response
    {
        $rubrique = Rubrique::findBySlug($slug);
        if ($rubrique === null) {
            throw new HttpException(404);
        }
        $locale = Lang::getLocale();

        // Sélecteur de langue : le slug de la rubrique dans chaque langue (repli : liste des rubriques)
        $slugs = Rubrique::slugs((int) $rubrique['id']);
        $alternates = [];
        foreach (Lang::locales() as $code) {
            $alternates[$code] = isset($slugs[$code]) ? route('rubriques.show', ['slug' => $slugs[$code]], $code) : route('rubriques.index', [], $code);
        }
        View::share('alternates', $alternates);

        $scholars = array_values(array_filter(
            ScholarProfile::publicList($locale),
            static fn (array $s): bool => in_array((int) $rubrique['id'], array_map(static fn (array $r): int => (int) $r['id'], $s['rubriques']), true),
        ));

        return $this->view('front/rubriques/show', [
            'rubrique' => $rubrique,
            'children' => Rubrique::children((int) $rubrique['id'], $locale),
            'qa'       => $this->publishedQa((int) $rubrique['id'], $locale),
            'articles' => $this->publishedArticles((int) $rubrique['id'], $locale),
            'scholars' => $scholars,
            'fallbackLocale' => (int) $rubrique['is_fallback'] === 1 ? Lang::getDefault() : null,
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function publishedQa(int $rubriqueId, string $locale): array
    {
        if (!route_exists('qa.show')) {
            return [];
        }

        return Database::fetchAll(
            'SELECT p.id, p.published_at, p.audio_media_id, p.views, t.title, t.slug, t.question_text
             FROM qa_publications p JOIN qa_publication_translations t ON t.publication_id = p.id AND t.locale = ?
             WHERE p.rubrique_id = ? AND p.status = ? ORDER BY p.published_at DESC LIMIT 12',
            [$locale, $rubriqueId, 'published'],
        );
    }

    /** @return list<array<string, mixed>> */
    private function publishedArticles(int $rubriqueId, string $locale): array
    {
        if (!route_exists('blog.show')) {
            return [];
        }

        return Database::fetchAll(
            'SELECT a.id, a.cover_path, a.published_at, a.reading_minutes, t.title, t.slug, t.excerpt, u.full_name AS author_name
             FROM articles a JOIN article_translations t ON t.article_id = a.id AND t.locale = ?
             LEFT JOIN users u ON u.id = a.author_id
             WHERE a.rubrique_id = ? AND a.status = ? AND a.deleted_at IS NULL ORDER BY a.published_at DESC LIMIT 6',
            [$locale, $rubriqueId, 'published'],
        );
    }
}
