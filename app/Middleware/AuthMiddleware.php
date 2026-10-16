<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use Closure;

/**
 * Réservé aux membres connectés : sinon redirection vers la page de connexion (ou 401 en JSON).
 */
final class AuthMiddleware implements Middleware
{
    public function handle(Request $request, Closure $next, string ...$args): Response
    {
        if (Auth::check()) {
            return $next($request);
        }
        if ($request->wantsJson()) {
            return Response::json(['message' => __('common.errors.401')], 401);
        }
        if (route_exists('auth.login')) {
            Session::set('_intended', $request->uri());

            return Response::redirect(route('auth.login'));
        }

        throw new HttpException(403);
    }
}
