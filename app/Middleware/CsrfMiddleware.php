<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Route;
use Closure;

/**
 * Vérifie le jeton CSRF sur POST/PUT/PATCH/DELETE (champ « _token » ou en-tête « X-CSRF-Token »).
 * Jeton absent ou invalide → page 419 « Session expirée ».
 */
final class CsrfMiddleware implements Middleware
{
    public function handle(Request $request, Closure $next, string ...$args): Response
    {
        $route = $request->attribute('route');
        $unsafe = in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true);

        if ($unsafe && $route instanceof Route && $route->requiresCsrf()) {
            $token = $request->input('_token');
            $token = is_string($token) ? $token : $request->header('X-CSRF-Token');
            if (!Csrf::verify($token)) {
                throw new HttpException(419);
            }
        }

        return $next($request);
    }
}
