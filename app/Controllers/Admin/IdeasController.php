<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\IdeaService;

/** Boîte à idées côté administration : modération, statuts, réponse officielle, commentaires. */
final class IdeasController extends AdminController
{
    protected ?string $permission = 'ideas.moderate';

    public function index(Request $request): Response
    {
        $filters = ['status' => (string) $request->query('status', ''), 'category' => (string) $request->query('category', '')];

        return $this->view('admin/ideas/index', ['filters' => $filters, 'result' => IdeaService::adminList($filters, $this->page($request)), 'counts' => array_column(\App\Core\Database::fetchAll('SELECT status, COUNT(*) AS n FROM ideas WHERE deleted_at IS NULL GROUP BY status'), 'n', 'status')]);
    }

    public function show(Request $request, string $id): Response
    {
        $idea = IdeaService::find($this->id($id));
        if ($idea === null) {
            throw new HttpException(404);
        }

        return $this->view('admin/ideas/show', ['idea' => $idea, 'comments' => IdeaService::comments((int) $idea['id'], null, true)]);
    }

    public function update(Request $request, string $id): Response
    {
        $ideaId = $this->id($id);
        $data = $this->validate($request, ['status' => 'required|in:' . implode(',', IdeaService::STATUSES), 'admin_response' => 'max:5000']);
        try {
            IdeaService::setStatus($ideaId, (string) $data['status'], $data['admin_response'] ?: null, (int) Auth::id());
        } catch (\InvalidArgumentException) {
            throw new HttpException(404);
        }

        return $this->done('admin.ideas.show', ['id' => $ideaId]);
    }

    public function comment(Request $request, string $id): Response
    {
        $data = $this->validate($request, ['status' => 'required|in:visible,hidden,pending']);
        IdeaService::moderateComment($this->id($id), (string) $data['status'], (int) Auth::id());

        return $this->back($request, route('admin.ideas'));
    }

    public function destroy(Request $request, string $id): Response
    {
        IdeaService::delete($this->id($id), (int) Auth::id());

        return $this->done('admin.ideas', [], __('admin.common.deleted'));
    }
}
