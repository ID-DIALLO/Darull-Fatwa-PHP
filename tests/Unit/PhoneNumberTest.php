<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PhoneNumberTest extends TestCase
{
    /** @return iterable<string, array{string|null, string|null}> */
    public static function inputs(): iterable
    {
        yield 'vide' => ['', null];
        yield 'null' => [null, null];
        yield 'local malien' => ['76 12 34 56', '+22376123456'];
        yield 'local avec points' => ['76.12.34.56', '+22376123456'];
        yield 'international avec espaces' => ['+223 76 12 34 56', '+22376123456'];
        yield 'double zéro' => ['0033 6 12 34 56 78', '+33612345678'];
        yield 'sans plus avec indicatif' => ['22376123456', '+22376123456'];
        yield 'chiffres arabes' => ['٧٦١٢٣٤٥٦', '+22376123456'];
        yield 'parenthèses' => ['+33 (0)6 12 34 56 78', '+330612345678'];
    }

    #[DataProvider('inputs')]
    public function testNormalize(?string $input, ?string $expected): void
    {
        self::assertSame($expected, PhoneNumber::normalize($input));
    }

    public function testValidityAndFormatting(): void
    {
        self::assertTrue(PhoneNumber::isValid('+22376123456'));
        self::assertFalse(PhoneNumber::isValid('+330612345678' . '00000'));
        self::assertFalse(PhoneNumber::isValid('76123456'));
        self::assertSame('+223 76 12 34 56', PhoneNumber::format('+22376123456'));
        self::assertSame('+33612345678', PhoneNumber::format('+33612345678'));
        self::assertSame('22376123456', PhoneNumber::forWhatsApp('+223 76 12 34 56'));
    }
}
