<?php

declare(strict_types=1);

namespace App\Controllers\Account;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Response;
use App\Models\Notification;
use App\Services\NotificationService;

/**
 * Tableau de bord de « Mon espace » (§4.4) : compteurs, dernières réponses, notifications récentes.
 */
final class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $userId = (int) $user['id'];
        $counts = Database::fetch(
            "SELECT COUNT(*) AS asked,
                    SUM(status IN ('answered','published','closed')) AS answered,
                    SUM(status IN ('new','assigned','in_progress','needs_info')) AS pending
             FROM questions WHERE user_id = ? AND deleted_at IS NULL",
            [$userId],
        ) ?? [];
        $latestAnswers = Database::fetchAll(
            'SELECT q.reference, q.title, q.answered_at, COALESCE(rt.name, rd.name) AS rubrique_name
             FROM questions q
             LEFT JOIN rubrique_translations rt ON rt.rubrique_id = q.rubrique_id AND rt.locale = ?
             LEFT JOIN rubrique_translations rd ON rd.rubrique_id = q.rubrique_id AND rd.locale = ?
             WHERE q.user_id = ? AND q.answered_at IS NOT NULL AND q.deleted_at IS NULL ORDER BY q.answered_at DESC LIMIT 5',
            [Lang::getLocale(), Lang::getDefault(), $userId],
        );
        $notifications = array_map(static fn (array $row): array => $row + ['rendered' => NotificationService::render($row)], Notification::latest($userId, 5));

        return $this->view('account/dashboard', [
            'user'   => $user,
            'counts' => [
                'asked'    => (int) ($counts['asked'] ?? 0),
                'answered' => (int) ($counts['answered'] ?? 0),
                'pending'  => (int) ($counts['pending'] ?? 0),
            ],
            'latestAnswers' => $latestAnswers,
            'notifications' => $notifications,
        ]);
    }
}
