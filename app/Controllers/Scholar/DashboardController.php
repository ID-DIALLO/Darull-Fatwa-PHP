<?php

declare(strict_types=1);

namespace App\Controllers\Scholar;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Response;
use App\Services\ScholarService;

/**
 * Tableau de bord du professeur (§4.5) : assignées par statut, à prendre, réponses du mois, délai moyen, retards.
 */
final class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $rubriques = Database::fetchAll(
            'SELECT r.id, r.icon, COALESCE(t.name, d.name) AS name, COALESCE(t.slug, d.slug) AS slug FROM scholar_rubriques sr
             JOIN rubriques r ON r.id = sr.rubrique_id
             LEFT JOIN rubrique_translations t ON t.rubrique_id = r.id AND t.locale = ?
             LEFT JOIN rubrique_translations d ON d.rubrique_id = r.id AND d.locale = ?
             WHERE sr.user_id = ? ORDER BY r.sort_order',
            [Lang::getLocale(), Lang::getDefault(), (int) $user['id']],
        );

        return $this->view('scholar/dashboard', [
            'user'      => $user,
            'stats'     => ScholarService::dashboard((int) $user['id']),
            'rubriques' => $rubriques,
            'recent'    => ScholarService::queue((int) $user['id'], 'mine', ['sort' => 'oldest'], 1, 5)['data'],
        ]);
    }

    /** Statistiques des questions du professeur (statuts, mois, rubriques, langues, source, délais). */
    public function stats(Request $request): Response
    {
        return $this->view('scholar/stats', ['stats' => ScholarService::statistics((int) Auth::id())]);
    }

    /** Commentaires publiés sous ses articles et ses Q/R publiques. */
    public function comments(Request $request): Response
    {
        return $this->view('scholar/comments', ['comments' => ScholarService::comments((int) Auth::id())]);
    }

    /** Sujets de forum ouverts par le professeur (questions à débattre) et accès à la création. */
    public function debates(Request $request): Response
    {
        return $this->view('scholar/debates', ['topics' => \App\Services\ForumService::topicsOf((int) Auth::id()), 'categories' => \App\Services\ForumService::categories()]);
    }
}
