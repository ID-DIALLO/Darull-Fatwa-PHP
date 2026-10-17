<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\Admin\StatsService;

/**
 * Tableau de bord de l'administration (§4.6.1) : cartes KPI, graphiques Chart.js et filtre de période.
 */
final class DashboardController extends AdminController
{
    public function index(Request $request): Response
    {
        $range = StatsService::range($request->all());

        return $this->view('admin/dashboard', [
            'range'   => $range,
            'kpis'    => StatsService::kpis($range),
            'recent'  => Database::fetchAll(
                "SELECT q.id, q.reference, q.title, q.status, q.created_at, u.full_name AS member_name, s.full_name AS scholar_name
                 FROM questions q JOIN users u ON u.id = q.user_id LEFT JOIN users s ON s.id = q.assigned_to
                 WHERE q.deleted_at IS NULL ORDER BY q.created_at DESC LIMIT 8",
            ),
            'reviews' => Database::fetchAll(
                "SELECT q.id, q.reference, q.title, m.created_at, u.full_name AS scholar_name
                 FROM question_messages m JOIN questions q ON q.id = m.question_id JOIN users u ON u.id = m.sender_id
                 WHERE m.kind = 'answer' AND m.review_status = 'pending' AND q.deleted_at IS NULL ORDER BY m.created_at ASC LIMIT 8",
            ),
        ]);
    }

    /** Langue de l'interface d'administration (cookie « lang », mémorisé aussi dans le profil). */
    public function language(Request $request, string $code): Response
    {
        if (!\App\Core\Lang::isSupported($code)) {
            throw new \App\Core\HttpException(404);
        }
        \App\Models\User::update((int) \App\Core\Auth::id(), ['locale' => $code]);
        $referer = (string) $request->header('Referer');
        $target = parse_url($referer, PHP_URL_HOST) === $request->host() ? $referer : route('admin.dashboard');

        return $this->redirect($target)->cookie('lang', $code, ['expires' => time() + 365 * 86400, 'secure' => $request->isSecure(), 'httponly' => false, 'samesite' => 'Lax']);
    }

    /** Données JSON des graphiques (§11.4 : GET /admin/stats/data?period=). */
    public function data(Request $request): Response
    {
        $this->authorize('stats.view');
        $range = StatsService::range($request->all());

        return $this->json(['range' => $range, 'kpis' => StatsService::kpis($range), 'charts' => StatsService::charts($range)]);
    }
}
