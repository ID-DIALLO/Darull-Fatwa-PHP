<?php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\IdeaService;

/** Boîte à idées (§4.10). */
final class IdeaController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = ['sort' => (string) $request->query('tri', $request->query('sort', 'popular')), 'status' => (string) $request->query('statut', $request->query('status', '')), 'category' => (string) $request->query('categorie', $request->query('category', ''))];

        return $this->view('front/ideas/index', ['filters' => $filters, 'result' => IdeaService::publicList($filters, max(1, (int) $request->query('page', 1)), 12, Auth::id())]);
    }

    public function show(Request $request, string $id): Response
    {
        $idea = $this->find($id);
        $userId = Auth::id();

        return $this->view('front/ideas/show', [
            'idea'     => $idea,
            'voted'    => IdeaService::hasVoted($userId, (int) $idea['id']),
            'comments' => IdeaService::comments((int) $idea['id'], $userId, Auth::can('ideas.moderate')),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('ideas.create');

        return $this->view('front/ideas/create');
    }

    public function store(Request $request): Response
    {
        $this->authorize('ideas.create');
        $data = $this->validate($request, ['category' => 'required|in:' . implode(',', IdeaService::CATEGORIES), 'title' => 'required|min:5|max:200', 'description' => 'required|min:20|max:5000']);
        $id = IdeaService::create(Auth::user(), (string) $data['category'], (string) $data['title'], (string) $data['description']);
        Flash::success(__('ideas.flash.created'));

        return $this->redirect(route('ideas.show', ['id' => $id]));
    }

    public function vote(Request $request, string $id): Response
    {
        $this->authorize('ideas.vote');
        $idea = $this->find($id);
        try {
            [$voted, $count] = IdeaService::toggleVote((int) Auth::id(), (int) $idea['id']);
        } catch (\InvalidArgumentException) {
            Flash::error(__('ideas.flash.not_votable'));

            return $this->redirect(route('ideas.show', ['id' => $idea['id']]));
        }
        if ($request->wantsJson()) {
            return $this->json(['voted' => $voted, 'count' => $count]);
        }
        Flash::success(__($voted ? 'ideas.flash.voted' : 'ideas.flash.unvoted'));

        return $this->back($request, route('ideas.show', ['id' => $idea['id']]));
    }

    public function comment(Request $request, string $id): Response
    {
        $this->authorize('ideas.create');
        $idea = $this->find($id);
        $data = $this->validate($request, ['body' => 'required|min:3|max:2000']);
        IdeaService::comment($idea, Auth::user(), (string) $data['body']);
        Flash::success(__('ideas.flash.commented'));

        return $this->redirect(route('ideas.show', ['id' => $idea['id']]) . '#comments');
    }

    /** Mes idées (Mon espace). */
    public function mine(Request $request): Response
    {
        return $this->view('account/ideas', ['ideas' => IdeaService::ideasOf((int) Auth::id())]);
    }

    /** @return array<string, mixed> */
    private function find(string $id): array
    {
        $idea = ctype_digit($id) ? IdeaService::find((int) $id) : null;
        if ($idea === null || !IdeaService::canView($idea, Auth::user())) {
            throw new HttpException(404);
        }

        return $idea;
    }
}
