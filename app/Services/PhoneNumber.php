<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Numéros de téléphone au format international E.164 (+223XXXXXXXX), indicatif du Mali par défaut.
 */
final class PhoneNumber
{
    public const DEFAULT_COUNTRY_CODE = '+223';

    /** Indicatifs par pays (seuls ceux de app/Config/countries.php sont acceptés). */
    public const COUNTRY_CODES = ['ML' => '+223', 'BF' => '+226', 'CI' => '+225', 'SN' => '+221', 'GN' => '+224', 'NE' => '+227', 'MR' => '+222', 'TG' => '+228', 'BJ' => '+229', 'MA' => '+212', 'DZ' => '+213', 'FR' => '+33'];

    /** Indicatifs acceptés, déduits des pays ouverts. @return list<string> */
    public static function allowedPrefixes(): array
    {
        $prefixes = [];
        foreach ((array) config('countries', ['ML', 'BF', 'CI']) as $country) {
            if (isset(self::COUNTRY_CODES[$country])) {
                $prefixes[] = self::COUNTRY_CODES[$country];
            }
        }

        return $prefixes !== [] ? $prefixes : [self::DEFAULT_COUNTRY_CODE];
    }

    /** Le numéro (E.164) appartient-il à un pays ouvert ? */
    public static function isAllowed(?string $phone): bool
    {
        if ($phone === null) {
            return false;
        }
        foreach (self::allowedPrefixes() as $prefix) {
            if (str_starts_with($phone, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Nettoie une saisie : espaces, points, tirets et parenthèses retirés, « 00 » remplacé par « + »,
     * numéro local malien (8 chiffres) préfixé par +223. Retourne null si la saisie est vide.
     * La validité finale est vérifiée par la règle « phone » du Validator.
     */
    public static function normalize(?string $raw, string $defaultCountryCode = self::DEFAULT_COUNTRY_CODE): ?string
    {
        if ($raw === null) {
            return null;
        }
        // Chiffres arabes-indiens → chiffres occidentaux
        $raw = strtr($raw, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']);
        $value = preg_replace('/[\s().\-\x{200E}\x{200F}\x{202A}-\x{202E}]/u', '', trim($raw)) ?? '';
        if ($value === '') {
            return null;
        }
        if (str_starts_with($value, '00')) {
            $value = '+' . substr($value, 2);
        }
        if (!str_starts_with($value, '+')) {
            if (preg_match('/^\d{8}$/', $value)) {
                $value = $defaultCountryCode . $value;
            } elseif (preg_match('/^223\d{8}$/', $value)) {
                $value = '+' . $value;
            } else {
                $value = '+' . $value;
            }
        }

        return $value;
    }

    public static function isValid(?string $phone): bool
    {
        return $phone !== null && (bool) preg_match('/^\+[1-9]\d{7,14}$/', $phone);
    }

    /** Affichage lisible : « +223 76 12 34 56 ». */
    public static function format(?string $phone): string
    {
        if ($phone === null || $phone === '') {
            return '';
        }
        if (preg_match('/^\+223(\d{2})(\d{2})(\d{2})(\d{2})$/', $phone, $m)) {
            return '+223 ' . $m[1] . ' ' . $m[2] . ' ' . $m[3] . ' ' . $m[4];
        }

        return $phone;
    }

    /** Numéro sans « + » pour les liens wa.me. */
    public static function forWhatsApp(string $phone): string
    {
        return ltrim(preg_replace('/\D/', '', $phone) ?? '', '0');
    }
}
