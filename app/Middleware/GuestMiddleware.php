<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use Closure;

/**
 * Réservé aux visiteurs non connectés (connexion, inscription) : sinon retour à l'accueil.
 */
final class GuestMiddleware implements Middleware
{
    public function handle(Request $request, Closure $next, string ...$args): Response
    {
        if (Auth::check()) {
            return Response::redirect(route('home'));
        }

        return $next($request);
    }
}
