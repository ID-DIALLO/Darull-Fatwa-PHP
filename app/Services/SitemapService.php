<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Lang;
use App\Models\ScholarProfile;

/**
 * Plans du site (§13.1) : un index « sitemap.xml » et un fichier par langue (« sitemap-fr.xml »…),
 * écrits dans storage/cache/sitemaps/ par le cron (04:00) ou à la demande quand ils manquent ou datent
 * de plus de 24 h. Seuls les contenus publics y figurent (jamais l'espace membre ni l'administration).
 */
final class SitemapService
{
    public const MAX_AGE = 86400;

    public static function directory(): string
    {
        return storage_path('cache/sitemaps');
    }

    /** Génère tous les fichiers et retourne leurs chemins. @return list<string> */
    public static function generate(): array
    {
        $dir = self::directory();
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $files = [];
        foreach (Lang::locales() as $locale) {
            $file = $dir . '/sitemap-' . $locale . '.xml';
            file_put_contents($file, self::renderLocale($locale), LOCK_EX);
            $files[] = $file;
        }
        $index = $dir . '/sitemap.xml';
        file_put_contents($index, self::renderIndex(), LOCK_EX);
        $files[] = $index;

        return $files;
    }

    /** Contenu d'un fichier (régénéré si absent ou trop ancien). */
    public static function content(string $name): ?string
    {
        if (!preg_match('/^sitemap(-(' . implode('|', array_map('preg_quote', Lang::locales())) . '))?\.xml$/', $name)) {
            return null;
        }
        $file = self::directory() . '/' . $name;
        if (!is_file($file) || filemtime($file) < time() - self::MAX_AGE) {
            self::generate();
        }

        return is_file($file) ? (string) file_get_contents($file) : null;
    }

    public static function renderIndex(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach (Lang::locales() as $locale) {
            $file = self::directory() . '/sitemap-' . $locale . '.xml';
            $xml .= '  <sitemap><loc>' . self::escape(url('/sitemap-' . $locale . '.xml')) . '</loc>'
                . (is_file($file) ? '<lastmod>' . gmdate('Y-m-d', (int) filemtime($file)) . '</lastmod>' : '') . "</sitemap>\n";
        }

        return $xml . "</sitemapindex>\n";
    }

    /** Sitemap d'une langue, avec les liens hreflang vers les autres langues de chaque contenu traduit. */
    public static function renderLocale(string $locale): string
    {
        $previous = Lang::getLocale();
        Lang::setLocale($locale);
        try {
            $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
                . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
            foreach (self::entries($locale) as $entry) {
                $xml .= "  <url>\n    <loc>" . self::escape($entry['loc']) . "</loc>\n";
                if (!empty($entry['lastmod'])) {
                    $xml .= '    <lastmod>' . gmdate('Y-m-d', strtotime((string) $entry['lastmod']) ?: time()) . "</lastmod>\n";
                }
                $xml .= '    <changefreq>' . $entry['changefreq'] . "</changefreq>\n    <priority>" . $entry['priority'] . "</priority>\n";
                foreach ($entry['alternates'] ?? [] as $code => $href) {
                    $xml .= '    <xhtml:link rel="alternate" hreflang="' . $code . '" href="' . self::escape($href) . '"/>' . "\n";
                }
                $xml .= "  </url>\n";
            }

            return $xml . "</urlset>\n";
        } finally {
            Lang::setLocale($previous);
        }
    }

    /**
     * Adresses publiques d'une langue.
     *
     * @return list<array{loc: string, lastmod?: string|null, changefreq: string, priority: string, alternates?: array<string, string>}>
     */
    public static function entries(string $locale): array
    {
        $locales = Lang::locales();
        $entries = [];
        $static = static function (string $route, string $changefreq, string $priority) use (&$entries, $locales, $locale): void {
            if (!route_exists($route)) {
                return;
            }
            $alternates = [];
            foreach ($locales as $code) {
                $alternates[$code] = url(route($route, [], $code));
            }
            $entries[] = ['loc' => url(route($route, [], $locale)), 'changefreq' => $changefreq, 'priority' => $priority, 'alternates' => $alternates];
        };
        $static('home', 'daily', '1.0');
        $static('rubriques.index', 'weekly', '0.8');
        $static('qa.index', 'daily', '0.9');
        $static('blog.index', 'daily', '0.8');
        $static('scholars.index', 'weekly', '0.7');
        $static('forum.index', 'daily', '0.6');
        $static('ideas.index', 'weekly', '0.4');
        $static('contact', 'monthly', '0.4');

        // Contenus traduits : une entrée par traduction existante, hreflang vers les autres traductions
        $translated = static function (string $sql, string $route, string $changefreq, string $priority, string $param = 'slug') use (&$entries, $locale): void {
            $rows = Database::fetchAll($sql);
            $byId = [];
            foreach ($rows as $row) {
                $byId[(string) $row['id']][(string) $row['locale']] = $row;
            }
            foreach ($byId as $translations) {
                if (!isset($translations[$locale])) {
                    continue;
                }
                $alternates = [];
                foreach ($translations as $code => $row) {
                    $alternates[$code] = url(route($route, [$param => $row['slug']], $code));
                }
                $entries[] = ['loc' => $alternates[$locale], 'lastmod' => $translations[$locale]['lastmod'] ?? null, 'changefreq' => $changefreq, 'priority' => $priority, 'alternates' => $alternates];
            }
        };
        $translated("SELECT r.id, t.locale, t.slug, r.updated_at AS lastmod FROM rubriques r JOIN rubrique_translations t ON t.rubrique_id = r.id WHERE r.is_active = 1 ORDER BY r.sort_order", 'rubriques.show', 'weekly', '0.8');
        $translated("SELECT p.id, t.locale, t.slug, p.updated_at AS lastmod FROM pages p JOIN page_translations t ON t.page_id = p.id WHERE p.is_active = 1", 'pages.show', 'monthly', '0.5');
        $translated("SELECT a.id, t.locale, t.slug, COALESCE(a.updated_at, a.published_at) AS lastmod FROM articles a JOIN article_translations t ON t.article_id = a.id WHERE a.status = 'published' AND a.deleted_at IS NULL AND a.published_at <= UTC_TIMESTAMP() ORDER BY a.published_at DESC", 'blog.show', 'monthly', '0.7');
        $translated("SELECT q.id, t.locale, t.slug, COALESCE(q.updated_at, q.published_at) AS lastmod FROM qa_publications q JOIN qa_publication_translations t ON t.publication_id = q.id WHERE q.status = 'published' ORDER BY q.published_at DESC", 'qa.show', 'monthly', '0.8');
        $translated("SELECT c.id, t.locale, t.slug, NULL AS lastmod FROM forum_categories c JOIN forum_category_translations t ON t.category_id = c.id WHERE c.is_active = 1 ORDER BY c.sort_order", 'forum.category', 'daily', '0.5');

        // Contenus non traduits (même URL dans toutes les langues, préfixe différent)
        if (route_exists('scholars.show')) {
            foreach (Database::fetchAll("SELECT u.id, u.full_name, sp.updated_at FROM scholar_profiles sp JOIN users u ON u.id = sp.user_id WHERE sp.is_public = 1 AND u.status = 'active' AND u.deleted_at IS NULL") as $row) {
                $slug = ScholarProfile::slug($row);
                $alternates = [];
                foreach ($locales as $code) {
                    $alternates[$code] = url(route('scholars.show', ['slug' => $slug], $code));
                }
                $entries[] = ['loc' => $alternates[$locale], 'lastmod' => $row['updated_at'], 'changefreq' => 'monthly', 'priority' => '0.6', 'alternates' => $alternates];
            }
        }
        if (route_exists('forum.topic')) {
            foreach (Database::fetchAll("SELECT id, slug, COALESCE(last_post_at, created_at) AS lastmod FROM forum_topics WHERE status IN ('open','locked') AND deleted_at IS NULL ORDER BY id DESC LIMIT 5000") as $row) {
                $alternates = [];
                foreach ($locales as $code) {
                    $alternates[$code] = url(route('forum.topic', ['id' => $row['id'], 'slug' => $row['slug']], $code));
                }
                $entries[] = ['loc' => $alternates[$locale], 'lastmod' => $row['lastmod'], 'changefreq' => 'weekly', 'priority' => '0.4', 'alternates' => $alternates];
            }
        }
        if (route_exists('ideas.show')) {
            foreach (Database::fetchAll("SELECT id, COALESCE(responded_at, created_at) AS lastmod FROM ideas WHERE status IN ('new','under_review','accepted','done','declined') AND deleted_at IS NULL ORDER BY id DESC LIMIT 2000") as $row) {
                $alternates = [];
                foreach ($locales as $code) {
                    $alternates[$code] = url(route('ideas.show', ['id' => $row['id']], $code));
                }
                $entries[] = ['loc' => $alternates[$locale], 'lastmod' => $row['lastmod'], 'changefreq' => 'monthly', 'priority' => '0.3', 'alternates' => $alternates];
            }
        }

        return $entries;
    }

    /** robots.txt (§13.1) : exclusions des espaces privés dans chaque langue + adresse du sitemap. */
    public static function robots(): string
    {
        $lines = ['User-agent: *', 'Disallow: /admin', 'Disallow: /api/', 'Disallow: /media/', 'Disallow: /offline'];
        foreach (Lang::locales() as $code) {
            foreach (['account.dashboard', 'scholar.dashboard', 'auth.login', 'auth.register', 'search'] as $route) {
                if (route_exists($route)) {
                    $lines[] = 'Disallow: ' . route($route, [], $code);
                }
            }
        }
        $lines[] = '';
        $lines[] = 'Sitemap: ' . url('/sitemap.xml');

        return implode("\n", $lines) . "\n";
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
