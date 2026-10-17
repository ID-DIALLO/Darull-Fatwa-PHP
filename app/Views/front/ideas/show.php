<?php
/**
 * Détail d'une idée : description, vote, réponse officielle, commentaires.
 *
 * @var \App\Core\View $this
 * @var array<string, mixed> $idea
 * @var bool $voted
 * @var list<array<string, mixed>> $comments
 */
$this->extend('layouts/front');
$this->section('title', e($idea['title']));
$this->section('description', e(excerpt((string) $idea['description'], 160)));
if ($idea['status'] === 'pending') {
    $this->section('robots', 'noindex, nofollow');
}
$votable = in_array($idea['status'], ['new', 'under_review', 'accepted'], true);
?>
<?php $this->section('content'); ?>
<?= $this->partial('partials/breadcrumb', ['items' => [['label' => __('ideas.title'), 'url' => route('ideas.index')], ['label' => excerpt((string) $idea['title'], 60)]]]) ?>
<section class="df-section">
    <div class="container">
        <div class="df-measure-wide mx-auto">
            <?php if ($idea['status'] === 'pending'): ?><div class="alert alert-warning"><i class="bi bi-hourglass-split" aria-hidden="true"></i> <?= e(__('ideas.pending_notice')) ?></div><?php endif ?>
            <article class="df-card">
                <div class="df-idea__head mb-2">
                    <span class="df-badge df-badge--<?= ['pending' => 'warning', 'new' => 'info', 'under_review' => 'info', 'accepted' => 'success', 'done' => 'success', 'declined' => 'secondary'][$idea['status']] ?>"><?= e(__('ideas.status.' . $idea['status'])) ?></span>
                    <span class="small text-secondary"><?= e(__('ideas.category.' . $idea['category'])) ?> · <?= e(\App\Models\User::publicName(['display_name' => $idea['author_display_name'], 'full_name' => $idea['author_name']])) ?> · <?= e(format_date((string) $idea['created_at'])) ?></span>
                </div>
                <h1 class="df-title h2" dir="auto"><?= e($idea['title']) ?></h1>
                <div class="df-prose" dir="auto"><?= nl2br(e($idea['description'])) ?></div>
                <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                    <?php if (auth() !== null && $votable): ?>
                    <form method="post" action="<?= e(route('ideas.vote', ['id' => $idea['id']])) ?>" data-vote-form><?= csrf_field() ?><button type="submit" class="btn <?= $voted ? 'btn-primary' : 'btn-outline-primary' ?>" aria-pressed="<?= $voted ? 'true' : 'false' ?>"><i class="bi bi-hand-thumbs-up<?= $voted ? '-fill' : '' ?>" aria-hidden="true"></i> <?= e($voted ? __('ideas.unvote') : __('ideas.vote')) ?> <span class="badge text-bg-light" data-vote-count><?= (int) $idea['votes_count'] ?></span></button></form>
                    <?php elseif (auth() === null && $votable): ?><a class="btn btn-outline-primary" href="<?= e(route('auth.login', ['redirect' => route('ideas.show', ['id' => $idea['id']])])) ?>"><i class="bi bi-hand-thumbs-up" aria-hidden="true"></i> <?= e(__('ideas.login_to_vote')) ?> (<?= (int) $idea['votes_count'] ?>)</a>
                    <?php else: ?><span class="btn btn-outline-secondary disabled"><i class="bi bi-hand-thumbs-up" aria-hidden="true"></i> <?= e(trans_choice('home.forum_ideas.votes', (int) $idea['votes_count'])) ?></span><?php endif ?>
                    <?= $this->partial('partials/share', ['url' => url(route('ideas.show', ['id' => $idea['id']])), 'title' => $idea['title']]) ?>
                    <?php if (auth() !== null && (int) $idea['user_id'] !== (int) auth()['id']): ?><?= $this->partial('partials/report-button', ['type' => 'idea', 'id' => (int) $idea['id']]) ?><?php endif ?>
                </div>
            </article>
            <?php if (!empty($idea['admin_response'])): ?>
            <aside class="df-card df-official mt-3">
                <h2 class="df-card__title h6"><i class="bi bi-patch-check-fill" aria-hidden="true"></i> <?= e(__('ideas.official_response')) ?></h2>
                <div class="df-prose" dir="auto"><?= nl2br(e($idea['admin_response'])) ?></div>
                <p class="small text-secondary mb-0"><?= e($idea['responder_name'] ?? __('common.site.name')) ?> · <?= e(format_date($idea['responded_at'])) ?></p>
            </aside>
            <?php endif ?>
            <section class="mt-4" id="comments">
                <h2 class="h4"><?= e(trans_choice('blog.comments.count', count($comments))) ?></h2>
                <?php if ($comments === []): ?><p class="text-secondary"><?= e(__('blog.comments.empty')) ?></p><?php else: ?>
                <ul class="df-comments list-unstyled">
                    <?php foreach ($comments as $c): ?>
                    <li class="df-comment" id="comment-<?= (int) $c['id'] ?>">
                        <div class="df-comment__head"><span class="df-avatar df-avatar--xs df-avatar--initials" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) $c['full_name'], 0, 1))) ?></span><span class="df-comment__author" dir="auto"><?= e($c['display_name'] ?: $c['full_name']) ?></span><?php if ($c['status'] === 'pending'): ?><span class="df-badge df-badge--warning"><?= e(__('forum.pending_badge')) ?></span><?php endif ?><time class="df-comment__date" datetime="<?= e($c['created_at']) ?>"><?= e(time_ago((string) $c['created_at'])) ?></time></div>
                        <p class="df-comment__body" dir="auto"><?= nl2br(e($c['body'])) ?></p>
                        <?php if (auth() !== null && (int) $c['user_id'] !== (int) auth()['id']): ?><?= $this->partial('partials/report-button', ['type' => 'idea_comment', 'id' => (int) $c['id']]) ?><?php endif ?>
                    </li>
                    <?php endforeach ?>
                </ul>
                <?php endif ?>
                <?php if (auth() !== null && $idea['status'] !== 'pending'): ?>
                <form method="post" action="<?= e(route('ideas.comment', ['id' => $idea['id']])) ?>" class="df-card mt-3">
                    <?= csrf_field() ?>
                    <label class="form-label" for="comment-body"><?= e(__('blog.comments.write')) ?></label>
                    <textarea class="form-control<?= error_for('body') ? ' is-invalid' : '' ?>" id="comment-body" name="body" rows="3" required minlength="3" maxlength="2000" dir="auto"><?= e(old('body')) ?></textarea>
                    <?= $this->partial('partials/field-error', ['name' => 'body']) ?>
                    <button type="submit" class="btn btn-primary mt-2"><i class="bi bi-send" aria-hidden="true"></i> <?= e(__('blog.comments.submit')) ?></button>
                </form>
                <?php elseif (auth() === null): ?><p class="mt-3"><a class="btn btn-outline-primary" href="<?= e(route('auth.login')) ?>"><?= e(__('blog.comments.login')) ?></a></p><?php endif ?>
            </section>
        </div>
    </div>
</section>
<?php $this->endSection(); ?>
