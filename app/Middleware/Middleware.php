<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use Closure;

/**
 * Un middleware reçoit la requête, peut l'arrêter (redirection, erreur) ou la transmettre à $next.
 * Les arguments viennent de la définition de la route : 'role:admin,superadmin' → ('admin', 'superadmin').
 */
interface Middleware
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next, string ...$args): Response;
}
