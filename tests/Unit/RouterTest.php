<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\HttpException;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Response;
use App\Core\Route;
use App\Core\Router;
use App\Middleware\Middleware;
use Closure;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        Lang::init(dirname(__DIR__) . '/Fixtures/lang', ['fr', 'ar', 'en'], 'fr');
        $this->router = new Router(['fr', 'ar', 'en'], ['tag' => TagMiddleware::class]);
    }

    public function testStaticRouteMatches(): void
    {
        $this->router->get('/', static fn () => 'racine')->name('root');

        $response = $this->router->dispatch(Request::create('/'));

        self::assertSame('racine', $response->body());
    }

    public function testParametersAreCapturedAndPassedToTheAction(): void
    {
        $this->router->get('/fr/blog/{slug}', static fn (Request $r, string $slug) => 'article ' . $slug);

        self::assertSame('article mon-article', $this->router->dispatch(Request::create('/fr/blog/mon-article'))->body());
    }

    public function testConstrainedParametersAndCompositeSegment(): void
    {
        $this->router->get('/fr/forum/sujet/{id:\d+}-{slug}', static fn (Request $r, string $id, string $slug) => $id . '|' . $slug);

        self::assertSame('42|la-priere-du-vendredi', $this->router->dispatch(Request::create('/fr/forum/sujet/42-la-priere-du-vendredi'))->body());

        $this->expectException(HttpException::class);
        $this->router->dispatch(Request::create('/fr/forum/sujet/abc-titre'));
    }

    public function testArabicSlugIsMatchedAndUrlEncoded(): void
    {
        $this->router->localized('GET', ['fr' => '/rubriques/{slug}', 'en' => '/topics/{slug}'], static fn (Request $r, string $slug) => $slug, 'rubriques.show');

        $request = Request::create('/ar/topics/' . rawurlencode('التوحيد'));
        self::assertSame('التوحيد', $this->router->dispatch($request)->body());
        self::assertSame('/ar/topics/' . rawurlencode('التوحيد'), $this->router->url('rubriques.show', ['slug' => 'التوحيد'], 'ar'));
    }

    public function testLocalizedRoutesUseFrenchSegmentsForFrAndEnglishForEnAndAr(): void
    {
        $this->router->localized('GET', ['fr' => '/poser-une-question', 'en' => '/ask'], static fn () => 'ask', 'questions.create');

        self::assertSame('/fr/poser-une-question', $this->router->url('questions.create', [], 'fr'));
        self::assertSame('/en/ask', $this->router->url('questions.create', [], 'en'));
        self::assertSame('/ar/ask', $this->router->url('questions.create', [], 'ar'));

        [$route] = $this->router->match(Request::create('/ar/ask'));
        self::assertSame('ar', $route->locale);
    }

    public function testUrlUsesCurrentLocaleByDefault(): void
    {
        $this->router->localized('GET', '', static fn () => 'home', 'home');

        Lang::setLocale('en');
        self::assertSame('/en', $this->router->url('home'));
        Lang::setLocale('fr');
        self::assertSame('/fr', $this->router->url('home'));
    }

    public function testExtraParametersBecomeQueryString(): void
    {
        $this->router->get('/fr/recherche', static fn () => '')->name('search');

        self::assertSame('/fr/recherche?q=pri%C3%A8re&page=2', $this->router->url('search', ['q' => 'prière', 'page' => 2]));
    }

    public function testMissingRouteParameterThrows(): void
    {
        $this->router->get('/fr/blog/{slug}', static fn () => '')->name('blog.show');

        $this->expectException(\InvalidArgumentException::class);
        $this->router->url('blog.show');
    }

    public function testUnknownRouteNameThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->router->url('inexistante');
    }

    public function testUnknownPathGives404(): void
    {
        $this->router->get('/fr', static fn () => '');

        try {
            $this->router->dispatch(Request::create('/fr/nexiste-pas'));
            self::fail('Une exception 404 était attendue.');
        } catch (HttpException $e) {
            self::assertSame(404, $e->getStatus());
        }
    }

    public function testWrongMethodGives405WithAllowHeader(): void
    {
        $this->router->post('/fr/contact', static fn () => '');

        try {
            $this->router->dispatch(Request::create('/fr/contact'));
            self::fail('Une exception 405 était attendue.');
        } catch (HttpException $e) {
            self::assertSame(405, $e->getStatus());
            self::assertSame('POST', $e->getHeaders()['Allow']);
        }
    }

    public function testHeadRequestsMatchGetRoutes(): void
    {
        $this->router->get('/fr', static fn () => 'ok');

        self::assertSame(200, $this->router->dispatch(Request::create('/fr', 'HEAD'))->status());
    }

    public function testMethodOverrideFieldForDelete(): void
    {
        $this->router->delete('/api/audio/{uuid}', static fn (Request $r, string $uuid) => 'supprimé ' . $uuid);

        $request = Request::create('/api/audio/abc', 'POST', ['_method' => 'DELETE']);
        self::assertSame('supprimé abc', $this->router->dispatch($request)->body());
    }

    public function testTrailingSlashIsIgnored(): void
    {
        $this->router->get('/fr/blog', static fn () => 'blog');

        self::assertSame('blog', $this->router->dispatch(Request::create('/fr/blog/'))->body());
    }

    public function testGroupsApplyPrefixAndMiddleware(): void
    {
        $this->router->group(['prefix' => 'admin', 'middleware' => 'tag:groupe'], function (Router $r): void {
            $r->get('/questions', static fn () => 'liste')->name('admin.questions.index')->middleware('tag:route');
        });

        self::assertSame('/admin/questions', $this->router->url('admin.questions.index'));
        $response = $this->router->dispatch(Request::create('/admin/questions'));
        self::assertSame('liste', $response->body());
        // Ordre d'exécution : middleware du groupe, puis celui de la route
        self::assertSame('groupe,route', $response->getHeader('X-Tags'));
    }

    public function testGlobalMiddlewareRunsEvenOn404(): void
    {
        $this->expectException(HttpException::class);
        try {
            $this->router->dispatch(Request::create('/inconnu'), [ThrowingMiddleware::class]);
        } catch (\LogicException $e) {
            self::assertSame('middleware global exécuté', $e->getMessage());
            throw new HttpException(404);
        }
    }

    public function testArrayAndStringResultsAreConverted(): void
    {
        $this->router->get('/api/ping', static fn () => ['pong' => true]);

        $response = $this->router->dispatch(Request::create('/api/ping'));
        self::assertSame('{"pong":true}', $response->body());
        self::assertStringContainsString('application/json', (string) $response->getHeader('Content-Type'));
    }

    public function testAlternatesGiveTheSamePageInEachLanguage(): void
    {
        $this->router->localized('GET', '', static fn () => '', 'home');
        $this->router->localized('GET', ['fr' => '/rubriques/{slug}', 'en' => '/topics/{slug}'], static fn () => '', 'rubriques.show');

        $request = Request::create('/fr/rubriques/islam');
        $this->router->dispatch($request);

        self::assertSame(
            ['fr' => '/fr/rubriques/islam', 'ar' => '/ar/topics/islam', 'en' => '/en/topics/islam'],
            $this->router->alternates($request),
        );
    }

    public function testRouteCompileEscapesLiteralCharacters(): void
    {
        [$regex] = Route::compile('/sitemap-{lang}.xml');

        self::assertMatchesRegularExpression($regex, '/sitemap-fr.xml');
        self::assertDoesNotMatchRegularExpression($regex, '/sitemap-frXxml');
    }
}

/** Middleware de test : ajoute son argument à l'en-tête X-Tags. */
final class TagMiddleware implements Middleware
{
    public function handle(Request $request, Closure $next, string ...$args): Response
    {
        $tags = (array) $request->attribute('tags', []);
        $tags[] = $args[0] ?? '';
        $request->setAttribute('tags', $tags);
        $response = $next($request);

        return $response->header('X-Tags', implode(',', (array) $request->attribute('tags')));
    }
}

final class ThrowingMiddleware implements Middleware
{
    public function handle(Request $request, Closure $next, string ...$args): Response
    {
        throw new \LogicException('middleware global exécuté');
    }
}
