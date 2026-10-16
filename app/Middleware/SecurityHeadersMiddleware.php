<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use Closure;

/**
 * En-têtes de sécurité (§12.1) : CSP, nosniff, X-Frame-Options, Referrer-Policy,
 * Permissions-Policy (micro autorisé pour notre propre site uniquement), HSTS en HTTPS.
 */
final class SecurityHeadersMiddleware implements Middleware
{
    public function handle(Request $request, Closure $next, string ...$args): Response
    {
        return self::apply($next($request), $request);
    }

    public static function apply(Response $response, Request $request): Response
    {
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' https://challenges.cloudflare.com",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob:",
            "media-src 'self' blob:",
            "font-src 'self'",
            "connect-src 'self'",
            'frame-src https://challenges.cloudflare.com',
            "worker-src 'self' blob:",
            "manifest-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ]);

        $headers = [
            'Content-Security-Policy' => $csp,
            'X-Content-Type-Options'  => 'nosniff',
            'X-Frame-Options'         => 'SAMEORIGIN',
            'Referrer-Policy'         => 'strict-origin-when-cross-origin',
            'Permissions-Policy'      => 'microphone=(self), camera=(), geolocation=()',
        ];
        if ($request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $name => $value) {
            if ($response->getHeader($name) === null) {
                $response->header($name, $value);
            }
        }

        return $response;
    }
}
