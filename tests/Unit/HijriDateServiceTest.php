<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\HijriDateService;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class HijriDateServiceTest extends TestCase
{
    private DateTimeImmutable $date;

    protected function setUp(): void
    {
        // 16 octobre 2026 = 5 joumada al oula 1448 (calendrier Umm al-Qura)
        $this->date = new DateTimeImmutable('2026-10-16 12:00:00', new DateTimeZone('UTC'));
    }

    public function testHijriDateInEachLanguage(): void
    {
        self::assertSame('5 joumada al oula 1448 AH', HijriDateService::hijri($this->date, 'fr'));
        self::assertSame('5 جمادى الأولى 1448 هـ', HijriDateService::hijri($this->date, 'ar'));
        self::assertStringContainsString('1448', HijriDateService::hijri($this->date, 'en'));
    }

    public function testArabicUsesWesternDigitsByDefault(): void
    {
        self::assertMatchesRegularExpression('/\d/', HijriDateService::hijri($this->date, 'ar'));
        self::assertStringContainsString('١٤٤٨', HijriDateService::hijri($this->date, 'ar', 'Africa/Bamako', false));
    }

    public function testGregorianDate(): void
    {
        self::assertSame('vendredi 16 octobre 2026', HijriDateService::gregorian($this->date, 'fr'));
        self::assertStringContainsString('16', HijriDateService::gregorian($this->date, 'ar'));
    }
}
