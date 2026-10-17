<?php
/**
 * Sujet du forum : message initial, réponses (citation, J'aime, badge professeur, signalement), abonnement,
 * formulaire de réponse, actions de modération.
 *
 * @var \App\Core\View $this
 * @var array<string, mixed> $topic
 * @var list<array<string, mixed>> $posts
 * @var bool $subscribed
 * @var bool $liked
 * @var bool $canReply
 * @var bool $rulesAccepted
 * @var bool $canValidate
 * @var bool $moderator
 * @var list<array<string, mixed>> $categories
 * @var int $maxSeconds
 */
$this->extend('layouts/front');
$this->section('title', e($topic['title']));
$this->section('description', e(excerpt((string) $topic['body'], 160)));
if ($topic['status'] !== 'open' && $topic['status'] !== 'locked') {
    $this->section('robots', 'noindex, nofollow');
}
$this->section('scripts'); ?><script src="<?= e(asset('assets/js/audio-player.js')) ?>" type="module"></script><?php if ($canReply): ?><script src="<?= e(asset('assets/js/voice-recorder.js')) ?>" type="module"></script><?php endif ?><?php $this->endSection();
$topicUrl = route('forum.topic', ['id' => $topic['id'], 'slug' => $topic['slug']]);
$author = static fn (array $row, string $display = 'display_name', string $full = 'full_name'): string => \App\Models\User::publicName(['display_name' => $row[$display] ?? null, 'full_name' => $row[$full] ?? '']);
$roleBadge = static function (string $role): string {
    return in_array($role, ['scholar', 'admin', 'superadmin', 'moderator'], true) ? '<span class="df-badge df-badge--' . ($role === 'scholar' ? 'success' : 'info') . '">' . e(__('account.roles.' . $role)) . '</span>' : '';
};
?>
<?php $this->section('content'); ?>
<?= $this->partial('partials/breadcrumb', ['items' => [['label' => __('forum.title'), 'url' => route('forum.index')], ['label' => $topic['category']['name'] ?? '', 'url' => route('forum.category', ['slug' => $topic['category']['slug'] ?? ''])], ['label' => excerpt((string) $topic['title'], 60)]]]) ?>
<section class="df-section">
    <div class="container">
        <?= $this->partial('partials/forum-banner') ?>
        <div class="df-measure-wide mx-auto">
            <?php if ($topic['status'] === 'pending'): ?><div class="alert alert-warning"><i class="bi bi-hourglass-split" aria-hidden="true"></i> <?= e(__('forum.topic_pending_notice')) ?></div><?php elseif ($topic['status'] === 'hidden'): ?><div class="alert alert-danger"><?= e(__('forum.topic_hidden_notice')) ?></div><?php elseif ($topic['status'] === 'locked'): ?><div class="alert alert-secondary"><i class="bi bi-lock-fill" aria-hidden="true"></i> <?= e(__('forum.topic_locked_notice')) ?></div><?php endif ?>
            <article class="df-post df-post--topic" id="topic">
                <header class="df-post__head">
                    <?php if (!empty($topic['author_avatar'])): ?><img class="df-avatar" src="<?= e($topic['author_avatar']) ?>" alt="" width="48" height="48"><?php else: ?><span class="df-avatar df-avatar--initials" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($author($topic, 'author_display_name', 'author_name'), 0, 1))) ?></span><?php endif ?>
                    <div class="flex-grow-1">
                        <h1 class="df-title h2 mb-1" dir="auto"><?php if ((int) $topic['is_pinned'] === 1): ?><i class="bi bi-pin-angle-fill text-warning" title="<?= e(__('forum.pinned')) ?>"></i> <?php endif ?><?= e($topic['title']) ?></h1>
                        <p class="small text-secondary mb-0"><span dir="auto"><?= e($author($topic, 'author_display_name', 'author_name')) ?></span> <?= $roleBadge((string) $topic['author_role']) ?> · <time datetime="<?= e($topic['created_at']) ?>"><?= e(format_date((string) $topic['created_at'], true)) ?></time> · <?= e(__('questions.languages.' . $topic['language'])) ?> · <i class="bi bi-eye" aria-hidden="true"></i> <?= (int) $topic['views'] ?></p>
                    </div>
                </header>
                <?php if (!empty($topic['media'])): ?><div class="mb-3"><?= $this->partial('partials/audio-player', ['src' => route('media.audio', ['uuid' => $topic['media']['uuid']]), 'duration' => $topic['media']['duration_seconds'] !== null ? (int) $topic['media']['duration_seconds'] : null, 'mime' => $topic['media']['mime_type'], 'label' => __('forum.form.voice')]) ?></div><?php endif ?>
                <div class="df-post__body df-prose" dir="auto"><?= $topic['body'] /* HTML échappé à l'enregistrement */ ?></div>
                <footer class="df-post__actions">
                    <?php if (auth() !== null): ?>
                    <form method="post" action="<?= e(route('forum.like')) ?>" class="d-inline" data-like-form><?= csrf_field() ?><input type="hidden" name="type" value="forum_topic"><input type="hidden" name="id" value="<?= (int) $topic['id'] ?>"><button type="submit" class="btn btn-sm <?= $liked ? 'btn-primary' : 'btn-outline-primary' ?>" aria-pressed="<?= $liked ? 'true' : 'false' ?>"><i class="bi bi-hand-thumbs-up<?= $liked ? '-fill' : '' ?>" aria-hidden="true"></i> <?= e(__('forum.like')) ?> <span class="badge text-bg-light" data-like-count><?= (int) $topic['likes_count'] ?></span></button></form>
                    <form method="post" action="<?= e(route('forum.subscribe', ['id' => $topic['id']])) ?>" class="d-inline"><?= csrf_field() ?><button type="submit" class="btn btn-sm <?= $subscribed ? 'btn-gold' : 'btn-outline-secondary' ?>"><i class="bi bi-bell<?= $subscribed ? '-fill' : '' ?>" aria-hidden="true"></i> <?= e(__($subscribed ? 'forum.unsubscribe' : 'forum.subscribe')) ?></button></form>
                    <?php if ($canReply): ?><a class="btn btn-sm btn-outline-primary" href="#reply-form"><i class="bi bi-reply" aria-hidden="true"></i> <?= e(__('forum.reply')) ?></a><?php endif ?>
                    <?php else: ?><span class="small text-secondary"><i class="bi bi-hand-thumbs-up" aria-hidden="true"></i> <?= (int) $topic['likes_count'] ?></span><?php endif ?>
                    <?= $this->partial('partials/share', ['url' => url($topicUrl), 'title' => $topic['title']]) ?>
                    <?php if (auth() !== null && (int) $topic['user_id'] !== (int) auth()['id']): ?><?= $this->partial('partials/report-button', ['type' => 'forum_topic', 'id' => (int) $topic['id']]) ?><?php endif ?>
                </footer>
                <?php if ($moderator): ?>
                <div class="df-moderation">
                    <span class="small fw-semibold"><i class="bi bi-shield" aria-hidden="true"></i> <?= e(__('forum.moderation.title')) ?></span>
                    <?php foreach (array_filter(['approve' => $topic['status'] === 'pending' || $topic['status'] === 'hidden', 'pin' => (int) $topic['is_pinned'] === 0, 'unpin' => (int) $topic['is_pinned'] === 1, 'lock' => $topic['status'] === 'open', 'unlock' => $topic['status'] === 'locked', 'hide' => $topic['status'] !== 'hidden']) as $action => $_): ?>
                    <form method="post" action="<?= e(route('forum.moderate', ['id' => $topic['id']])) ?>" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="<?= e($action) ?>"><button type="submit" class="btn btn-sm btn-outline-secondary"><?= e(__('forum.moderation.' . $action)) ?></button></form>
                    <?php endforeach ?>
                    <form method="post" action="<?= e(route('forum.moderate', ['id' => $topic['id']])) ?>" class="d-inline-flex gap-1"><?= csrf_field() ?><input type="hidden" name="action" value="move"><select class="form-select form-select-sm" name="category_id" aria-label="<?= e(__('forum.moderation.move')) ?>"><?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) $c['id'] === (int) $topic['category_id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach ?></select><button type="submit" class="btn btn-sm btn-outline-secondary"><?= e(__('forum.moderation.move')) ?></button></form>
                </div>
                <?php endif ?>
            </article>

            <h2 class="h4 mt-4" id="replies"><?= e(trans_choice('forum.replies_count', (int) $topic['replies_count'])) ?></h2>
            <?php if ($posts === []): ?><p class="text-secondary"><?= e(__('forum.no_replies')) ?></p><?php endif ?>
            <ol class="df-posts list-unstyled">
                <?php foreach ($posts as $p): ?>
                <li class="df-post<?= (int) $p['is_validated'] === 1 ? ' df-post--validated' : '' ?><?= $p['status'] === 'pending' ? ' df-post--pending' : '' ?>" id="post-<?= (int) $p['id'] ?>">
                    <?php if ((int) $p['is_validated'] === 1): ?><p class="df-post__validated"><i class="bi bi-patch-check-fill" aria-hidden="true"></i> <?= e(__('forum.validated_by', ['name' => $p['validated_by_name'] ?? ''])) ?></p><?php endif ?>
                    <?php if ($p['status'] === 'pending'): ?><p class="df-badge df-badge--warning mb-2"><i class="bi bi-hourglass-split" aria-hidden="true"></i> <?= e(__('forum.pending_badge')) ?></p><?php endif ?>
                    <header class="df-post__head">
                        <?php if (!empty($p['avatar_path'])): ?><img class="df-avatar df-avatar--xs" src="<?= e($p['avatar_path']) ?>" alt="" width="32" height="32"><?php else: ?><span class="df-avatar df-avatar--xs df-avatar--initials" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($author($p), 0, 1))) ?></span><?php endif ?>
                        <span class="fw-semibold" dir="auto"><?= e($author($p)) ?></span> <?= $roleBadge((string) $p['role']) ?>
                        <a class="small text-secondary ms-auto" href="#post-<?= (int) $p['id'] ?>"><time datetime="<?= e($p['created_at']) ?>"><?= e(format_date((string) $p['created_at'], true)) ?></time></a>
                    </header>
                    <?php if ($p['quoted_post_id'] && $p['quoted_body']): ?><blockquote class="df-post__quote" dir="auto"><cite><?= e($author($p, 'quoted_display_name', 'quoted_full_name')) ?></cite><?= excerpt((string) $p['quoted_body'], 300) ?></blockquote><?php endif ?>
                    <?php if (!empty($p['media_uuid'])): ?><div class="mb-2"><?= $this->partial('partials/audio-player', ['src' => route('media.audio', ['uuid' => $p['media_uuid']]), 'duration' => $p['media_duration'] !== null ? (int) $p['media_duration'] : null, 'mime' => $p['media_mime'], 'label' => __('forum.form.voice')]) ?></div><?php endif ?>
                    <div class="df-post__body df-prose" dir="auto"><?= $p['body'] /* HTML échappé à l'enregistrement */ ?></div>
                    <footer class="df-post__actions">
                        <?php if (auth() !== null): ?>
                        <form method="post" action="<?= e(route('forum.like')) ?>" class="d-inline" data-like-form><?= csrf_field() ?><input type="hidden" name="type" value="forum_post"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button type="submit" class="btn btn-sm <?= !empty($p['liked']) ? 'btn-primary' : 'btn-outline-primary' ?>" aria-pressed="<?= !empty($p['liked']) ? 'true' : 'false' ?>"><i class="bi bi-hand-thumbs-up<?= !empty($p['liked']) ? '-fill' : '' ?>" aria-hidden="true"></i> <span class="badge text-bg-light" data-like-count><?= (int) $p['likes_count'] ?></span></button></form>
                        <?php if ($canReply): ?><button type="button" class="btn btn-sm btn-outline-secondary" data-quote-post="<?= (int) $p['id'] ?>" data-quote-author="<?= e($author($p)) ?>"><i class="bi bi-quote" aria-hidden="true"></i> <?= e(__('forum.quote')) ?></button><?php endif ?>
                        <?php if ($canValidate): ?><form method="post" action="<?= e(route('forum.validate', ['postId' => $p['id']])) ?>" class="d-inline"><?= csrf_field() ?><button type="submit" class="btn btn-sm <?= (int) $p['is_validated'] === 1 ? 'btn-success' : 'btn-outline-success' ?>"><i class="bi bi-patch-check" aria-hidden="true"></i> <?= e(__((int) $p['is_validated'] === 1 ? 'forum.unvalidate' : 'forum.validate')) ?></button></form><?php endif ?>
                        <?php if ($moderator): ?><?php foreach (array_filter(['approve' => $p['status'] !== 'visible', 'hide' => $p['status'] !== 'hidden']) as $action => $_): ?><form method="post" action="<?= e(route('admin.forum.post', ['id' => $p['id']])) ?>" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="<?= e($action) ?>"><button type="submit" class="btn btn-sm btn-outline-secondary"><?= e(__('forum.moderation.' . $action)) ?></button></form><?php endforeach ?><?php endif ?>
                        <?php if ((int) $p['user_id'] !== (int) auth()['id']): ?><?= $this->partial('partials/report-button', ['type' => 'forum_post', 'id' => (int) $p['id']]) ?><?php endif ?>
                        <?php else: ?><span class="small text-secondary"><i class="bi bi-hand-thumbs-up" aria-hidden="true"></i> <?= (int) $p['likes_count'] ?></span><?php endif ?>
                    </footer>
                </li>
                <?php endforeach ?>
            </ol>

            <?php if ($canReply): ?>
            <form method="post" action="<?= e(route('forum.reply', ['id' => $topic['id']])) ?>" class="df-card mt-4" id="reply-form" novalidate>
                <?= csrf_field() ?>
                <h2 class="df-card__title h5"><i class="bi bi-reply" aria-hidden="true"></i> <?= e(__('forum.reply')) ?></h2>
                <input type="hidden" name="quoted_post_id" value="" data-quote-field>
                <p class="small text-secondary" data-quote-indicator hidden><?= e(__('forum.quoting')) ?> <strong data-quote-name></strong> <button type="button" class="btn btn-link btn-sm p-0" data-quote-cancel><?= e(__('common.cancel')) ?></button></p>
                <?php if (\App\Services\ForumService::needsModeration(auth())): ?><p class="small text-secondary"><i class="bi bi-shield-check" aria-hidden="true"></i> <?= e(__('forum.moderation_notice', ['count' => \App\Services\ForumService::moderatedFirstPosts()])) ?></p><?php endif ?>
                <label class="form-label" for="reply-body"><?= e(__('forum.form.reply_body')) ?></label>
                <textarea class="form-control<?= error_for('body') ? ' is-invalid' : '' ?>" id="reply-body" name="body" rows="5" required minlength="2" dir="auto"><?= e(old('body')) ?></textarea>
                <?= $this->partial('partials/field-error', ['name' => 'body']) ?>
                <details class="mt-2"><summary class="small"><?= e(__('forum.form.voice')) ?></summary><?= $this->partial('partials/voice-recorder', ['name' => 'media_uuid', 'maxSeconds' => min(180, $maxSeconds)]) ?></details>
                <?php if (!$rulesAccepted): ?><div class="form-check mt-2"><input class="form-check-input<?= error_for('accept_rules') ? ' is-invalid' : '' ?>" type="checkbox" id="accept_rules" name="accept_rules" value="1" required><label class="form-check-label" for="accept_rules"><?= e(__('forum.form.accept_rules')) ?> <a href="<?= e(route('pages.show', ['slug' => __('forum.rules_slug')])) ?>" target="_blank" rel="noopener"><?= e(__('forum.rules_link')) ?></a></label><?= $this->partial('partials/field-error', ['name' => 'accept_rules']) ?></div><?php endif ?>
                <button type="submit" class="btn btn-primary mt-3"><i class="bi bi-send" aria-hidden="true"></i> <?= e(__('forum.form.reply_submit')) ?></button>
            </form>
            <?php elseif (auth() === null): ?>
            <p class="mt-4"><a class="btn btn-outline-primary" href="<?= e(route('auth.login', ['redirect' => $topicUrl])) ?>"><i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> <?= e(__('forum.login_to_reply')) ?></a></p>
            <?php endif ?>
        </div>
    </div>
</section>
<?php $this->endSection(); ?>
