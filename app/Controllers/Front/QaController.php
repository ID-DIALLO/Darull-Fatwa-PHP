<?php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\Rubrique;
use App\Services\CommentService;
use App\Services\FavoriteService;
use App\Services\QaService;
use App\Services\ReferenceFormatter;

/** Base publique de questions-réponses (§4.8). */
final class QaController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = ['rubrique' => (string) $request->query('rubrique', ''), 'sort' => $request->query('sort') === 'popular' ? 'popular' : 'recent', 'q' => trim((string) $request->query('q', ''))];
        // Le filtre accepte aussi le code de la rubrique (liens depuis les pages de rubrique)
        if ($filters['rubrique'] !== '' && !ctype_digit($filters['rubrique'])) {
            $rubrique = Rubrique::findByCode($filters['rubrique']);
            $filters['rubrique'] = $rubrique !== null ? (string) $rubrique['id'] : '';
        }

        return $this->view('front/qa/index', [
            'filters'   => $filters,
            'result'    => QaService::publicList($filters, max(1, (int) $request->query('page', 1))),
            'rubriques' => Rubrique::allActive(),
            'counts'    => QaService::countsByRubrique(),
        ]);
    }

    public function show(Request $request, string $slug): Response
    {
        $publication = QaService::findPublic($slug);
        if ($publication === null) {
            throw new HttpException(404);
        }
        $slugs = QaService::slugs((int) $publication['id']);
        $alternates = [];
        foreach (Lang::locales() as $code) {
            $alternates[$code] = isset($slugs[$code]) ? route('qa.show', ['slug' => $slugs[$code]], $code) : route('qa.index', ['missing' => $publication['id']], $code);
        }
        View::share('alternates', $alternates);
        QaService::incrementViews((int) $publication['id']);
        $userId = Auth::id();

        return $this->view('front/qa/show', [
            'publication' => $publication,
            'related'     => QaService::related($publication),
            'formatter'   => static fn (array $ref): string => ReferenceFormatter::format($ref),
            'isFavorite'  => FavoriteService::has($userId, 'qa', (int) $publication['id']),
            'favorites'   => FavoriteService::count('qa', (int) $publication['id']),
            'comments'    => CommentService::approvedFor('qa', (int) $publication['id']),
            'pending'     => $userId !== null ? CommentService::pendingOf($userId, 'qa', (int) $publication['id']) : [],
            'askUrl'      => route_exists('questions.create') ? route('questions.create', ['rubrique' => $publication['rubrique_code']]) : route('auth.register'),
        ]);
    }

    /** Commentaire d'un membre sur une Q/R (modération a priori). */
    public function comment(Request $request, string $slug): Response
    {
        $publication = QaService::findPublic($slug);
        if ($publication === null) {
            throw new HttpException(404);
        }
        $data = $this->validate($request, ['body' => 'required|min:3|max:3000', 'parent_id' => 'integer']);
        CommentService::add('qa', (int) $publication['id'], (int) Auth::id(), (string) $data['body'], !empty($data['parent_id']) ? (int) $data['parent_id'] : null);
        \App\Core\Flash::success(__('blog.comments.pending_notice'));

        return $this->redirect(route('qa.show', ['slug' => $slug]) . '#comments');
    }
}
