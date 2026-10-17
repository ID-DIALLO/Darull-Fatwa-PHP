<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Response;
use App\Core\Route;
use App\Core\View;
use Closure;

/**
 * Langue de la requête (§7.1) : préfixe de l'URL (/fr, /ar, /en) ; à défaut
 * cookie « lang » → langue du profil → Accept-Language → français.
 * La langue choisie via l'URL est mémorisée dans le cookie « lang » (1 an).
 */
final class LocaleMiddleware implements Middleware
{
    public function handle(Request $request, Closure $next, string ...$args): Response
    {
        $route = $request->attribute('route');
        $fromUrl = $route instanceof Route ? $route->locale : null;
        if ($fromUrl === null) {
            $segment = explode('/', trim($request->path(), '/'))[0];
            $fromUrl = Lang::isSupported($segment) ? $segment : null;
        }

        $locale = $fromUrl ?? Lang::detect($request, self::userLocale($request));
        Lang::setLocale($locale);
        $request->setAttribute('locale', $locale);
        View::share('locale', $locale);
        View::share('dir', Lang::direction($locale));

        $response = $next($request);

        if ($fromUrl !== null && $request->cookie('lang') !== $fromUrl) {
            $response->cookie('lang', $fromUrl, [
                'expires'  => time() + 365 * 86400,
                'secure'   => $request->isSecure(),
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
        }

        return $response;
    }

    private static function userLocale(Request $request): ?string
    {
        // On n'interroge la base que si une session utilisateur existe.
        if (!\App\Core\Session::has('user_id')) {
            return null;
        }
        $locale = Auth::user()['locale'] ?? null;

        return is_string($locale) ? $locale : null;
    }
}
