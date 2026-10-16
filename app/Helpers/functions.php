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
        return base_path('storage' . ($path !== '' ? '/' . ltrim($path, '/') : ''));
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
    /** Chemin d'une ressource de public/ avec numéro de version (cache long côté navigateur). */
    function asset(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        $file = public_path($path);
        $version = is_file($file) ? substr(md5((string) filemtime($file)), 0, 8) : null;

        return $path . ($version !== null ? '?v=' . $version : '');
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

        return $old[$key] ?? $default;
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
