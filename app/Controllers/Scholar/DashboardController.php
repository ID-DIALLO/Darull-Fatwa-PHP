<?php

declare(strict_types=1);

namespace App\Controllers\Scholar;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

/**
 * Tableau de bord de l'espace professeur. Phase 1 : page d'attente protégée par les rôles ;
 * l'espace complet arrive en Phase 4.
 */
final class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('account/placeholder', ['area' => 'scholar']);
    }
}
