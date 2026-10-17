<?php

declare(strict_types=1);

namespace App\Controllers\Account;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

/**
 * Tableau de bord de « Mon espace » (§4.4). Les compteurs de questions se remplissent dès la Phase 3.
 */
final class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $counts = Database::fetch(
            "SELECT COUNT(*) AS asked,
                    SUM(status IN ('answered','published','closed')) AS answered,
                    SUM(status IN ('new','assigned','in_progress','needs_info')) AS pending
             FROM questions WHERE user_id = ? AND deleted_at IS NULL",
            [(int) $user['id']],
        ) ?? [];

        return $this->view('account/dashboard', [
            'user'   => $user,
            'counts' => [
                'asked'    => (int) ($counts['asked'] ?? 0),
                'answered' => (int) ($counts['answered'] ?? 0),
                'pending'  => (int) ($counts['pending'] ?? 0),
            ],
        ]);
    }
}
