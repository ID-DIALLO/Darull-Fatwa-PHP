<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

/**
 * Tableau de bord de l'administration. Phase 1 : page d'attente protégée par les rôles ;
 * le back-office complet arrive en Phase 5.
 */
final class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('account/placeholder', ['area' => 'admin']);
    }
}
