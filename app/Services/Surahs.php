<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Lang;

/**
 * Les 114 sourates du Coran (§4.2.4) : numéro, nom arabe, translittération française et anglaise.
 * Sert au formulaire de références des professeurs et à l'affichage normalisé des références.
 */
final class Surahs
{
    /** @var array<int, array{0: string, 1: string, 2: string}>|null numéro => [arabe, français, anglais] */
    private static ?array $list = null;

    /** @return array<int, array{0: string, 1: string, 2: string}> */
    public static function all(): array
    {
        return self::$list ??= require base_path('app/Config/surahs.php');
    }

    public static function name(int $number, ?string $locale = null): string
    {
        $row = self::all()[$number] ?? null;
        if ($row === null) {
            return (string) $number;
        }
        $locale ??= Lang::getLocale();

        return match ($locale) {
            'ar'    => $row[0],
            'en'    => $row[2],
            default => $row[1],
        };
    }

    /** Liste pour un <select> : numéro => « 2. Al-Baqara (البقرة) ». @return array<int, string> */
    public static function options(?string $locale = null): array
    {
        $locale ??= Lang::getLocale();
        $options = [];
        foreach (self::all() as $number => $row) {
            $options[$number] = $locale === 'ar' ? $number . '. ' . $row[0] : $number . '. ' . ($locale === 'en' ? $row[2] : $row[1]) . ' (' . $row[0] . ')';
        }

        return $options;
    }
}
