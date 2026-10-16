<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use Closure;

/**
 * Réservé à certains rôles : 'role:admin,superadmin'. Le super-admin passe toujours.
 */
final class RoleMiddleware implements Middleware
{
    public function handle(Request $request, Closure $next, string ...$args): Response
    {
        if (!Auth::check()) {
            return (new AuthMiddleware())->handle($request, $next);
        }
        if (!Auth::hasRole('superadmin', ...$args)) {
            throw new HttpException(403);
        }

        return $next($request);
    }
}
