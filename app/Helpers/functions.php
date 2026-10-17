<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\HttpException;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Services\HijriDateService;

/*
 * Fonctions utilitaires disponibles partout (vues, contrôleurs, scripts).
 */

if (!function_exists('env')) {
    /** Variable d'environnement (.env) avec conversion de true/false/null/empty. */
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($value === false || $value === null) {
            return $default;
        }

        return match (strtolower((string) $value)) {
            'true', '(true)'   => true,
            'false', '(false)' => false,
            'null', '(null)'   => null,
            'empty', '(empty)' => '',
            default            => $value,
        };
    }
}

if (!function_exists('e')) {
    /** Échappement HTML systématique de toute valeur affichée. */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('__')) {
    /** @param array<string, scalar|null> $replace */
    function __(string $key, array $replace = [], ?string $locale = null): string
    {
        return Lang::get($key, $replace, $locale);
    }
}

if (!function_exists('trans_choice')) {
    /** @param array<string, scalar|null> $replace */
    function trans_choice(string $key, int $count, array $replace = [], ?string $locale = null): string
    {
        return Lang::choice($key, $count, $replace, $locale);
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return App::hasInstance() ? App::instance()->config($key, $default) : $default;
    }
}

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return App::hasInstance() ? App::instance()->basePath($path) : dirname(__DIR__, 2) . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        // STORAGE_PATH (optionnel, absolu ou relatif à la racine) : permet d'isoler le stockage des tests.
        $root = (string) (env('STORAGE_PATH') ?: 'storage');
        $base = str_starts_with($root, '/') ? rtrim($root, '/') : base_path(trim($root, '/'));

        return $base . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('public_path')) {
    function public_path(string $path = ''): string
    {
        return base_path('public' . ($path !== '' ? '/' . ltrim($path, '/') : ''));
    }
}

if (!function_exists('locale')) {
    function locale(): string
    {
        return Lang::getLocale();
    }
}

if (!function_exists('text_dir')) {
    /** « rtl » pour l'arabe, « ltr » sinon. */
    function text_dir(?string $locale = null): string
    {
        return Lang::direction($locale);
    }
}

if (!function_exists('route')) {
    /**
     * URL d'une route nommée (jamais d'URL écrite en dur dans les vues).
     *
     * @param array<string, scalar> $params
     */
    function route(string $name, array $params = [], ?string $locale = null, bool $absolute = false): string
    {
        $path = App::instance()->router()->url($name, $params, $locale);

        return $absolute ? url($path) : $path;
    }
}

if (!function_exists('route_exists')) {
    function route_exists(string $name): bool
    {
        return App::hasInstance() && App::instance()->router()->has($name);
    }
}

if (!function_exists('alternate_urls')) {
    /**
     * Adresse de la page courante dans chaque langue (sélecteur de langue, balises hreflang).
     *
     * @return array<string, string> locale => chemin
     */
    function alternate_urls(): array
    {
        // Contenus traduits (rubrique, page, article…) : le contrôleur fournit les adresses par langue.
        $custom = View::shared('alternates');
        if (is_array($custom) && $custom !== []) {
            return $custom;
        }
        $request = View::shared('request');
        if (!$request instanceof Request || !App::hasInstance()) {
            return [];
        }

        return App::instance()->router()->alternates($request);
    }
}

if (!function_exists('url')) {
    /** URL absolue à partir d'un chemin (APP_URL). */
    function url(string $path = '/'): string
    {
        return rtrim((string) config('app.url', ''), '/') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    /**
     * Chemin d'une ressource de public/ avec numéro de version (cache long côté navigateur). En production
     * (ou ASSETS_BUILD=1), les fichiers listés dans public/assets/dist/manifest.json — générés par
     * `php bin/build-assets.php` — remplacent les sources.
     */
    function asset(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        $manifest = asset_manifest();
        if (isset($manifest[ltrim($path, '/')])) {
            $path = '/' . ltrim((string) $manifest[ltrim($path, '/')], '/');
        }
        $file = public_path($path);
        $version = is_file($file) ? substr(md5((string) filemtime($file)), 0, 8) : null;

        return $path . ($version !== null ? '?v=' . $version : '');
    }
}

if (!function_exists('asset_manifest')) {
    /** @return array<string, string> source => fichier construit (vide si la version construite n'est pas utilisée) */
    function asset_manifest(): array
    {
        static $manifest = null;
        if ($manifest !== null) {
            return $manifest;
        }
        $flag = env('ASSETS_BUILD');
        $enabled = $flag !== null && $flag !== '' ? filter_var($flag, FILTER_VALIDATE_BOOL) : config('app.env') === 'production';
        $file = public_path('assets/dist/manifest.json');
        $manifest = $enabled && is_file($file) ? (array) json_decode((string) file_get_contents($file), true) : [];

        return $manifest;
    }
}

if (!function_exists('styles')) {
    /**
     * Balises <link> d'un groupe de feuilles de style : un seul fichier concaténé et minifié s'il a été construit
     * (clé du manifeste), sinon les fichiers sources un par un.
     *
     * @param list<string> $files
     */
    function styles(array $files, string $bundle): string
    {
        if (isset(asset_manifest()[$bundle])) {
            return '<link rel="stylesheet" href="' . e(asset($bundle)) . '">' . "\n";
        }

        return implode("\n", array_map(static fn (string $f): string => '<link rel="stylesheet" href="' . e(asset($f)) . '">', $files)) . "\n";
    }
}

if (!function_exists('json_ld')) {
    /**
     * Balise <script type="application/ld+json"> (§13.1). « < » et « & » sont encodés pour qu'aucune donnée
     * ne puisse fermer la balise.
     *
     * @param array<string, mixed> $data
     */
    function json_ld(array $data): string
    {
        $json = json_encode(['@context' => 'https://schema.org'] + $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);

        return '<script type="application/ld+json">' . ($json === false ? '{}' : $json) . '</script>' . "\n";
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Csrf::token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return Csrf::field();
    }
}

if (!function_exists('method_field')) {
    function method_field(string $method): string
    {
        return '<input type="hidden" name="_method" value="' . e(strtoupper($method)) . '">';
    }
}

if (!function_exists('old')) {
    /** Ancienne saisie d'un formulaire renvoyé avec des erreurs. */
    function old(string $key, mixed $default = ''): mixed
    {
        $old = (array) Session::getFlash('_old_input', []);
        if (array_key_exists($key, $old)) {
            return $old[$key];
        }
        // Notation pointée pour les champs tableau (ex. « title.ar »)
        $value = $old;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}

if (!function_exists('errors')) {
    /** @return array<string, list<string>> Erreurs de validation du formulaire précédent */
    function errors(): array
    {
        return (array) Session::getFlash('_errors', []);
    }
}

if (!function_exists('error_for')) {
    function error_for(string $field): ?string
    {
        return errors()[$field][0] ?? null;
    }
}

if (!function_exists('auth')) {
    /** @return array<string, mixed>|null Utilisateur connecté */
    function auth(): ?array
    {
        return Auth::user();
    }
}

if (!function_exists('can')) {
    function can(string $permission): bool
    {
        return Auth::can($permission);
    }
}

if (!function_exists('abort')) {
    /** @param array<string, string> $headers */
    function abort(int $status, string $message = '', array $headers = []): never
    {
        throw new HttpException($status, $message, $headers);
    }
}

if (!function_exists('hijri_date')) {
    function hijri_date(?DateTimeInterface $date = null, ?string $locale = null): string
    {
        return HijriDateService::hijri($date ?? new DateTimeImmutable(), $locale ?? Lang::getLocale(), (string) config('app.timezone', 'Africa/Bamako'));
    }
}

if (!function_exists('gregorian_date')) {
    function gregorian_date(?DateTimeInterface $date = null, ?string $locale = null): string
    {
        return HijriDateService::gregorian($date ?? new DateTimeImmutable(), $locale ?? Lang::getLocale(), (string) config('app.timezone', 'Africa/Bamako'));
    }
}

if (!function_exists('setting')) {
    /** Paramètre du site (table settings), avec valeur par défaut. */
    function setting(string $key, ?string $default = null): ?string
    {
        return \App\Models\Setting::get($key, $default);
    }
}

if (!function_exists('whatsapp_link')) {
    /** Lien wa.me vers le numéro officiel (ou un numéro donné) avec un message prérempli (§4.3). */
    function whatsapp_link(?string $message = null, ?string $number = null): ?string
    {
        $number ??= setting('whatsapp_number') ?? (string) config('whatsapp.official_number', '');
        if ($number === null || $number === '' || str_contains($number, 'X')) {
            return null;
        }
        $digits = \App\Services\PhoneNumber::forWhatsApp($number);

        return 'https://wa.me/' . $digits . ($message !== null && $message !== '' ? '?text=' . rawurlencode($message) : '');
    }
}

if (!function_exists('excerpt')) {
    /** Extrait texte d'un contenu HTML, coupé au mot ($length caractères). */
    function excerpt(?string $html, int $length = 160): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        $cut = mb_substr($text, 0, $length);
        $space = mb_strrpos($cut, ' ');

        return rtrim($space !== false && $space > $length * 0.6 ? mb_substr($cut, 0, $space) : $cut, " ,;:.") . '…';
    }
}

if (!function_exists('format_date')) {
    /** Date courte dans la langue courante (ex. « 17 oct. 2026 »), depuis une date UTC de la base. */
    function format_date(?string $utcDate, bool $withTime = false): string
    {
        if ($utcDate === null || $utcDate === '') {
            return '';
        }
        try {
            $date = new DateTimeImmutable($utcDate, new DateTimeZone('UTC'));
        } catch (\Exception) {
            return $utcDate;
        }
        $icu = (string) config('app.intl_locales.' . Lang::getLocale(), 'fr_FR') . '@numbers=latn';
        $formatter = new IntlDateFormatter($icu, IntlDateFormatter::MEDIUM, $withTime ? IntlDateFormatter::SHORT : IntlDateFormatter::NONE, (string) config('app.timezone', 'Africa/Bamako'));

        return (string) $formatter->format($date);
    }
}

if (!function_exists('time_ago')) {
    /** « il y a 3 h » dans la langue courante (clés common.ago.*). */
    function time_ago(?string $utcDate): string
    {
        if ($utcDate === null || $utcDate === '') {
            return '';
        }
        $seconds = max(0, time() - (int) strtotime($utcDate . ' UTC'));
        [$key, $value] = match (true) {
            $seconds < 60      => ['now', 0],
            $seconds < 3600    => ['minutes', intdiv($seconds, 60)],
            $seconds < 86400   => ['hours', intdiv($seconds, 3600)],
            $seconds < 2592000 => ['days', intdiv($seconds, 86400)],
            $seconds < 31536000 => ['months', intdiv($seconds, 2592000)],
            default            => ['years', intdiv($seconds, 31536000)],
        };

        return $key === 'now' ? __('common.ago.now') : trans_choice('common.ago.' . $key, $value);
    }
}

if (!function_exists('mask_phone')) {
    /** « +22376123412 » → « +223 •• •• •• 12 » (numéros masqués pour les professeurs, §12.2). */
    function mask_phone(?string $phone): string
    {
        if ($phone === null || $phone === '') {
            return '';
        }
        $digits = preg_replace('/\D/', '', $phone) ?? '';
        $country = str_starts_with($phone, '+223') ? '+223' : '+' . substr($digits, 0, max(1, strlen($digits) - 8));

        return $country . ' •• •• •• ' . substr($digits, -2);
    }
}
