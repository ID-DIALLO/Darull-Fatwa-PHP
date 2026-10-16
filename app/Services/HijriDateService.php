<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeInterface;
use IntlDateFormatter;

/**
 * Dates grégorienne et hégirienne (§6, §7.2) avec IntlDateFormatter
 * et le calendrier « islamic-umalqura » (ex. « 5 joumada al oula 1448 AH »).
 * Chiffres occidentaux (0-9) par défaut, y compris en arabe.
 */
final class HijriDateService
{
    private const INTL_LOCALES = ['fr' => 'fr_FR', 'ar' => 'ar', 'en' => 'en_GB'];

    public static function hijri(DateTimeInterface $date, string $locale, string $timezone = 'Africa/Bamako', bool $latinDigits = true): string
    {
        $icu = (self::INTL_LOCALES[$locale] ?? $locale) . '@calendar=islamic-umalqura' . ($latinDigits ? ';numbers=latn' : '');
        $formatter = new IntlDateFormatter($icu, IntlDateFormatter::LONG, IntlDateFormatter::NONE, $timezone, IntlDateFormatter::TRADITIONAL);

        return (string) $formatter->format($date);
    }

    public static function gregorian(DateTimeInterface $date, string $locale, string $timezone = 'Africa/Bamako', bool $latinDigits = true, bool $withWeekday = true): string
    {
        $icu = (self::INTL_LOCALES[$locale] ?? $locale) . ($latinDigits ? '@numbers=latn' : '');
        $formatter = new IntlDateFormatter($icu, $withWeekday ? IntlDateFormatter::FULL : IntlDateFormatter::LONG, IntlDateFormatter::NONE, $timezone, IntlDateFormatter::GREGORIAN);

        return (string) $formatter->format($date);
    }
}
