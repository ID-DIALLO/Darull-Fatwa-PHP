<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Setting;
use Closure;

/**
 * Mode maintenance : fichier storage/maintenance.flag ou paramètre « maintenance_mode » = 1.
 * Les administrateurs continuent d'accéder au site. Les autres reçoivent la page 503 traduite.
 */
final class MaintenanceMiddleware implements Middleware
{
    public function handle(Request $request, Closure $next, string ...$args): Response
    {
        if (self::isDown() && !Auth::hasRole('admin', 'superadmin')) {
            throw new HttpException(503, 'Maintenance', ['Retry-After' => '3600']);
        }

        return $next($request);
    }

    public static function isDown(): bool
    {
        if (is_file(base_path('storage/maintenance.flag'))) {
            return true;
        }

        return Setting::get('maintenance_mode', '0') === '1';
    }
}
