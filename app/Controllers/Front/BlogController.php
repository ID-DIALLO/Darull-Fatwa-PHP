<?php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\HttpException;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\Rubrique;
use App\Services\ArticleService;
use App\Services\CommentService;
use App\Services\FavoriteService;

/** Blog public (§4.7) : liste, article, commentaires. */
final class BlogController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = ['rubrique' => (string) $request->query('rubrique', ''), 'tag' => trim((string) $request->query('tag', '')), 'q' => trim((string) $request->query('q', ''))];
        if ($filters['rubrique'] !== '' && !ctype_digit($filters['rubrique'])) {
            $rubrique = Rubrique::findByCode($filters['rubrique']);
            $filters['rubrique'] = $rubrique !== null ? (string) $rubrique['id'] : '';
        }
        $page = max(1, (int) $request->query('page', 1));
        $result = ArticleService::publicList($filters, $page);
        $featured = null;
        if ($page === 1 && array_filter($filters) === [] && $result['data'] !== [] && (int) $result['data'][0]['is_featured'] === 1) {
            $featured = array_shift($result['data']);
        }

        return $this->view('front/blog/index', [
            'filters'   => $filters,
            'result'    => $result,
            'featured'  => $featured,
            'rubriques' => Rubrique::allActive(),
            'popular'   => ArticleService::popular(),
            'tags'      => ArticleService::tagCloud(),
            'missing'   => $request->query('missing') !== null,
        ]);
    }

    public function show(Request $request, string $slug): Response
    {
        $article = ArticleService::findPublic($slug);
        if ($article === null) {
            throw new HttpException(404);
        }
        $slugs = ArticleService::slugs((int) $article['id']);
        $alternates = [];
        foreach (Lang::locales() as $code) {
            // Traduction absente : liste du blog de la langue choisie avec un message (§4.7)
            $alternates[$code] = isset($slugs[$code]) ? route('blog.show', ['slug' => $slugs[$code]], $code) : route('blog.index', ['missing' => $article['id']], $code);
        }
        View::share('alternates', $alternates);
        ArticleService::incrementViews((int) $article['id']);
        $userId = Auth::id();

        return $this->view('front/blog/show', [
            'article'    => $article,
            'related'    => ArticleService::related($article),
            'isFavorite' => FavoriteService::has($userId, 'article', (int) $article['id']),
            'favorites'  => FavoriteService::count('article', (int) $article['id']),
            'comments'   => (int) $article['allow_comments'] === 1 ? CommentService::approvedFor('article', (int) $article['id']) : [],
            'pending'    => $userId !== null && (int) $article['allow_comments'] === 1 ? CommentService::pendingOf($userId, 'article', (int) $article['id']) : [],
        ]);
    }

    /** Dépôt d'un commentaire (membre connecté, modération a priori). */
    public function comment(Request $request, string $slug): Response
    {
        $article = ArticleService::findPublic($slug);
        if ($article === null) {
            throw new HttpException(404);
        }
        if ((int) $article['allow_comments'] !== 1) {
            throw new HttpException(403);
        }
        $data = $this->validate($request, ['body' => 'required|min:3|max:3000', 'parent_id' => 'integer']);
        CommentService::add('article', (int) $article['id'], (int) Auth::id(), (string) $data['body'], !empty($data['parent_id']) ? (int) $data['parent_id'] : null);
        Flash::success(__('blog.comments.pending_notice'));

        return $this->redirect(route('blog.show', ['slug' => $slug]) . '#comments');
    }
}
