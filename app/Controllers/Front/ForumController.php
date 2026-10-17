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
use App\Services\AudioService;
use App\Services\ForumService;

/** Forum public (§4.9) : catégories, sujets, réponses, J'aime, abonnements, signalements, badge professeur. */
final class ForumController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('front/forum/index', [
            'categories' => ForumService::categories(),
            'latest'     => ForumService::topics(null, 1, 10, Auth::id())['data'],
        ]);
    }

    public function category(Request $request, string $slug): Response
    {
        $category = ForumService::category($slug);
        if ($category === null) {
            throw new HttpException(404);
        }
        $slugs = ForumService::categorySlugs((int) $category['id']);
        $alternates = [];
        foreach (Lang::locales() as $code) {
            $alternates[$code] = isset($slugs[$code]) ? route('forum.category', ['slug' => $slugs[$code]], $code) : route('forum.index', [], $code);
        }
        View::share('alternates', $alternates);

        return $this->view('front/forum/category', [
            'category' => $category,
            'result'   => ForumService::topics((int) $category['id'], max(1, (int) $request->query('page', 1)), 20, Auth::id()),
        ]);
    }

    public function topic(Request $request, string $id, string $slug = ''): Response
    {
        $topic = ctype_digit($id) ? ForumService::topic((int) $id) : null;
        if ($topic === null || !ForumService::canView($topic, Auth::user())) {
            throw new HttpException(404);
        }
        if ($slug !== $topic['slug']) {
            return $this->redirect(route('forum.topic', ['id' => $topic['id'], 'slug' => $topic['slug']]), 301);
        }
        ForumService::incrementViews((int) $topic['id']);
        $userId = Auth::id();
        $moderator = Auth::can('forum.moderate');

        return $this->view('front/forum/topic', [
            'topic'      => $topic,
            'posts'      => ForumService::posts((int) $topic['id'], $userId, $moderator),
            'subscribed' => ForumService::isSubscribed($userId, (int) $topic['id']),
            'liked'      => $userId !== null && \App\Core\Database::fetch("SELECT 1 FROM likes WHERE user_id = ? AND likeable_type = 'forum_topic' AND likeable_id = ?", [$userId, (int) $topic['id']]) !== null,
            'canReply'   => $userId !== null && ($topic['status'] === 'open' || $moderator),
            'rulesAccepted' => $userId !== null && !empty(Auth::user()['forum_rules_accepted_at']),
            'canValidate' => Auth::can('forum.validate_answer'),
            'moderator'  => $moderator,
            'categories' => $moderator ? ForumService::categories() : [],
            'maxSeconds' => AudioService::maxSeconds(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('forum.post');

        return $this->view('front/forum/create', [
            'categories'    => ForumService::categories(),
            'selected'      => (string) $request->query('categorie', $request->query('category', '')),
            'rulesAccepted' => !empty(Auth::user()['forum_rules_accepted_at']),
            'moderated'     => ForumService::needsModeration(Auth::user()),
            'maxSeconds'    => AudioService::maxSeconds(),
        ]);
    }

    public function store(Request $request): Response
    {
        $this->authorize('forum.post');
        $user = Auth::user();
        $rules = ['category_id' => 'required|integer|exists:forum_categories,id', 'title' => 'required|min:5|max:200', 'body' => 'required|min:10|max:20000', 'language' => 'required|in:fr,ar,en,bm,other', 'media_uuid' => 'max:36'];
        if (empty($user['forum_rules_accepted_at'])) {
            $rules['accept_rules'] = 'accepted';
        }
        $data = $this->validate($request, $rules);
        $id = ForumService::createTopic($user, ['category_id' => (int) $data['category_id'], 'title' => (string) $data['title'], 'body' => (string) $data['body'], 'language' => (string) $data['language'], 'media_uuid' => $data['media_uuid'] ?? null]);
        $topic = ForumService::topic($id);
        Flash::success(__($topic['status'] === 'pending' ? 'forum.flash.topic_pending' : 'forum.flash.topic_created'));

        return $this->redirect(route('forum.topic', ['id' => $id, 'slug' => $topic['slug']]));
    }

    public function reply(Request $request, string $id): Response
    {
        $this->authorize('forum.post');
        $topic = $this->findTopic($id);
        $user = Auth::user();
        $rules = ['body' => 'required|min:2|max:20000', 'quoted_post_id' => 'integer', 'media_uuid' => 'max:36'];
        if (empty($user['forum_rules_accepted_at'])) {
            $rules['accept_rules'] = 'accepted';
        }
        $data = $this->validate($request, $rules);
        try {
            $postId = ForumService::reply($topic, $user, (string) $data['body'], !empty($data['quoted_post_id']) ? (int) $data['quoted_post_id'] : null, $data['media_uuid'] ?? null);
        } catch (\DomainException) {
            Flash::error(__('forum.flash.locked'));

            return $this->redirect(route('forum.topic', ['id' => $topic['id'], 'slug' => $topic['slug']]));
        }
        $pending = \App\Core\Database::value('SELECT status FROM forum_posts WHERE id = ?', [$postId]) === 'pending';
        Flash::success(__($pending ? 'forum.flash.reply_pending' : 'forum.flash.reply_posted'));

        return $this->redirect(route('forum.topic', ['id' => $topic['id'], 'slug' => $topic['slug']]) . '#post-' . $postId);
    }

    public function like(Request $request): Response
    {
        $this->authorize('likes.toggle');
        $data = $this->validate($request, ['type' => 'required|in:forum_topic,forum_post', 'id' => 'required|integer']);
        try {
            [$liked, $count] = ForumService::toggleLike((int) Auth::id(), (string) $data['type'], (int) $data['id']);
        } catch (\InvalidArgumentException) {
            throw new HttpException(404);
        }
        if ($request->wantsJson()) {
            return $this->json(['liked' => $liked, 'count' => $count]);
        }

        return $this->back($request, route('forum.index'));
    }

    public function subscribe(Request $request, string $id): Response
    {
        $this->authorize('forum.post');
        $topic = $this->findTopic($id);
        $subscribed = ForumService::toggleSubscription((int) Auth::id(), (int) $topic['id']);
        Flash::success(__($subscribed ? 'forum.flash.subscribed' : 'forum.flash.unsubscribed'));

        return $this->redirect(route('forum.topic', ['id' => $topic['id'], 'slug' => $topic['slug']]));
    }

    public function report(Request $request): Response
    {
        $this->authorize('forum.post');
        $data = $this->validate($request, ['type' => 'required|in:forum_topic,forum_post,comment,idea,idea_comment', 'id' => 'required|integer', 'reason' => 'required|in:' . implode(',', ForumService::REPORT_REASONS), 'details' => 'max:500']);
        $created = ForumService::report((int) Auth::id(), (string) $data['type'], (int) $data['id'], (string) $data['reason'], $data['details'] ?: null);
        Flash::add($created ? 'success' : 'info', __($created ? 'forum.flash.reported' : 'forum.flash.already_reported'));

        return $this->back($request, route('forum.index'));
    }

    /** Badge « réponse validée par un professeur » (bascule). */
    public function validate_(Request $request, string $postId): Response
    {
        $this->authorize('forum.validate_answer');
        $post = ctype_digit($postId) ? \App\Core\Database::fetch('SELECT p.*, t.slug FROM forum_posts p JOIN forum_topics t ON t.id = p.topic_id WHERE p.id = ? AND p.deleted_at IS NULL', [(int) $postId]) : null;
        if ($post === null) {
            throw new HttpException(404);
        }
        ForumService::validateAnswer((int) $post['id'], (int) Auth::id(), (int) $post['is_validated'] !== 1);

        return $this->redirect(route('forum.topic', ['id' => $post['topic_id'], 'slug' => $post['slug']]) . '#post-' . $post['id']);
    }

    /** Actions de modération rapide depuis le sujet (épingler, verrouiller, masquer, déplacer, approuver). */
    public function moderate(Request $request, string $id): Response
    {
        $this->authorize('forum.moderate');
        $topic = $this->findTopic($id);
        $data = $this->validate($request, ['action' => 'required|in:pin,unpin,lock,unlock,hide,approve,move', 'category_id' => 'integer']);
        match ($data['action']) {
            'pin'     => ForumService::updateTopic((int) $topic['id'], ['is_pinned' => true], (int) Auth::id()),
            'unpin'   => ForumService::updateTopic((int) $topic['id'], ['is_pinned' => false], (int) Auth::id()),
            'lock'    => ForumService::updateTopic((int) $topic['id'], ['status' => 'locked'], (int) Auth::id()),
            'unlock'  => ForumService::updateTopic((int) $topic['id'], ['status' => 'open'], (int) Auth::id()),
            'hide'    => ForumService::hide('topic', (int) $topic['id'], (int) Auth::id()),
            'approve' => ForumService::approve('topic', (int) $topic['id'], (int) Auth::id()),
            'move'    => ForumService::updateTopic((int) $topic['id'], ['category_id' => (int) ($data['category_id'] ?? 0)], (int) Auth::id()),
        };
        Flash::success(__('admin.common.saved'));

        return $this->redirect(route('forum.topic', ['id' => $topic['id'], 'slug' => $topic['slug']]));
    }

    /** Mes sujets et mes abonnements (Mon espace). */
    public function mine(Request $request): Response
    {
        return $this->view('account/forum', ['topics' => ForumService::topicsOf((int) Auth::id()), 'subscriptions' => ForumService::subscriptionsOf((int) Auth::id())]);
    }

    /** @return array<string, mixed> */
    private function findTopic(string $id): array
    {
        $topic = ctype_digit($id) ? ForumService::topic((int) $id) : null;
        if ($topic === null || !ForumService::canView($topic, Auth::user())) {
            throw new HttpException(404);
        }

        return $topic;
    }
}
