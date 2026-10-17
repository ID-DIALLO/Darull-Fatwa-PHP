<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Database;
use App\Services\SitemapService;
use Tests\Support\DatabaseTestCase;

/**
 * Phase 8 : référencement (sitemaps par langue, robots.txt, données structurées, Open Graph), en-têtes de sécurité,
 * PWA (manifest, service worker, page hors-ligne), session courte de l'équipe, assets construits.
 */
final class QualityTest extends DatabaseTestCase
{
    public function testLesSitemapsParLangueListentLesContenusPublicsAvecHreflang(): void
    {
        $scholar = $this->createUser(['email' => 'cheick@example.ml', 'full_name' => 'Cheick Moussa Koné', 'role_id' => 2]);
        Database::execute("INSERT INTO scholar_profiles (user_id, languages, is_public) VALUES (?, 'fr,ar', 1)", [$scholar]);
        $hidden = $this->createUser(['email' => 'cache@example.ml', 'full_name' => 'Professeur Discret', 'role_id' => 2]);
        Database::execute("INSERT INTO scholar_profiles (user_id, languages, is_public) VALUES (?, 'fr', 0)", [$hidden]);

        $index = $this->get('/sitemap.xml');
        self::assertSame(200, $index->status());
        self::assertStringStartsWith('application/xml', (string) $index->getHeader('Content-Type'));
        self::assertStringContainsString('/sitemap-fr.xml', $index->body());
        self::assertStringContainsString('/sitemap-ar.xml', $index->body());
        self::assertStringContainsString('/sitemap-en.xml', $index->body());

        foreach (['fr', 'ar', 'en'] as $locale) {
            $response = $this->get('/sitemap-' . $locale . '.xml');
            self::assertSame(200, $response->status(), $locale);
            $xml = @simplexml_load_string($response->body());
            self::assertNotFalse($xml, 'XML invalide pour ' . $locale);
            self::assertStringContainsString('<loc>' . url('/' . $locale) . '</loc>', $response->body());
            self::assertStringContainsString('hreflang="' . $locale . '"', $response->body());
            self::assertStringContainsString('/' . $locale . '/' . ($locale === 'fr' ? 'professeurs' : 'scholars') . '/' . $scholar . '-cheick-moussa-kone', $response->body());
            self::assertStringNotContainsString('/' . $hidden . '-professeur-discret', $response->body());
            self::assertStringNotContainsString('mon-espace', $response->body());
            self::assertStringNotContainsString('/admin', $response->body());
        }
        // Les rubriques traduites y figurent avec leurs trois adresses
        $fr = $this->get('/sitemap-fr.xml')->body();
        self::assertStringContainsString('/fr/rubriques/coran</loc>', $fr);
        self::assertStringContainsString('hreflang="ar" href="' . url('/ar/topics/'), $fr);
        self::assertSame(404, $this->get('/sitemap-xx.xml')->status());

        // Fichiers régénérés par le cron
        $files = SitemapService::generate();
        self::assertCount(4, $files);
        foreach ($files as $file) {
            self::assertFileExists($file);
        }
    }

    public function testRobotsExclutLesEspacesPrivesEtPointeVersLeSitemap(): void
    {
        $response = $this->get('/robots.txt');
        self::assertSame(200, $response->status());
        self::assertStringStartsWith('text/plain', (string) $response->getHeader('Content-Type'));
        foreach (['Disallow: /admin', 'Disallow: /api/', 'Disallow: /media/', 'Disallow: /fr/mon-espace', 'Disallow: /ar/my-account', 'Disallow: /en/scholar', 'Disallow: /fr/professeur', 'Sitemap: ' . url('/sitemap.xml')] as $line) {
            self::assertStringContainsString($line, $response->body());
        }
    }

    public function testDonneesStructureesEtMetadonneesDePartage(): void
    {
        $home = $this->get('/fr')->body();
        self::assertStringContainsString('"@type":"Organization"', $home);
        self::assertStringContainsString('"@type":"WebSite"', $home);
        self::assertStringContainsString('"@type":"SearchAction"', $home);
        self::assertStringContainsString('<meta property="og:image" content="' . url('/assets/img/og-default.png') . '">', $home);
        self::assertStringContainsString('<meta property="og:image:width" content="1200">', $home);
        self::assertStringContainsString('<meta name="twitter:title"', $home);
        self::assertStringContainsString('<link rel="canonical" href="' . url('/fr') . '">', $home);
        self::assertStringContainsString('hreflang="x-default"', $home);
        self::assertFileExists(public_path('assets/img/og-default.png'));
        [$width, $height] = getimagesize(public_path('assets/img/og-default.png')) ?: [0, 0];
        self::assertSame([1200, 630], [$width, $height]);

        // Fil d'Ariane → BreadcrumbList sur les pages internes, dans les trois langues
        foreach (['/fr/blog', '/ar/blog', '/en/topics'] as $uri) {
            $body = $this->get($uri)->body();
            self::assertStringContainsString('"@type":"BreadcrumbList"', $body, $uri);
            self::assertStringContainsString('"position":2', $body, $uri);
        }

        // Fiche professeur → Person
        $scholar = $this->createUser(['email' => 'cheick@example.ml', 'full_name' => 'Cheick Moussa Koné', 'role_id' => 2]);
        Database::execute("INSERT INTO scholar_profiles (user_id, languages, is_public) VALUES (?, 'fr,ar', 1)", [$scholar]);
        Database::execute("INSERT INTO scholar_translations (user_id, locale, title, bio) VALUES (?, 'fr', 'Cheick', 'Enseigne le fiqh à Bamako.')", [$scholar]);
        $page = $this->get('/fr/professeurs/' . $scholar . '-cheick-moussa-kone')->body();
        self::assertStringContainsString('"@type":"Person"', $page);
        self::assertStringContainsString('"honorificPrefix":"Cheick"', $page);
        // Un « < » dans une donnée ne peut pas fermer la balise script
        self::assertStringNotContainsString('</script><', json_ld(['@type' => 'Thing', 'name' => '</script><b>x']));
    }

    public function testEnTetesDeSecuriteSurToutesLesReponses(): void
    {
        foreach ([$this->get('/fr'), $this->get('/fr/page-inexistante'), $this->get('/robots.txt')] as $response) {
            $csp = (string) $response->getHeader('Content-Security-Policy');
            self::assertStringContainsString("default-src 'self'", $csp);
            preg_match("/script-src ([^;]+)/", $csp, $m);
            self::assertStringNotContainsString("'unsafe-inline'", $m[1] ?? '', 'script-src ne doit pas autoriser le JS en ligne');
            self::assertSame('nosniff', $response->getHeader('X-Content-Type-Options'));
            self::assertSame('SAMEORIGIN', $response->getHeader('X-Frame-Options'));
            self::assertSame('strict-origin-when-cross-origin', $response->getHeader('Referrer-Policy'));
            self::assertStringContainsString('microphone=(self)', (string) $response->getHeader('Permissions-Policy'));
        }
        // HSTS uniquement en HTTPS
        self::assertNull($this->get('/fr')->getHeader('Strict-Transport-Security'));
        self::assertStringContainsString('max-age=31536000', (string) $this->get('/fr', ['HTTPS' => 'on'])->getHeader('Strict-Transport-Security'));
    }

    public function testPwaManifestServiceWorkerEtPageHorsLigne(): void
    {
        $manifest = json_decode((string) file_get_contents(public_path('manifest.webmanifest')), true);
        self::assertSame('standalone', $manifest['display']);
        self::assertSame('#0B5D45', $manifest['theme_color']);
        self::assertNotEmpty($manifest['icons']);
        foreach ($manifest['icons'] as $icon) {
            self::assertFileExists(public_path(ltrim((string) $icon['src'], '/')));
        }
        $sw = (string) file_get_contents(public_path('sw.js'));
        self::assertStringContainsString("'/offline'", $sw);
        self::assertStringContainsString('mon-espace', $sw, 'les pages privées ne doivent jamais être mises en cache');
        self::assertStringContainsString('/sw.js', (string) file_get_contents(public_path('assets/js/app.js')));

        $offline = $this->get('/offline');
        self::assertSame(200, $offline->status());
        foreach (['Vous êtes hors ligne', 'أنت غير متصل بالإنترنت', 'You are offline'] as $text) {
            self::assertStringContainsString($text, $offline->body());
        }
        self::assertStringContainsString('<section lang="ar" dir="rtl">', $offline->body());
        self::assertStringNotContainsString('<script>', $offline->body(), 'aucun script en ligne (CSP)');
    }

    public function testLaSessionDeLEquipeEstMarqueePourUneExpirationCourte(): void
    {
        $member = $this->createUser(['email' => 'awa@example.ml']);
        $admin = $this->createUser(['email' => 'admin@example.ml', 'full_name' => 'Admin Principal', 'role_id' => 4]);
        $this->login($member);
        self::assertFalse(\App\Core\Session::get('auth_staff'));
        \App\Core\Session::reset();
        \App\Core\Auth::reset();
        $this->login($admin);
        self::assertTrue(\App\Core\Session::get('auth_staff'));
    }

    public function testLArchiveHebdomadaireDesAudiosContientLesEnregistrements(): void
    {
        $dir = storage_path('audio/2026/10');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($dir . '/test-archive.mp3', str_repeat('a', 2048));
        foreach (glob(storage_path('backups/audio-*.zip')) ?: [] as $old) {
            unlink($old);
        }
        $name = \App\Services\Admin\BackupService::archiveAudio();
        self::assertNotNull($name);
        self::assertStringStartsWith('audio-', (string) $name);
        $zip = new \ZipArchive();
        self::assertTrue($zip->open(storage_path('backups/' . $name)));
        self::assertNotFalse($zip->locateName('2026/10/test-archive.mp3'));
        $zip->close();
        self::assertSame(1, (int) Database::value("SELECT COUNT(*) FROM activity_logs WHERE action = 'admin.backup.created'"));
        unlink($dir . '/test-archive.mp3');
        unlink(storage_path('backups/' . $name));
    }

    public function testLesAssetsConstruitsSontUtilisesSeulementQuandDemande(): void
    {
        self::assertFileExists(public_path('assets/dist/manifest.json'));
        $manifest = json_decode((string) file_get_contents(public_path('assets/dist/manifest.json')), true);
        self::assertSame('assets/dist/app.css', $manifest['assets/dist/app.css']);
        self::assertSame('assets/dist/js/app.js', $manifest['assets/js/app.js']);
        foreach ($manifest as $built) {
            self::assertFileExists(public_path($built));
        }
        // En test (APP_ENV ≠ production, ASSETS_BUILD non défini) : les sources sont servies
        self::assertStringStartsWith('/assets/css/app.css?v=', asset('assets/css/app.css'));
        self::assertStringContainsString('/assets/css/variables.css', $this->get('/fr')->body());
        // La feuille groupée reste du CSS valide et contient les variables de la charte
        $css = (string) file_get_contents(public_path('assets/dist/app.css'));
        self::assertStringContainsString('--df-green', $css);
        self::assertStringContainsString("url('../fonts/", $css);
        self::assertStringNotContainsString("\n\n", $css);
    }
}
