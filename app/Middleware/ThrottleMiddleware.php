<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Route;
use App\Services\RateLimiter;
use Closure;

/**
 * Limitation du nombre de requêtes (§12.1) : 'throttle:5,60' = 5 requêtes par 60 secondes et par IP.
 * Dépassement → page 429 avec l'en-tête Retry-After.
 */
final class ThrottleMiddleware implements Middleware
{
    public function handle(Request $request, Closure $next, string ...$args): Response
    {
        $max = (int) ($args[0] ?? 60);
        $decay = (int) ($args[1] ?? 60);
        $route = $request->attribute('route');
        $name = $route instanceof Route ? ($route->getName() ?? $route->path) : $request->path();

        $result = RateLimiter::hit('throttle:' . $name . ':ip:' . $request->ip(), $max, $decay);
        if (!$result['allowed']) {
            throw new HttpException(429, 'Too Many Requests', ['Retry-After' => (string) $result['retry_after']]);
        }

        $response = $next($request);
        $response->header('X-RateLimit-Limit', (string) $max);
        $response->header('X-RateLimit-Remaining', (string) $result['remaining']);

        return $response;
    }
}
