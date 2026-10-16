<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = (string) tempnam(sys_get_temp_dir(), 'dfr');
        file_put_contents($this->file, '0123456789');
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
    }

    /** @return iterable<string, array{string, array{0: int, 1: int}|null}> */
    public static function ranges(): iterable
    {
        yield 'début et fin' => ['bytes=2-5', [2, 5]];
        yield 'jusqu\'à la fin' => ['bytes=7-', [7, 9]];
        yield 'derniers octets' => ['bytes=-3', [7, 9]];
        yield 'fin trop grande' => ['bytes=5-999', [5, 9]];
        yield 'début hors fichier' => ['bytes=20-', null];
        yield 'inversée' => ['bytes=5-2', null];
        yield 'syntaxe invalide' => ['octets=1-2', null];
        yield 'vide' => ['bytes=-', null];
    }

    /** @param array{0: int, 1: int}|null $expected */
    #[DataProvider('ranges')]
    public function testParseRange(string $header, ?array $expected): void
    {
        self::assertSame($expected, Response::parseRange($header, 10));
    }

    public function testFullFileResponse(): void
    {
        $response = Response::file($this->file, 'audio/mpeg');

        self::assertSame(200, $response->status());
        self::assertSame('10', $response->getHeader('Content-Length'));
        self::assertSame('bytes', $response->getHeader('Accept-Ranges'));
        self::assertSame('0123456789', $this->captureOutput($response));
    }

    public function testPartialContentWithRange(): void
    {
        $request = Request::create('/media/audio/x', 'GET', [], ['HTTP_RANGE' => 'bytes=2-5']);
        $response = Response::file($this->file, 'audio/mpeg', $request);

        self::assertSame(206, $response->status());
        self::assertSame('bytes 2-5/10', $response->getHeader('Content-Range'));
        self::assertSame('4', $response->getHeader('Content-Length'));
        self::assertSame('2345', $this->captureOutput($response));
    }

    public function testUnsatisfiableRangeGives416(): void
    {
        $request = Request::create('/media/audio/x', 'GET', [], ['HTTP_RANGE' => 'bytes=50-60']);
        $response = Response::file($this->file, 'audio/mpeg', $request);

        self::assertSame(416, $response->status());
        self::assertSame('bytes */10', $response->getHeader('Content-Range'));
    }

    public function testMissingFileGives404(): void
    {
        $this->expectException(HttpException::class);
        Response::file('/chemin/inexistant.mp3', 'audio/mpeg');
    }

    public function testJsonAndRedirect(): void
    {
        self::assertSame('{"message":"دار الفتوى"}', Response::json(['message' => 'دار الفتوى'])->body());

        $redirect = Response::redirect('/fr', 301);
        self::assertSame(301, $redirect->status());
        self::assertSame('/fr', $redirect->getHeader('location'));
    }

    private function captureOutput(Response $response): string
    {
        ob_start();
        $response->send();

        return (string) ob_get_clean();
    }
}
