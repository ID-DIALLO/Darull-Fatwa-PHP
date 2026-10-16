<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Lang;
use App\Core\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LangTest extends TestCase
{
    /** @var list<string> */
    private array $missing = [];

    protected function setUp(): void
    {
        $this->missing = [];
        Lang::init(
            dirname(__DIR__) . '/Fixtures/lang',
            ['fr', 'ar', 'en'],
            'fr',
            ['ar'],
            fn (string $key, string $locale) => $this->missing[] = $locale . ':' . $key,
        );
    }

    public function testTranslationWithReplacement(): void
    {
        self::assertSame('Bonjour Fatoumata', Lang::get('test.hello', ['name' => 'Fatoumata']));

        Lang::setLocale('ar');
        self::assertSame('مرحبًا فاطمة', Lang::get('test.hello', ['name' => 'فاطمة']));
    }

    public function testExplicitLocaleParameter(): void
    {
        self::assertSame('Hello Ali', Lang::get('test.hello', ['name' => 'Ali'], 'en'));
        self::assertSame('fr', Lang::getLocale());
    }

    public function testMissingKeyFallsBackToDefaultLocaleAndIsReported(): void
    {
        Lang::setLocale('en');

        self::assertSame('Seulement en français', Lang::get('test.only_fr'));
        self::assertSame(['en:test.only_fr'], $this->missing);
    }

    public function testKeyMissingEverywhereReturnsTheKey(): void
    {
        self::assertSame('test.inexistante', Lang::get('test.inexistante'));
        self::assertSame('fichier_absent.cle', Lang::get('fichier_absent.cle'));
        self::assertContains('fr:test.inexistante', $this->missing);
    }

    public function testNestedAndFlatKeys(): void
    {
        self::assertSame('Titre imbriqué', Lang::get('test.nested.title'));
        self::assertSame('Clé plate avec point', Lang::get('test.flat.key'));
    }

    public function testLongerPlaceholdersAreReplacedFirst(): void
    {
        self::assertSame('Ali et Ali Konaté', Lang::get('test.replace_order', ['name' => 'Ali', 'name_full' => 'Ali Konaté']));
    }

    public function testPluralForms(): void
    {
        // Français : 0 et 1 au singulier
        self::assertSame('0 question', Lang::choice('test.questions', 0));
        self::assertSame('1 question', Lang::choice('test.questions', 1));
        self::assertSame('3 questions', Lang::choice('test.questions', 3));
        // Trois formes : zéro | un | plusieurs
        self::assertSame('Aucune réponse', Lang::choice('test.answers', 0));
        self::assertSame('Une réponse', Lang::choice('test.answers', 1));
        self::assertSame('5 réponses', Lang::choice('test.answers', 5));
        // Anglais : 0 au pluriel
        self::assertSame('0 questions', Lang::choice('test.questions', 0, [], 'en'));
    }

    public function testDirection(): void
    {
        self::assertSame('ltr', Lang::direction('fr'));
        self::assertSame('ltr', Lang::direction('en'));
        self::assertSame('rtl', Lang::direction('ar'));
        Lang::setLocale('ar');
        self::assertTrue(Lang::isRtl());
    }

    public function testUnsupportedLocaleIsIgnored(): void
    {
        Lang::setLocale('de');

        self::assertSame('fr', Lang::getLocale());
        self::assertFalse(Lang::isSupported('de'));
    }

    /** @return iterable<string, array{string, list<string>}> */
    public static function acceptLanguageHeaders(): iterable
    {
        yield 'arabe saoudien' => ['ar-SA,ar;q=0.9,fr;q=0.8', ['ar', 'fr']];
        yield 'qualité inversée' => ['en;q=0.5, fr;q=0.9', ['fr', 'en']];
        yield 'vide' => ['', []];
        yield 'q=0 exclu' => ['de, fr;q=0', ['de']];
        yield 'joker ignoré' => ['*', []];
    }

    /** @param list<string> $expected */
    #[DataProvider('acceptLanguageHeaders')]
    public function testParseAcceptLanguage(string $header, array $expected): void
    {
        self::assertSame($expected, Lang::parseAcceptLanguage($header));
    }

    public function testDetectionOrderCookieThenProfileThenBrowserThenDefault(): void
    {
        $browserAr = ['HTTP_ACCEPT_LANGUAGE' => 'ar-MA,ar;q=0.9'];

        self::assertSame('en', Lang::detect(Request::create('/', 'GET', [], $browserAr, ['lang' => 'en']), 'fr'));
        self::assertSame('fr', Lang::detect(Request::create('/', 'GET', [], $browserAr), 'fr'));
        self::assertSame('ar', Lang::detect(Request::create('/', 'GET', [], $browserAr)));
        self::assertSame('fr', Lang::detect(Request::create('/', 'GET', [], ['HTTP_ACCEPT_LANGUAGE' => 'de-DE'])));
        // Cookie invalide ignoré
        self::assertSame('ar', Lang::detect(Request::create('/', 'GET', [], $browserAr, ['lang' => 'xx'])));
    }

    /**
     * Règle du projet : toute clé ajoutée en français existe aussi en arabe et en anglais.
     */
    public function testRealLanguageFilesHaveTheSameKeysInAllLocales(): void
    {
        $base = dirname(__DIR__, 2) . '/lang';
        foreach (glob($base . '/fr/*.php') ?: [] as $file) {
            $name = basename($file);
            $fr = self::flatten(require $file);
            foreach (['ar', 'en'] as $locale) {
                $other = $base . '/' . $locale . '/' . $name;
                self::assertFileExists($other);
                $missing = array_diff(array_keys($fr), array_keys(self::flatten(require $other)));
                self::assertSame([], array_values($missing), "Clés absentes de lang/{$locale}/{$name}");
            }
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    private static function flatten(array $data, string $prefix = ''): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $result += self::flatten($value, $prefix . $key . '.');
            } else {
                $result[$prefix . $key] = (string) $value;
            }
        }

        return $result;
    }
}
