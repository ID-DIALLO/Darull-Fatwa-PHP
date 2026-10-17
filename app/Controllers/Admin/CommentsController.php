<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\CommentService;

/** Modération des commentaires (articles, Q/R). */
final class CommentsController extends AdminController
{
    protected ?string $permission = 'comments.moderate';

    public function index(Request $request): Response
    {
        $filters = ['status' => (string) $request->query('status', 'pending'), 'type' => (string) $request->query('type', '')];

        return $this->view('admin/comments', ['filters' => $filters, 'result' => CommentService::adminList($filters, $this->page($request)), 'counts' => CommentService::counts()]);
    }

    public function moderate(Request $request, string $id): Response
    {
        $data = $this->validate($request, ['status' => 'required|in:' . implode(',', CommentService::STATUSES)]);
        CommentService::moderate($this->id($id), (string) $data['status'], (int) Auth::id());

        return $this->back($request, route('admin.comments'));
    }

    public function destroy(Request $request, string $id): Response
    {
        CommentService::delete($this->id($id), (int) Auth::id());

        return $this->back($request, route('admin.comments'));
    }
}
