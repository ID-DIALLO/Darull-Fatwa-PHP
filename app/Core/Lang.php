<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Traductions de l'interface (§7.1) : fichiers lang/{fr,ar,en}/{fichier}.php retournant des tableaux.
 * Clé « fichier.cle » (ex. __('home.hero.cta_ask')), paramètres « :nom », pluriels simples.
 * Clé absente → repli sur la langue par défaut, puis sur la clé elle-même (journalisé en mode debug).
 */
final class Lang
{
    private static string $path = '';

    /** @var list<string> */
    private static array $locales = ['fr', 'ar', 'en'];

    private static string $default = 'fr';

    private static string $locale = 'fr';

    /** @var list<string> */
    private static array $rtl = ['ar'];

    /** @var array<string, array<string, array<string, mixed>>> locale => fichier => tableau */
    private static array $loaded = [];

    /** @var (callable(string, string): void)|null Appelé pour chaque clé manquante */
    private static $missingHandler = null;

    /**
     * @param list<string> $locales
     * @param list<string> $rtl
     */
    public static function init(string $path, array $locales, string $default, array $rtl = ['ar'], ?callable $missingHandler = null): void
    {
        self::$path = rtrim($path, '/');
        self::$locales = $locales;
        self::$default = in_array($default, $locales, true) ? $default : ($locales[0] ?? 'fr');
        self::$locale = self::$default;
        self::$rtl = $rtl;
        self::$loaded = [];
        self::$missingHandler = $missingHandler;
    }

    /** @return list<string> */
    public static function locales(): array
    {
        return self::$locales;
    }

    public static function isSupported(?string $locale): bool
    {
        return $locale !== null && in_array($locale, self::$locales, true);
    }

    public static function setLocale(string $locale): void
    {
        if (self::isSupported($locale)) {
            self::$locale = $locale;
        }
    }

    public static function getLocale(): string
    {
        return self::$locale;
    }

    public static function getDefault(): string
    {
        return self::$default;
    }

    public static function direction(?string $locale = null): string
    {
        return in_array($locale ?? self::$locale, self::$rtl, true) ? 'rtl' : 'ltr';
    }

    public static function isRtl(?string $locale = null): bool
    {
        return self::direction($locale) === 'rtl';
    }

    /** @param array<string, scalar|null> $replace */
    public static function get(string $key, array $replace = [], ?string $locale = null): string
    {
        $locale ??= self::$locale;
        $value = self::lookup($key, $locale);

        if ($value === null && $locale !== self::$default) {
            self::reportMissing($key, $locale);
            $value = self::lookup($key, self::$default);
        }
        if ($value === null) {
            self::reportMissing($key, self::$default);

            return $key;
        }

        return self::replace($value, $replace);
    }

    public static function has(string $key, ?string $locale = null): bool
    {
        return self::lookup($key, $locale ?? self::$locale) !== null;
    }

    /**
     * Pluriel simple : « singulier|pluriel » ou « zéro|singulier|pluriel ». :count est remplacé automatiquement.
     *
     * @param array<string, scalar|null> $replace
     */
    public static function choice(string $key, int $count, array $replace = [], ?string $locale = null): string
    {
        $locale ??= self::$locale;
        $forms = explode('|', self::get($key, [], $locale));
        $replace['count'] ??= $count;

        if (count($forms) >= 3) {
            $text = $count === 0 ? $forms[0] : ($count === 1 ? $forms[1] : $forms[2]);
        } elseif (count($forms) === 2) {
            // En français, 0 et 1 sont au singulier ; en anglais et en arabe, seul 1 l'est.
            $singular = $locale === 'fr' ? $count <= 1 : $count === 1;
            $text = $singular ? $forms[0] : $forms[1];
        } else {
            $text = $forms[0];
        }

        return self::replace(trim($text), $replace);
    }

    /**
     * Langue à utiliser quand l'URL n'en contient pas (§7.1) :
     * cookie « lang » → langue du profil → en-tête Accept-Language → langue par défaut.
     */
    public static function detect(Request $request, ?string $userLocale = null): string
    {
        $cookie = $request->cookie('lang');
        if (self::isSupported($cookie)) {
            return (string) $cookie;
        }
        if (self::isSupported($userLocale)) {
            return (string) $userLocale;
        }
        foreach (self::parseAcceptLanguage((string) $request->header('Accept-Language')) as $candidate) {
            if (self::isSupported($candidate)) {
                return $candidate;
            }
        }

        return self::$default;
    }

    /**
     * « ar-SA,ar;q=0.9,fr;q=0.8 » → ['ar', 'fr'] (codes de langue triés par préférence).
     *
     * @return list<string>
     */
    public static function parseAcceptLanguage(string $header): array
    {
        $items = [];
        foreach (explode(',', $header) as $index => $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $segments = explode(';', $part);
            $code = strtolower(trim(explode('-', trim($segments[0]))[0]));
            $quality = 1.0;
            foreach (array_slice($segments, 1) as $param) {
                if (preg_match('/^\s*q=([0-9.]+)\s*$/', $param, $m)) {
                    $quality = (float) $m[1];
                }
            }
            if ($code !== '' && $code !== '*' && $quality > 0) {
                $items[] = ['code' => $code, 'q' => $quality, 'i' => $index];
            }
        }
        usort($items, static fn (array $a, array $b): int => [$b['q'], $a['i']] <=> [$a['q'], $b['i']]);

        return array_values(array_unique(array_column($items, 'code')));
    }

    private static function lookup(string $key, string $locale): ?string
    {
        $dot = strpos($key, '.');
        if ($dot === false) {
            return null;
        }
        $file = substr($key, 0, $dot);
        $item = substr($key, $dot + 1);
        $lines = self::load($locale, $file);

        if (array_key_exists($item, $lines)) {
            return is_string($lines[$item]) ? $lines[$item] : null;
        }
        // Accès imbriqué : 'errors.404.title' → $lines['errors']['404']['title']
        $value = $lines;
        foreach (explode('.', $item) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return is_string($value) ? $value : null;
    }

    /** @return array<string, mixed> */
    private static function load(string $locale, string $file): array
    {
        if (!isset(self::$loaded[$locale][$file])) {
            $path = self::$path . '/' . $locale . '/' . $file . '.php';
            $data = (preg_match('/^[a-z0-9_-]+$/', $file) && is_file($path)) ? require $path : [];
            self::$loaded[$locale][$file] = is_array($data) ? $data : [];
        }

        return self::$loaded[$locale][$file];
    }

    /** @param array<string, scalar|null> $replace */
    private static function replace(string $text, array $replace): string
    {
        if ($replace === []) {
            return $text;
        }
        // Les clés les plus longues d'abord (:name_full avant :name)
        uksort($replace, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        foreach ($replace as $name => $value) {
            $text = str_replace(':' . $name, (string) $value, $text);
        }

        return $text;
    }

    private static function reportMissing(string $key, string $locale): void
    {
        if (self::$missingHandler !== null) {
            (self::$missingHandler)($key, $locale);
        }
    }
}
