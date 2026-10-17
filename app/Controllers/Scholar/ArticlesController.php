<?php

declare(strict_types=1);

namespace App\Controllers\Scholar;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Rubrique;
use App\Services\ActivityLog;
use App\Services\ArticleService;

/** Articles d'un professeur (§4.7) : brouillons, soumission à la validation, suivi. */
final class ArticlesController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('articles.create');

        return $this->view('scholar/articles/index', ['result' => ArticleService::manageList(['status' => (string) $request->query('status', '')], (int) Auth::id(), max(1, (int) $request->query('page', 1)))]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('articles.create');

        return $this->view('scholar/articles/form', ['article' => null, 'rubriques' => Rubrique::allActive()]);
    }

    public function edit(Request $request, string $id): Response
    {
        return $this->view('scholar/articles/form', ['article' => $this->own($id), 'rubriques' => Rubrique::allActive()]);
    }

    public function store(Request $request): Response
    {
        $this->authorize('articles.create');

        return $this->persist($request, null);
    }

    public function update(Request $request, string $id): Response
    {
        $article = $this->own($id);
        if (!in_array($article['status'], ['draft', 'submitted'], true)) {
            throw new HttpException(409);
        }

        return $this->persist($request, (int) $article['id']);
    }

    private function persist(Request $request, ?int $id): Response
    {
        $this->validate($request, ['title.' . \App\Core\Lang::getDefault() => 'required_without:title.ar|max:200', 'rubrique_id' => 'integer|exists:rubriques,id', 'cover' => 'file|mimes:jpg,jpeg,png,webp|max_size:5120']);
        $hasTitle = array_filter((array) $request->input('title', []), static fn ($t): bool => trim((string) $t) !== '') !== [];
        if (!$hasTitle) {
            \App\Core\Flash::error(__('blog.manage.flash.title_required'));

            return $this->back($request, route('scholar.articles'));
        }
        $articleId = ArticleService::save($id, array_merge($request->all(), ['status' => $request->input('submit') === 'submit' ? 'submitted' : 'draft']), (int) Auth::id(), $request->file('cover'), true);
        ActivityLog::record($request->input('submit') === 'submit' ? 'article.submitted' : 'article.saved', 'article', $articleId);
        if ($request->input('submit') === 'submit') {
            $staff = \App\Core\Database::fetchAll("SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug IN ('admin','superadmin') AND u.status = 'active' AND u.deleted_at IS NULL");
            \App\Services\NotificationService::notifyMany(array_map(static fn (array $u): int => (int) $u['id'], $staff), 'article.submitted', ['author' => (string) (Auth::user()['full_name'] ?? '')], route('admin.articles.edit', ['id' => $articleId]));
        }
        \App\Core\Flash::success(__($request->input('submit') === 'submit' ? 'blog.manage.flash.submitted' : 'blog.manage.flash.saved'));

        return $this->redirectToRoute('scholar.articles');
    }

    /** @return array<string, mixed> */
    private function own(string $id): array
    {
        $this->authorize('articles.create');
        $article = ctype_digit($id) ? ArticleService::find((int) $id) : null;
        if ($article === null || (int) $article['author_id'] !== (int) Auth::id()) {
            throw new HttpException(404);
        }

        return $article;
    }
}
