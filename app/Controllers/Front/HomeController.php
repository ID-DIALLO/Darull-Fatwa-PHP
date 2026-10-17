<?php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Response;
use App\Models\Banner;
use App\Models\DailyQuote;
use App\Models\HomeSection;
use App\Models\Rubrique;
use App\Models\ScholarProfile;
use App\Services\SiteStats;

/**
 * Page d'accueil (§6) : sections activables et ordonnées depuis l'administration, alimentées par la base.
 * Les sections sans contenu (Q/R, articles, forum, idées tant que ces modules n'existent pas) sont masquées.
 */
final class HomeController extends Controller
{
    /** « / » : redirection vers la langue du visiteur (§7.1). */
    public function root(Request $request): Response
    {
        $locale = Lang::detect($request, Auth::user()['locale'] ?? null);

        return $this->redirect(route('home', [], $locale))
            ->header('Vary', 'Accept-Language, Cookie')
            ->header('Cache-Control', 'no-store');
    }

    public function index(Request $request): Response
    {
        $locale = Lang::getLocale();
        $sections = HomeSection::activeCodes();
        $data = [
            'sections'  => $sections,
            'banners'   => in_array('hero', $sections, true) ? Banner::active($locale) : [],
            'rubriques' => Rubrique::allActive($locale),
            'quote'     => in_array('daily_quote', $sections, true) ? DailyQuote::today($locale) : null,
            'scholars'  => in_array('scholars', $sections, true) ? ScholarProfile::publicList($locale, 8) : [],
            'stats'     => in_array('stats', $sections, true) ? SiteStats::home() : null,
            'latestQa'  => in_array('latest_qa', $sections, true) ? $this->latestQa($locale) : [],
            'articles'  => in_array('latest_articles', $sections, true) ? $this->latestArticles($locale) : [],
            'topics'    => in_array('forum_ideas', $sections, true) ? $this->latestTopics() : [],
            'ideas'     => in_array('forum_ideas', $sections, true) ? $this->topIdeas() : [],
            'checks'    => config('app.debug') ? $this->environmentChecks() : [],
        ];

        return $this->view('front/home', $data);
    }

    /** @return list<array<string, mixed>> */
    private function latestQa(string $locale): array
    {
        if (!route_exists('qa.show')) {
            return [];
        }

        return Database::fetchAll(
            'SELECT p.id, p.published_at, p.audio_media_id, t.title, t.slug, t.question_text,
                    COALESCE(rt.name, rd.name) AS rubrique_name, r.icon AS rubrique_icon
             FROM qa_publications p
             JOIN qa_publication_translations t ON t.publication_id = p.id AND t.locale = ?
             JOIN rubriques r ON r.id = p.rubrique_id
             LEFT JOIN rubrique_translations rt ON rt.rubrique_id = r.id AND rt.locale = ?
             LEFT JOIN rubrique_translations rd ON rd.rubrique_id = r.id AND rd.locale = ?
             WHERE p.status = ? ORDER BY p.published_at DESC LIMIT 6',
            [$locale, $locale, Lang::getDefault(), 'published'],
        );
    }

    /** @return list<array<string, mixed>> */
    private function latestArticles(string $locale): array
    {
        if (!route_exists('blog.show')) {
            return [];
        }

        return Database::fetchAll(
            'SELECT a.id, a.cover_path, a.published_at, a.reading_minutes, t.title, t.slug, t.excerpt,
                    u.full_name AS author_name, COALESCE(rt.name, rd.name) AS rubrique_name
             FROM articles a
             JOIN article_translations t ON t.article_id = a.id AND t.locale = ?
             LEFT JOIN users u ON u.id = a.author_id
             LEFT JOIN rubriques r ON r.id = a.rubrique_id
             LEFT JOIN rubrique_translations rt ON rt.rubrique_id = r.id AND rt.locale = ?
             LEFT JOIN rubrique_translations rd ON rd.rubrique_id = r.id AND rd.locale = ?
             WHERE a.status = ? AND a.deleted_at IS NULL ORDER BY a.published_at DESC LIMIT 3',
            [$locale, $locale, Lang::getDefault(), 'published'],
        );
    }

    /** @return list<array<string, mixed>> */
    private function latestTopics(): array
    {
        if (!route_exists('forum.topic')) {
            return [];
        }

        return Database::fetchAll(
            "SELECT id, title, slug, replies_count, last_post_at, created_at FROM forum_topics
             WHERE status IN ('open','locked') AND deleted_at IS NULL ORDER BY COALESCE(last_post_at, created_at) DESC LIMIT 5",
        );
    }

    /** @return list<array<string, mixed>> */
    private function topIdeas(): array
    {
        if (!route_exists('ideas.show')) {
            return [];
        }

        return Database::fetchAll(
            "SELECT id, title, votes_count, status FROM ideas
             WHERE status NOT IN ('pending','declined') AND deleted_at IS NULL ORDER BY votes_count DESC, created_at DESC LIMIT 5",
        );
    }

    /**
     * Vérifications techniques affichées uniquement en mode debug (aide à l'installation).
     *
     * @return list<array{label: string, ok: bool, detail: string}>
     */
    private function environmentChecks(): array
    {
        $checks = [['label' => 'PHP ≥ 8.2', 'ok' => PHP_VERSION_ID >= 80200, 'detail' => PHP_VERSION]];
        foreach (['pdo_mysql', 'mbstring', 'intl', 'fileinfo', 'openssl', 'json', 'curl', 'zip', 'gd'] as $ext) {
            $checks[] = ['label' => 'ext-' . $ext, 'ok' => extension_loaded($ext), 'detail' => extension_loaded($ext) ? 'OK' : '—'];
        }
        try {
            $version = (string) Database::value('SELECT VERSION()');
            $tables = (int) Database::value('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()');
            $checks[] = ['label' => __('common.test.database'), 'ok' => $tables > 0, 'detail' => $version . ' · ' . $tables . ' tables'];
        } catch (\Throwable) {
            $checks[] = ['label' => __('common.test.database'), 'ok' => false, 'detail' => __('common.test.database_missing')];
        }
        $checks[] = ['label' => 'storage/ ' . __('common.test.writable'), 'ok' => is_writable(storage_path('logs')), 'detail' => storage_path()];

        return $checks;
    }
}
