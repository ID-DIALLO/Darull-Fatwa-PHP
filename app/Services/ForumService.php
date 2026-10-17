<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Lang;
use App\Models\MediaFile;
use App\Models\Setting;

/**
 * Forum (§4.9) : catégories traduites, sujets, réponses (avec citation), J'aime, abonnements avec
 * notifications, signalements, badge « réponse validée par un professeur », modération a priori des
 * premiers messages d'un nouveau membre, filtre de mots interdits, charte acceptée à la première participation.
 */
final class ForumService
{
    public const REPORT_REASONS = ['spam', 'offensive', 'off_topic', 'misinformation', 'other'];

    /** Nombre de messages (sujets + réponses) relus avant publication pour un nouveau membre. */
    public static function moderatedFirstPosts(): int
    {
        return max(0, (int) (Setting::get('forum_first_posts_moderated') ?? 3));
    }

    /** @return list<array<string, mixed>> */
    public static function categories(?string $locale = null): array
    {
        $locale ??= Lang::getLocale();

        return Database::fetchAll(
            "SELECT c.*, COALESCE(t.name, d.name) AS name, COALESCE(t.slug, d.slug) AS slug, COALESCE(t.description, d.description) AS description,
                    (SELECT COUNT(*) FROM forum_topics ft WHERE ft.category_id = c.id AND ft.status IN ('open','locked') AND ft.deleted_at IS NULL) AS topics_count,
                    (SELECT COUNT(*) FROM forum_posts fp JOIN forum_topics ft2 ON ft2.id = fp.topic_id WHERE ft2.category_id = c.id AND fp.status = 'visible' AND fp.deleted_at IS NULL AND ft2.deleted_at IS NULL) AS posts_count,
                    (SELECT MAX(COALESCE(ft3.last_post_at, ft3.created_at)) FROM forum_topics ft3 WHERE ft3.category_id = c.id AND ft3.status IN ('open','locked') AND ft3.deleted_at IS NULL) AS last_activity_at
             FROM forum_categories c
             LEFT JOIN forum_category_translations t ON t.category_id = c.id AND t.locale = ?
             LEFT JOIN forum_category_translations d ON d.category_id = c.id AND d.locale = ?
             WHERE c.is_active = 1 ORDER BY c.sort_order, c.id",
            [$locale, Lang::getDefault()],
        );
    }

    /** @return array<string, mixed>|null */
    public static function category(string $slug, ?string $locale = null): ?array
    {
        $locale ??= Lang::getLocale();

        return Database::fetch(
            'SELECT c.*, COALESCE(t.name, d.name) AS name, COALESCE(t.slug, d.slug) AS slug, COALESCE(t.description, d.description) AS description
             FROM forum_categories c LEFT JOIN forum_category_translations t ON t.category_id = c.id AND t.locale = ? LEFT JOIN forum_category_translations d ON d.category_id = c.id AND d.locale = ?
             WHERE c.is_active = 1 AND (t.slug = ? OR (t.slug IS NULL AND d.slug = ?)) LIMIT 1',
            [$locale, Lang::getDefault(), $slug, $slug],
        );
    }

    /** @return array<string, string> locale => slug */
    public static function categorySlugs(int $categoryId): array
    {
        $slugs = [];
        foreach (Database::fetchAll('SELECT locale, slug FROM forum_category_translations WHERE category_id = ?', [$categoryId]) as $row) {
            $slugs[(string) $row['locale']] = (string) $row['slug'];
        }

        return $slugs;
    }

    private const TOPIC_SELECT = "SELECT ft.*, u.full_name AS author_name, u.display_name AS author_display_name, u.avatar_path AS author_avatar, r.slug AS author_role,
            lu.display_name AS last_display_name, lu.full_name AS last_full_name,
            (SELECT COUNT(*) FROM forum_posts fp WHERE fp.topic_id = ft.id AND fp.is_validated = 1 AND fp.status = 'visible' AND fp.deleted_at IS NULL) AS validated_count
        FROM forum_topics ft JOIN users u ON u.id = ft.user_id JOIN roles r ON r.id = u.role_id LEFT JOIN users lu ON lu.id = ft.last_post_user_id";

    /**
     * Sujets d'une catégorie (épinglés d'abord) ou de tout le forum.
     *
     * @return array{data: list<array<string, mixed>>, total: int, page: int, per_page: int, last_page: int}
     */
    public static function topics(?int $categoryId, int $page = 1, int $perPage = 20, ?int $viewerId = null): array
    {
        $where = ["ft.deleted_at IS NULL AND (ft.status IN ('open','locked')" . ($viewerId !== null ? ' OR (ft.status = \'pending\' AND ft.user_id = ?)' : '') . ')'];
        $bindings = $viewerId !== null ? [$viewerId] : [];
        if ($categoryId !== null) {
            $where[] = 'ft.category_id = ?';
            $bindings[] = $categoryId;
        }
        $whereSql = ' WHERE ' . implode(' AND ', $where);
        $total = (int) Database::value('SELECT COUNT(*) FROM forum_topics ft' . $whereSql, $bindings);
        $page = max(1, $page);
        $rows = Database::fetchAll(self::TOPIC_SELECT . $whereSql . ' ORDER BY ft.is_pinned DESC, COALESCE(ft.last_post_at, ft.created_at) DESC LIMIT ' . (int) $perPage . ' OFFSET ' . (int) (($page - 1) * $perPage), $bindings);

        return ['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'last_page' => max(1, (int) ceil($total / $perPage))];
    }

    /** @return array<string, mixed>|null */
    public static function topic(int $id): ?array
    {
        $topic = Database::fetch(self::TOPIC_SELECT . ' WHERE ft.id = ? AND ft.deleted_at IS NULL', [$id]);
        if ($topic === null) {
            return null;
        }
        $topic['category'] = Database::fetch(
            'SELECT c.*, COALESCE(t.name, d.name) AS name, COALESCE(t.slug, d.slug) AS slug FROM forum_categories c LEFT JOIN forum_category_translations t ON t.category_id = c.id AND t.locale = ? LEFT JOIN forum_category_translations d ON d.category_id = c.id AND d.locale = ? WHERE c.id = ?',
            [Lang::getLocale(), Lang::getDefault(), (int) $topic['category_id']],
        );
        $topic['media'] = $topic['media_id'] ? MediaFile::find((int) $topic['media_id']) : null;

        return $topic;
    }

    /** Un lecteur peut-il voir ce sujet ? (masqué / en attente : auteur et modérateurs seulement) */
    public static function canView(array $topic, ?array $user): bool
    {
        if (in_array($topic['status'], ['open', 'locked'], true)) {
            return true;
        }
        if ($user === null) {
            return false;
        }

        return (int) $topic['user_id'] === (int) $user['id'] || \App\Core\Auth::can('forum.moderate');
    }

    /**
     * Réponses visibles d'un sujet (+ celles en attente du lecteur), avec auteur, citation et « j'aime » du lecteur.
     *
     * @return list<array<string, mixed>>
     */
    public static function posts(int $topicId, ?int $viewerId, bool $moderator = false): array
    {
        $where = "fp.topic_id = ? AND fp.deleted_at IS NULL AND (fp.status = 'visible'" . ($moderator ? " OR fp.status = 'pending'" : ($viewerId !== null ? " OR (fp.status = 'pending' AND fp.user_id = ?)" : '')) . ')';
        $bindings = [$topicId];
        if (!$moderator && $viewerId !== null) {
            $bindings[] = $viewerId;
        }
        $rows = Database::fetchAll(
            "SELECT fp.*, u.full_name, u.display_name, u.avatar_path, r.slug AS role, v.full_name AS validated_by_name,
                    q.body AS quoted_body, qu.display_name AS quoted_display_name, qu.full_name AS quoted_full_name,
                    mf.uuid AS media_uuid, mf.duration_seconds AS media_duration, mf.mime_type AS media_mime
             FROM forum_posts fp JOIN users u ON u.id = fp.user_id JOIN roles r ON r.id = u.role_id
             LEFT JOIN users v ON v.id = fp.validated_by
             LEFT JOIN forum_posts q ON q.id = fp.quoted_post_id LEFT JOIN users qu ON qu.id = q.user_id
             LEFT JOIN media_files mf ON mf.id = fp.media_id
             WHERE {$where} ORDER BY fp.created_at ASC, fp.id ASC",
            $bindings,
        );
        if ($viewerId !== null && $rows !== []) {
            $liked = array_map('intval', array_column(Database::fetchAll("SELECT likeable_id FROM likes WHERE user_id = ? AND likeable_type = 'forum_post' AND likeable_id IN (" . implode(',', array_map(static fn (array $r): int => (int) $r['id'], $rows)) . ')', [$viewerId]), 'likeable_id'));
            foreach ($rows as &$row) {
                $row['liked'] = in_array((int) $row['id'], $liked, true);
            }
            unset($row);
        }

        return $rows;
    }

    /** Le membre doit-il passer par la modération ? (moins de N contributions publiées) */
    public static function needsModeration(array $user): bool
    {
        if (\App\Core\Auth::can('forum.moderate') || in_array($user['role'] ?? '', ['scholar', 'admin', 'superadmin', 'moderator'], true)) {
            return false;
        }
        $limit = self::moderatedFirstPosts();
        if ($limit === 0) {
            return false;
        }
        $published = (int) Database::value("SELECT (SELECT COUNT(*) FROM forum_topics WHERE user_id = ? AND status IN ('open','locked') AND deleted_at IS NULL) + (SELECT COUNT(*) FROM forum_posts WHERE user_id = ? AND status = 'visible' AND deleted_at IS NULL)", [(int) $user['id'], (int) $user['id']]);

        return $published < $limit;
    }

    /** Le texte contient-il un mot interdit (paramètre « forum_banned_words », un par ligne ou virgule) ? */
    public static function containsBannedWord(string $text): ?string
    {
        $words = preg_split('/[\n,;]+/', (string) Setting::get('forum_banned_words', '')) ?: [];
        $haystack = mb_strtolower($text);
        foreach ($words as $word) {
            $word = mb_strtolower(trim($word));
            if ($word !== '' && mb_strlen($word) >= 2 && mb_strpos($haystack, $word) !== false) {
                return $word;
            }
        }

        return null;
    }

    /**
     * Nouveau sujet. Retourne l'identifiant ; statut « pending » si le membre est en modération ou si un mot interdit apparaît.
     *
     * @param array<string, mixed> $user
     * @param array{category_id: int, title: string, body: string, language: string, media_uuid?: string|null} $data
     */
    public static function createTopic(array $user, array $data): int
    {
        $body = Html::fromPlainText($data['body']);
        $media = self::ownMedia($data['media_uuid'] ?? null, (int) $user['id']);
        $pending = self::needsModeration($user) || self::containsBannedWord($data['title'] . ' ' . $data['body']) !== null;
        $title = mb_substr(trim($data['title']), 0, 200);
        Database::execute(
            'INSERT INTO forum_topics (category_id, user_id, title, slug, body, media_id, language, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [(int) $data['category_id'], (int) $user['id'], $title, SlugService::make($title, 200) ?: 'sujet', $body, $media !== null ? (int) $media['id'] : null, $data['language'], $pending ? 'pending' : 'open'],
        );
        $id = Database::lastInsertId();
        self::acceptRules((int) $user['id']);
        Database::execute('INSERT IGNORE INTO forum_subscriptions (user_id, topic_id) VALUES (?, ?)', [(int) $user['id'], $id]);
        ActivityLog::record('forum.topic_created', 'forum_topic', $id, ['pending' => $pending], (int) $user['id']);
        if ($pending) {
            self::notifyModerators('forum.pending', ['title' => $title], route('admin.forum', ['status' => 'pending']));
        }

        return $id;
    }

    /**
     * Réponse à un sujet ouvert. @param array<string, mixed> $user
     */
    public static function reply(array $topic, array $user, string $text, ?int $quotedPostId, ?string $mediaUuid = null): int
    {
        if ($topic['status'] !== 'open' && !\App\Core\Auth::can('forum.moderate')) {
            throw new \DomainException('locked');
        }
        $body = Html::fromPlainText($text);
        $media = self::ownMedia($mediaUuid, (int) $user['id']);
        if ($body === '' && $media === null) {
            throw new \InvalidArgumentException('empty');
        }
        if ($quotedPostId !== null && Database::fetch("SELECT 1 FROM forum_posts WHERE id = ? AND topic_id = ? AND status = 'visible'", [$quotedPostId, (int) $topic['id']]) === null) {
            $quotedPostId = null;
        }
        $pending = self::needsModeration($user) || self::containsBannedWord($text) !== null;
        Database::execute(
            'INSERT INTO forum_posts (topic_id, user_id, quoted_post_id, body, media_id, status) VALUES (?, ?, ?, ?, ?, ?)',
            [(int) $topic['id'], (int) $user['id'], $quotedPostId, $body, $media !== null ? (int) $media['id'] : null, $pending ? 'pending' : 'visible'],
        );
        $id = Database::lastInsertId();
        self::acceptRules((int) $user['id']);
        Database::execute('INSERT IGNORE INTO forum_subscriptions (user_id, topic_id) VALUES (?, ?)', [(int) $user['id'], (int) $topic['id']]);
        ActivityLog::record('forum.post_created', 'forum_post', $id, ['topic' => (int) $topic['id'], 'pending' => $pending], (int) $user['id']);
        if ($pending) {
            self::notifyModerators('forum.pending', ['title' => (string) $topic['title']], route('admin.forum.posts', ['status' => 'pending']));
        } else {
            self::publishPost($id, (int) $user['id']);
        }

        return $id;
    }

    /** Mise à jour des compteurs du sujet et notification des abonnés (sauf l'auteur) après publication d'une réponse. */
    private static function publishPost(int $postId, int $authorId): void
    {
        $post = Database::fetch('SELECT p.*, t.title, t.slug FROM forum_posts p JOIN forum_topics t ON t.id = p.topic_id WHERE p.id = ?', [$postId]);
        if ($post === null) {
            return;
        }
        $topicId = (int) $post['topic_id'];
        Database::execute(
            "UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_posts WHERE topic_id = ? AND status = 'visible' AND deleted_at IS NULL), last_post_at = ?, last_post_user_id = ? WHERE id = ?",
            [$topicId, $post['created_at'], $authorId, $topicId],
        );
        $subscribers = array_map(static fn (array $r): int => (int) $r['user_id'], Database::fetchAll('SELECT user_id FROM forum_subscriptions WHERE topic_id = ? AND user_id <> ?', [$topicId, $authorId]));
        $author = Database::fetch('SELECT full_name, display_name FROM users WHERE id = ?', [$authorId]) ?? [];
        NotificationService::notifyMany($subscribers, 'forum.reply', ['title' => (string) $post['title'], 'author' => \App\Models\User::publicName($author)], route('forum.topic', ['id' => $topicId, 'slug' => $post['slug']]) . '#post-' . $postId);
    }

    /** Approbation d'un sujet ou d'une réponse en attente (modération). */
    public static function approve(string $type, int $id, int $moderatorId): void
    {
        if ($type === 'topic') {
            $topic = Database::fetch('SELECT * FROM forum_topics WHERE id = ?', [$id]);
            if ($topic === null) {
                return;
            }
            Database::execute("UPDATE forum_topics SET status = 'open' WHERE id = ? AND status IN ('pending','hidden')", [$id]);
            NotificationService::notify((int) $topic['user_id'], 'forum.topic_approved', ['title' => (string) $topic['title']], route('forum.topic', ['id' => $id, 'slug' => $topic['slug']]));
        } else {
            $post = Database::fetch('SELECT * FROM forum_posts WHERE id = ?', [$id]);
            if ($post === null) {
                return;
            }
            $wasPending = $post['status'] === 'pending';
            Database::execute("UPDATE forum_posts SET status = 'visible' WHERE id = ?", [$id]);
            self::publishPost($id, (int) $post['user_id']);
            if ($wasPending) {
                $topic = Database::fetch('SELECT title, slug FROM forum_topics WHERE id = ?', [(int) $post['topic_id']]) ?? [];
                NotificationService::notify((int) $post['user_id'], 'forum.post_approved', ['title' => (string) ($topic['title'] ?? '')], route('forum.topic', ['id' => (int) $post['topic_id'], 'slug' => (string) ($topic['slug'] ?? 'sujet')]) . '#post-' . $id);
            }
        }
        ActivityLog::record('admin.forum.' . $type . '_approved', 'forum_' . $type, $id, [], $moderatorId);
    }

    /** Masquer un sujet ou une réponse. */
    public static function hide(string $type, int $id, int $moderatorId): void
    {
        if ($type === 'topic') {
            Database::execute("UPDATE forum_topics SET status = 'hidden' WHERE id = ?", [$id]);
        } else {
            Database::execute("UPDATE forum_posts SET status = 'hidden' WHERE id = ?", [$id]);
            $topicId = (int) Database::value('SELECT topic_id FROM forum_posts WHERE id = ?', [$id]);
            Database::execute("UPDATE forum_topics SET replies_count = (SELECT COUNT(*) FROM forum_posts WHERE topic_id = ? AND status = 'visible' AND deleted_at IS NULL) WHERE id = ?", [$topicId, $topicId]);
        }
        ActivityLog::record('admin.forum.' . $type . '_hidden', 'forum_' . $type, $id, [], $moderatorId);
    }

    /** Épingler / verrouiller / rouvrir / déplacer un sujet. @param array<string, mixed> $changes */
    public static function updateTopic(int $id, array $changes, int $moderatorId): void
    {
        $sets = [];
        $bindings = [];
        if (array_key_exists('is_pinned', $changes)) {
            $sets[] = 'is_pinned = ?';
            $bindings[] = $changes['is_pinned'] ? 1 : 0;
        }
        if (isset($changes['status']) && in_array($changes['status'], ['open', 'locked', 'hidden', 'pending'], true)) {
            $sets[] = 'status = ?';
            $bindings[] = $changes['status'];
        }
        if (!empty($changes['category_id'])) {
            $sets[] = 'category_id = ?';
            $bindings[] = (int) $changes['category_id'];
        }
        if ($sets === []) {
            return;
        }
        $bindings[] = $id;
        Database::execute('UPDATE forum_topics SET ' . implode(', ', $sets) . ' WHERE id = ?', $bindings);
        ActivityLog::record('admin.forum.topic_updated', 'forum_topic', $id, $changes, $moderatorId);
    }

    /** Badge « réponse validée par un professeur » (bascule). */
    public static function validateAnswer(int $postId, int $scholarId, bool $validated = true): void
    {
        Database::execute('UPDATE forum_posts SET is_validated = ?, validated_by = ? WHERE id = ?', [$validated ? 1 : 0, $validated ? $scholarId : null, $postId]);
        ActivityLog::record($validated ? 'forum.answer_validated' : 'forum.answer_unvalidated', 'forum_post', $postId, [], $scholarId);
        if ($validated) {
            $post = Database::fetch('SELECT p.user_id, p.topic_id, t.title, t.slug FROM forum_posts p JOIN forum_topics t ON t.id = p.topic_id WHERE p.id = ?', [$postId]);
            if ($post !== null && (int) $post['user_id'] !== $scholarId) {
                NotificationService::notify((int) $post['user_id'], 'forum.answer_validated', ['title' => (string) $post['title']], route('forum.topic', ['id' => (int) $post['topic_id'], 'slug' => (string) $post['slug']]) . '#post-' . $postId);
            }
        }
    }

    /** J'aime (bascule) sur un sujet ou une réponse ; retourne [aimé, total]. @return array{0: bool, 1: int} */
    public static function toggleLike(int $userId, string $type, int $id): array
    {
        $table = $type === 'forum_topic' ? 'forum_topics' : 'forum_posts';
        if (!in_array($type, ['forum_topic', 'forum_post'], true) || Database::fetch('SELECT 1 FROM ' . $table . ' WHERE id = ? AND deleted_at IS NULL', [$id]) === null) {
            throw new \InvalidArgumentException('invalid_target');
        }
        $exists = Database::fetch('SELECT 1 FROM likes WHERE user_id = ? AND likeable_type = ? AND likeable_id = ?', [$userId, $type, $id]) !== null;
        if ($exists) {
            Database::execute('DELETE FROM likes WHERE user_id = ? AND likeable_type = ? AND likeable_id = ?', [$userId, $type, $id]);
        } else {
            Database::execute('INSERT IGNORE INTO likes (user_id, likeable_type, likeable_id) VALUES (?, ?, ?)', [$userId, $type, $id]);
        }
        $count = (int) Database::value('SELECT COUNT(*) FROM likes WHERE likeable_type = ? AND likeable_id = ?', [$type, $id]);
        Database::execute('UPDATE ' . $table . ' SET likes_count = ? WHERE id = ?', [$count, $id]);

        return [!$exists, $count];
    }

    /** Abonnement à un sujet (bascule) ; retourne l'état final. */
    public static function toggleSubscription(int $userId, int $topicId): bool
    {
        if (Database::fetch('SELECT 1 FROM forum_subscriptions WHERE user_id = ? AND topic_id = ?', [$userId, $topicId]) !== null) {
            Database::execute('DELETE FROM forum_subscriptions WHERE user_id = ? AND topic_id = ?', [$userId, $topicId]);

            return false;
        }
        Database::execute('INSERT IGNORE INTO forum_subscriptions (user_id, topic_id) VALUES (?, ?)', [$userId, $topicId]);

        return true;
    }

    public static function isSubscribed(?int $userId, int $topicId): bool
    {
        return $userId !== null && Database::fetch('SELECT 1 FROM forum_subscriptions WHERE user_id = ? AND topic_id = ?', [$userId, $topicId]) !== null;
    }

    /** Signalement (un seul ouvert par membre et par cible). */
    public static function report(int $reporterId, string $type, int $id, string $reason, ?string $details): bool
    {
        if (!in_array($type, ['forum_topic', 'forum_post', 'comment', 'idea', 'idea_comment'], true) || !in_array($reason, self::REPORT_REASONS, true)) {
            throw new \InvalidArgumentException('invalid_report');
        }
        if (Database::fetch("SELECT 1 FROM reports WHERE reporter_id = ? AND reportable_type = ? AND reportable_id = ? AND status = 'open'", [$reporterId, $type, $id]) !== null) {
            return false;
        }
        Database::execute('INSERT INTO reports (reporter_id, reportable_type, reportable_id, reason, details) VALUES (?, ?, ?, ?, ?)', [$reporterId, $type, $id, $reason, $details !== null ? mb_substr($details, 0, 500) : null]);
        ActivityLog::record('forum.reported', $type, $id, ['reason' => $reason], $reporterId);
        self::notifyModerators('forum.report', ['reason' => __('forum.report.reasons.' . $reason)], route('admin.reports'));

        return true;
    }

    public static function incrementViews(int $topicId): void
    {
        Database::execute('UPDATE forum_topics SET views = views + 1 WHERE id = ?', [$topicId]);
    }

    public static function acceptRules(int $userId): void
    {
        Database::execute('UPDATE users SET forum_rules_accepted_at = COALESCE(forum_rules_accepted_at, UTC_TIMESTAMP()) WHERE id = ?', [$userId]);
    }

    /** Sujets d'un membre (y compris en attente). @return list<array<string, mixed>> */
    public static function topicsOf(int $userId): array
    {
        return Database::fetchAll(self::TOPIC_SELECT . ' WHERE ft.user_id = ? AND ft.deleted_at IS NULL ORDER BY ft.created_at DESC LIMIT 100', [$userId]);
    }

    /** Sujets suivis par un membre. @return list<array<string, mixed>> */
    public static function subscriptionsOf(int $userId): array
    {
        return Database::fetchAll(self::TOPIC_SELECT . " JOIN forum_subscriptions s ON s.topic_id = ft.id AND s.user_id = ? WHERE ft.deleted_at IS NULL AND ft.status IN ('open','locked') ORDER BY COALESCE(ft.last_post_at, ft.created_at) DESC LIMIT 100", [$userId]);
    }

    /** @param array<string, mixed> $data */
    private static function notifyModerators(string $type, array $data, string $url): void
    {
        $staff = Database::fetchAll("SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug IN ('moderator','admin','superadmin') AND u.status = 'active' AND u.deleted_at IS NULL");
        NotificationService::notifyMany(array_map(static fn (array $u): int => (int) $u['id'], $staff), $type, $data, $url, null);
    }

    /** @return array<string, mixed>|null */
    private static function ownMedia(?string $uuid, int $ownerId): ?array
    {
        if ($uuid === null || $uuid === '') {
            return null;
        }
        $media = MediaFile::findByUuid($uuid);
        if ($media === null || (int) $media['owner_id'] !== $ownerId || MediaFile::isAttached((int) $media['id'])) {
            return null;
        }

        return $media;
    }
}
