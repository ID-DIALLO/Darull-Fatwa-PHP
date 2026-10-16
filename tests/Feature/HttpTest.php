<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\App;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use PHPUnit\Framework\TestCase;

/**
 * Critères d'acceptation de la Phase 0 vérifiés de bout en bout (sans navigateur).
 */
final class HttpTest extends TestCase
{
    private App $app;

    protected function setUp(): void
    {
        $this->app = App::create(dirname(__DIR__, 2));
        Session::reset();
    }

    protected function tearDown(): void
    {
        restore_error_handler();
    }

    public function testRootRedirectsToFrenchByDefault(): void
    {
        $response = $this->get('/');

        self::assertSame(302, $response->status());
        self::assertSame('/fr', $response->getHeader('Location'));
    }

    public function testRootRedirectsToBrowserLanguage(): void
    {
        self::assertSame('/ar', $this->get('/', ['HTTP_ACCEPT_LANGUAGE' => 'ar-ML,ar;q=0.9'])->getHeader('Location'));
        self::assertSame('/en', $this->get('/', ['HTTP_ACCEPT_LANGUAGE' => 'en-GB,en;q=0.8'])->getHeader('Location'));
    }

    public function testLanguageCookieWinsOverBrowser(): void
    {
        $response = $this->app->handle(Request::create('/', 'GET', [], ['HTTP_ACCEPT_LANGUAGE' => 'ar'], ['lang' => 'en']));

        self::assertSame('/en', $response->getHeader('Location'));
    }

    public function testFrenchPageIsLeftToRight(): void
    {
        $html = $this->get('/fr')->body();

        self::assertStringContainsString('<html lang="fr" dir="ltr">', $html);
        self::assertStringContainsString('bootstrap.min.css', $html);
        self::assertStringContainsString('darul-fatwa-logo.svg', $html);
        self::assertStringContainsString('joumada', $html); // date hégirienne
    }

    public function testArabicPageIsRightToLeftWithArabicFonts(): void
    {
        $response = $this->get('/ar');
        $html = $response->body();

        self::assertSame(200, $response->status());
        self::assertStringContainsString('<html lang="ar" dir="rtl">', $html);
        self::assertStringContainsString('bootstrap.rtl.min.css', $html);
        self::assertStringContainsString('assets/css/rtl.css', $html);
        self::assertStringContainsString('<title>دار الفتوى | أجوبة إسلامية موثوقة</title>', $html);
        self::assertStringContainsString('جمادى', $html);
    }

    public function testVisitedLanguageIsRememberedInCookie(): void
    {
        $cookies = $this->get('/en')->cookies();

        self::assertSame('lang', $cookies[0]['name'] ?? null);
        self::assertSame('en', $cookies[0]['value'] ?? null);
    }

    public function testHreflangAlternatesArePresent(): void
    {
        $html = $this->get('/fr')->body();

        foreach (['fr', 'ar', 'en', 'x-default'] as $code) {
            self::assertStringContainsString('hreflang="' . $code . '"', $html);
        }
    }

    public function testUnknownUrlGives404InTheRightLanguage(): void
    {
        $fr = $this->get('/fr/page-inexistante');
        $ar = $this->get('/ar/page-inexistante');
        $en = $this->get('/en/missing-page');

        self::assertSame(404, $fr->status());
        self::assertStringContainsString('Page introuvable', $fr->body());
        self::assertSame(404, $ar->status());
        self::assertStringContainsString('dir="rtl"', $ar->body());
        self::assertStringContainsString('الصفحة غير موجودة', $ar->body());
        self::assertStringContainsString('Page not found', $en->body());
        self::assertStringContainsString('noindex', $fr->body());
    }

    public function testUnknownUrlWithoutLanguageUsesBrowserLanguage(): void
    {
        $response = $this->get('/nimporte-quoi', ['HTTP_ACCEPT_LANGUAGE' => 'ar']);

        self::assertSame(404, $response->status());
        self::assertStringContainsString('الصفحة غير موجودة', $response->body());
    }

    public function testApiErrorsAreJson(): void
    {
        $response = $this->get('/api/inexistant');

        self::assertSame(404, $response->status());
        self::assertStringContainsString('application/json', (string) $response->getHeader('Content-Type'));
    }

    public function testPostWithoutCsrfTokenGives419(): void
    {
        $this->app->router()->localized('POST', ['fr' => '/test-formulaire', 'en' => '/test-form'], static fn () => 'envoyé', 'test.form');

        $refused = $this->app->handle(Request::create('/fr/test-formulaire', 'POST', ['nom' => 'Awa']));
        self::assertSame(419, $refused->status());
        self::assertStringContainsString('Session expirée', $refused->body());

        $accepted = $this->app->handle(Request::create('/fr/test-formulaire', 'POST', ['_token' => Csrf::token()]));
        self::assertSame(200, $accepted->status());
        self::assertSame('envoyé', $accepted->body());

        $header = $this->app->handle(Request::create('/en/test-form', 'POST', [], ['HTTP_X_CSRF_TOKEN' => Csrf::token()]));
        self::assertSame(200, $header->status());
    }

    public function testWrongMethodGives405(): void
    {
        $response = $this->app->handle(Request::create('/fr', 'POST', ['_token' => Csrf::token()]));

        self::assertSame(405, $response->status());
        self::assertSame('GET, HEAD', $response->getHeader('Allow'));
    }

    public function testSecurityHeaders(): void
    {
        foreach ([$this->get('/fr'), $this->get('/fr/inexistante')] as $response) {
            self::assertSame('nosniff', $response->getHeader('X-Content-Type-Options'));
            self::assertSame('SAMEORIGIN', $response->getHeader('X-Frame-Options'));
            self::assertSame('strict-origin-when-cross-origin', $response->getHeader('Referrer-Policy'));
            self::assertSame('microphone=(self), camera=(), geolocation=()', $response->getHeader('Permissions-Policy'));
            self::assertStringContainsString("default-src 'self'", (string) $response->getHeader('Content-Security-Policy'));
        }
    }

    public function testHstsOnlyOverHttps(): void
    {
        self::assertNull($this->get('/fr')->getHeader('Strict-Transport-Security'));
        self::assertNotNull($this->get('/fr', ['HTTPS' => 'on'])->getHeader('Strict-Transport-Security'));
    }

    public function testMaintenanceModeGivesTranslated503(): void
    {
        $flag = dirname(__DIR__, 2) . '/storage/maintenance.flag';
        touch($flag);
        try {
            $response = $this->get('/en');
            self::assertSame(503, $response->status());
            self::assertSame('3600', $response->getHeader('Retry-After'));
            self::assertStringContainsString('Under maintenance', $response->body());
        } finally {
            unlink($flag);
        }
    }

    public function testServerErrorsAreGenericWithoutDebug(): void
    {
        $this->app->router()->get('/fr/boom', static fn () => throw new \RuntimeException('secret interne'));

        $response = $this->get('/fr/boom');

        self::assertSame(500, $response->status());
        self::assertStringContainsString('Erreur du serveur', $response->body());
        self::assertStringNotContainsString('secret interne', $response->body());
    }

    /** @param array<string, string> $server */
    private function get(string $uri, array $server = []): Response
    {
        return $this->app->handle(Request::create($uri, 'GET', [], $server));
    }
}
