<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Rubrique;
use App\Services\ActivityLog;
use App\Services\ArticleService;
use App\Services\NotificationService;

/** Blog côté administration (§4.6.3) : CRUD multilingue, planification, mise en avant, validation des soumissions. */
final class ArticlesController extends AdminController
{
    protected ?string $permission = 'articles.publish';

    public function index(Request $request): Response
    {
        $filters = ['status' => (string) $request->query('status', ''), 'rubrique' => (string) $request->query('rubrique', ''), 'q' => (string) $request->query('q', '')];

        return $this->view('admin/articles/index', [
            'filters'   => $filters,
            'result'    => ArticleService::manageList($filters, null, $this->page($request)),
            'rubriques' => Rubrique::allActive(),
            'counts'    => array_column(Database::fetchAll('SELECT status, COUNT(*) AS n FROM articles WHERE deleted_at IS NULL GROUP BY status'), 'n', 'status'),
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->view('admin/articles/form', ['article' => null, 'rubriques' => Rubrique::allActive(), 'authors' => $this->authors()]);
    }

    public function edit(Request $request, string $id): Response
    {
        $article = ArticleService::find($this->id($id));
        if ($article === null) {
            throw new HttpException(404);
        }

        return $this->view('admin/articles/form', ['article' => $article, 'rubriques' => Rubrique::allActive(), 'authors' => $this->authors()]);
    }

    public function store(Request $request): Response
    {
        return $this->persist($request, null);
    }

    public function update(Request $request, string $id): Response
    {
        $articleId = $this->id($id);
        if (ArticleService::find($articleId) === null) {
            throw new HttpException(404);
        }

        return $this->persist($request, $articleId);
    }

    private function persist(Request $request, ?int $id): Response
    {
        $this->validate($request, ['status' => 'required|in:' . implode(',', ArticleService::STATUSES), 'rubrique_id' => 'integer|exists:rubriques,id', 'author_id' => 'integer', 'cover' => 'file|mimes:jpg,jpeg,png,webp|max_size:5120', 'published_at' => 'date']);
        $hasTitle = array_filter((array) $request->input('title', []), static fn ($t): bool => trim((string) $t) !== '') !== [];
        if (!$hasTitle) {
            return $this->done($id === null ? 'admin.articles.create' : 'admin.articles.edit', $id === null ? [] : ['id' => $id], __('blog.manage.flash.title_required'), 'error');
        }
        $previous = $id !== null ? Database::value('SELECT status FROM articles WHERE id = ?', [$id]) : null;
        $articleId = ArticleService::save($id, $request->all(), (int) Auth::id(), $request->file('cover'), false);
        $status = (string) Database::value('SELECT status FROM articles WHERE id = ?', [$articleId]);
        ActivityLog::record($id === null ? 'admin.article.created' : 'admin.article.updated', 'article', $articleId, ['status' => $status]);
        // Article soumis par un professeur puis publié : l'auteur est prévenu
        if ($previous === 'submitted' && in_array($status, ['published', 'scheduled'], true)) {
            $authorId = (int) Database::value('SELECT author_id FROM articles WHERE id = ?', [$articleId]);
            if ($authorId > 0) {
                NotificationService::notify($authorId, 'article.published', [], route('admin.articles.edit', ['id' => $articleId]));
            }
        }

        return $this->done('admin.articles.edit', ['id' => $articleId]);
    }

    public function status(Request $request, string $id): Response
    {
        $data = $this->validate($request, ['status' => 'required|in:' . implode(',', ArticleService::STATUSES)]);
        ArticleService::setStatus($this->id($id), (string) $data['status'], (int) Auth::id());

        return $this->done('admin.articles');
    }

    public function destroy(Request $request, string $id): Response
    {
        ArticleService::delete($this->id($id), (int) Auth::id());

        return $this->done('admin.articles', [], __('admin.common.deleted'));
    }

    /** Auteurs possibles : professeurs et administrateurs. @return list<array<string, mixed>> */
    private function authors(): array
    {
        return Database::fetchAll("SELECT u.id, u.full_name, r.slug AS role FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug IN ('scholar','admin','superadmin') AND u.deleted_at IS NULL AND u.status = 'active' ORDER BY u.full_name");
    }
}
