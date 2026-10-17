<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Response;
use App\Services\ActivityLog;
use App\Services\ForumService;
use App\Services\Translations;

/** Modération du forum (§4.6.3) : sujets, messages en attente, catégories. */
final class ForumController extends AdminController
{
    protected ?string $permission = 'forum.moderate';

    public function index(Request $request): Response
    {
        $status = (string) $request->query('status', '');
        $where = ['ft.deleted_at IS NULL'];
        $bindings = [];
        if (in_array($status, ['pending', 'open', 'locked', 'hidden'], true)) {
            $where[] = 'ft.status = ?';
            $bindings[] = $status;
        }
        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
            $where[] = '(ft.title LIKE ? OR ft.body LIKE ? OR u.full_name LIKE ?)';
            array_push($bindings, $like, $like, $like);
        }
        $sql = ' FROM forum_topics ft JOIN users u ON u.id = ft.user_id LEFT JOIN forum_category_translations ct ON ct.category_id = ft.category_id AND ct.locale = ? WHERE ' . implode(' AND ', $where);
        $page = $this->page($request);
        $total = (int) Database::value('SELECT COUNT(*)' . $sql, array_merge([Lang::getLocale()], $bindings));
        $rows = Database::fetchAll("SELECT ft.*, u.full_name AS author_name, ct.name AS category_name, (SELECT COUNT(*) FROM reports r WHERE r.reportable_type = 'forum_topic' AND r.reportable_id = ft.id AND r.status = 'open') AS open_reports" . $sql . " ORDER BY FIELD(ft.status,'pending') DESC, COALESCE(ft.last_post_at, ft.created_at) DESC LIMIT 25 OFFSET " . (($page - 1) * 25), array_merge([Lang::getLocale()], $bindings));

        return $this->view('admin/forum/topics', [
            'filters' => ['status' => $status, 'q' => $search],
            'result'  => ['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => 25, 'last_page' => max(1, (int) ceil($total / 25))],
            'counts'  => array_column(Database::fetchAll('SELECT status, COUNT(*) AS n FROM forum_topics WHERE deleted_at IS NULL GROUP BY status'), 'n', 'status'),
            'pendingPosts' => (int) Database::value("SELECT COUNT(*) FROM forum_posts WHERE status = 'pending' AND deleted_at IS NULL"),
            'categories' => ForumService::categories(),
        ]);
    }

    public function posts(Request $request): Response
    {
        $status = (string) $request->query('status', 'pending');
        $where = ['fp.deleted_at IS NULL'];
        $bindings = [];
        if (in_array($status, ['pending', 'visible', 'hidden'], true)) {
            $where[] = 'fp.status = ?';
            $bindings[] = $status;
        }
        $sql = ' FROM forum_posts fp JOIN users u ON u.id = fp.user_id JOIN forum_topics ft ON ft.id = fp.topic_id WHERE ' . implode(' AND ', $where);
        $page = $this->page($request);
        $total = (int) Database::value('SELECT COUNT(*)' . $sql, $bindings);
        $rows = Database::fetchAll('SELECT fp.*, u.full_name AS author_name, ft.title AS topic_title, ft.slug AS topic_slug' . $sql . ' ORDER BY fp.created_at DESC LIMIT 25 OFFSET ' . (($page - 1) * 25), $bindings);

        return $this->view('admin/forum/posts', ['status' => $status, 'result' => ['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => 25, 'last_page' => max(1, (int) ceil($total / 25))], 'counts' => array_column(Database::fetchAll('SELECT status, COUNT(*) AS n FROM forum_posts WHERE deleted_at IS NULL GROUP BY status'), 'n', 'status')]);
    }

    public function topic(Request $request, string $id): Response
    {
        $topicId = $this->id($id);
        if (Database::fetch('SELECT 1 FROM forum_topics WHERE id = ?', [$topicId]) === null) {
            throw new HttpException(404);
        }
        $data = $this->validate($request, ['action' => 'required|in:approve,hide,pin,unpin,lock,unlock,move,delete', 'category_id' => 'integer']);
        $me = (int) Auth::id();
        match ($data['action']) {
            'approve' => ForumService::approve('topic', $topicId, $me),
            'hide'    => ForumService::hide('topic', $topicId, $me),
            'pin'     => ForumService::updateTopic($topicId, ['is_pinned' => true], $me),
            'unpin'   => ForumService::updateTopic($topicId, ['is_pinned' => false], $me),
            'lock'    => ForumService::updateTopic($topicId, ['status' => 'locked'], $me),
            'unlock'  => ForumService::updateTopic($topicId, ['status' => 'open'], $me),
            'move'    => ForumService::updateTopic($topicId, ['category_id' => (int) ($data['category_id'] ?? 0)], $me),
            'delete'  => $this->deleteTopic($topicId, $me),
        };

        return $this->back($request, route('admin.forum'));
    }

    private function deleteTopic(int $topicId, int $me): void
    {
        Database::execute('UPDATE forum_topics SET deleted_at = UTC_TIMESTAMP() WHERE id = ?', [$topicId]);
        ActivityLog::record('admin.forum.topic_deleted', 'forum_topic', $topicId, [], $me);
    }

    public function post(Request $request, string $id): Response
    {
        $postId = $this->id($id);
        if (Database::fetch('SELECT 1 FROM forum_posts WHERE id = ?', [$postId]) === null) {
            throw new HttpException(404);
        }
        $data = $this->validate($request, ['action' => 'required|in:approve,hide,delete']);
        $me = (int) Auth::id();
        if ($data['action'] === 'delete') {
            Database::execute('UPDATE forum_posts SET deleted_at = UTC_TIMESTAMP(), status = ? WHERE id = ?', ['hidden', $postId]);
            ActivityLog::record('admin.forum.post_deleted', 'forum_post', $postId, [], $me);
        } else {
            $data['action'] === 'approve' ? ForumService::approve('post', $postId, $me) : ForumService::hide('post', $postId, $me);
        }

        return $this->back($request, route('admin.forum.posts'));
    }

    public function categories(Request $request): Response
    {
        $rows = Database::fetchAll(
            'SELECT c.*, COALESCE(t.name, d.name) AS name, (SELECT GROUP_CONCAT(l.locale ORDER BY l.locale) FROM forum_category_translations l WHERE l.category_id = c.id) AS locales,
                    (SELECT COUNT(*) FROM forum_topics ft WHERE ft.category_id = c.id AND ft.deleted_at IS NULL) AS topics_count
             FROM forum_categories c LEFT JOIN forum_category_translations t ON t.category_id = c.id AND t.locale = ? LEFT JOIN forum_category_translations d ON d.category_id = c.id AND d.locale = ? ORDER BY c.sort_order, c.id',
            [Lang::getLocale(), Lang::getDefault()],
        );
        foreach ($rows as &$row) {
            $row['translations'] = Translations::all('forum_category_translations', 'category_id', (int) $row['id']);
        }
        unset($row);

        return $this->view('admin/forum/categories', ['categories' => $rows, 'rubriques' => \App\Models\Rubrique::allActive()]);
    }

    /** Création ou modification d'une catégorie (nom traduit, rubrique liée, ordre, activation). */
    public function saveCategory(Request $request, string $id = ''): Response
    {
        $this->validate($request, ['name.' . Lang::getDefault() => 'required|max:120', 'sort_order' => 'integer|between:0,999', 'rubrique_id' => 'integer']);
        $attributes = ['rubrique_id' => ctype_digit((string) $request->input('rubrique_id')) ? (int) $request->input('rubrique_id') : null, 'icon' => Translations::text($request->input('icon'), 120) ?? 'bi-chat-dots', 'sort_order' => (int) $request->input('sort_order', 0), 'is_active' => $request->input('is_active') === '1' ? 1 : 0];
        if ($id === '' || $id === 'new') {
            Database::execute('INSERT INTO forum_categories (rubrique_id, icon, sort_order, is_active) VALUES (?, ?, ?, ?)', array_values($attributes));
            $categoryId = Database::lastInsertId();
        } else {
            $categoryId = $this->id($id);
            Database::execute('UPDATE forum_categories SET rubrique_id = ?, icon = ?, sort_order = ?, is_active = ? WHERE id = ?', array_merge(array_values($attributes), [$categoryId]));
        }
        $existing = Translations::all('forum_category_translations', 'category_id', $categoryId);
        $translations = [];
        foreach (Lang::locales() as $locale) {
            $name = Translations::text(((array) $request->input('name', []))[$locale] ?? null, 120);
            $translations[$locale] = $name === null ? ['name' => null] : [
                'name' => $name,
                'slug' => isset($existing[$locale]) && $existing[$locale]['name'] === $name ? $existing[$locale]['slug'] : \App\Services\SlugService::unique($name, 'forum_category_translations', $locale, ['category_id' => $categoryId], 140),
                'description' => Translations::text(((array) $request->input('description', []))[$locale] ?? null, 255),
            ];
        }
        Translations::save('forum_category_translations', 'category_id', $categoryId, $translations, ['name']);
        ActivityLog::record('admin.forum.category_saved', 'forum_category', $categoryId);

        return $this->done('admin.forum.categories');
    }
}
