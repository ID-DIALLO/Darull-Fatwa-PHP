<?php
/**
 * Commentaires approuvés + commentaires en attente du membre + formulaire (modération a priori).
 *
 * @var list<array<string, mixed>> $comments
 * @var list<array<string, mixed>> $pending
 * @var string $action URL du formulaire
 */
$byParent = [];
foreach ($comments as $c) {
    $byParent[(int) ($c['parent_id'] ?? 0)][] = $c;
}
$render = static function (array $comment, int $depth) use (&$render, $byParent): void { ?>
    <li class="df-comment<?= $depth > 0 ? ' df-comment--reply' : '' ?>" id="comment-<?= (int) $comment['id'] ?>">
        <div class="df-comment__head">
            <?php if (!empty($comment['avatar_path'])): ?><img class="df-avatar df-avatar--xs" src="<?= e($comment['avatar_path']) ?>" alt="" width="32" height="32"><?php else: ?><span class="df-avatar df-avatar--xs df-avatar--initials" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) $comment['full_name'], 0, 1))) ?></span><?php endif ?>
            <span class="df-comment__author" dir="auto"><?= e($comment['display_name'] ?: $comment['full_name']) ?><?php if (in_array($comment['role'], ['scholar', 'admin', 'superadmin'], true)): ?> <span class="df-badge df-badge--success"><?= e(__('account.roles.' . $comment['role'])) ?></span><?php endif ?></span>
            <time class="df-comment__date" datetime="<?= e($comment['created_at']) ?>"><?= e(time_ago((string) $comment['created_at'])) ?></time>
        </div>
        <p class="df-comment__body" dir="auto"><?= nl2br(e($comment['body'])) ?></p>
        <?php if ($depth === 0 && auth() !== null): ?><button type="button" class="btn btn-link btn-sm p-0 df-comment__reply" data-reply-to="<?= (int) $comment['id'] ?>"><i class="bi bi-reply" aria-hidden="true"></i> <?= e(__('blog.comments.reply')) ?></button><?php endif ?>
        <?php if (!empty($byParent[(int) $comment['id']])): ?><ul class="df-comments list-unstyled mt-2"><?php foreach ($byParent[(int) $comment['id']] as $child) { $render($child, $depth + 1); } ?></ul><?php endif ?>
    </li>
<?php };
?>
<section class="df-section pt-0" id="comments">
    <div class="container">
        <div class="df-measure-wide">
            <h2 class="df-section__title h4"><i class="bi bi-chat-left-text" aria-hidden="true"></i> <?= e(trans_choice('blog.comments.count', count($comments))) ?></h2>
            <?php if ($comments === []): ?><p class="text-secondary"><?= e(__('blog.comments.empty')) ?></p><?php else: ?>
            <ul class="df-comments list-unstyled"><?php foreach ($byParent[0] ?? [] as $comment) { $render($comment, 0); } ?></ul>
            <?php endif ?>
            <?php foreach ($pending as $p): ?>
            <div class="alert alert-warning small"><i class="bi bi-hourglass-split" aria-hidden="true"></i> <?= e(__('blog.comments.your_pending')) ?> <q dir="auto"><?= e(excerpt($p['body'], 120)) ?></q></div>
            <?php endforeach ?>
            <?php if (auth() !== null): ?>
            <form method="post" action="<?= e($action) ?>" class="df-card mt-3" id="comment-form">
                <?= csrf_field() ?>
                <input type="hidden" name="parent_id" value="" data-reply-parent>
                <p class="small text-secondary mb-2" data-reply-indicator hidden><?= e(__('blog.comments.replying')) ?> <button type="button" class="btn btn-link btn-sm p-0" data-reply-cancel><?= e(__('common.cancel')) ?></button></p>
                <label class="form-label" for="comment-body"><?= e(__('blog.comments.write')) ?></label>
                <textarea class="form-control<?= error_for('body') ? ' is-invalid' : '' ?>" id="comment-body" name="body" rows="4" required minlength="3" maxlength="3000" dir="auto"><?= e(old('body')) ?></textarea>
                <?= $this->partial('partials/field-error', ['name' => 'body']) ?>
                <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-send" aria-hidden="true"></i> <?= e(__('blog.comments.submit')) ?></button>
                    <span class="small text-secondary"><?= e(__('blog.comments.moderation_notice')) ?></span>
                </div>
            </form>
            <?php else: ?>
            <p class="mt-3"><a class="btn btn-outline-primary" href="<?= e(route('auth.login')) ?>"><i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> <?= e(__('blog.comments.login')) ?></a></p>
            <?php endif ?>
        </div>
    </div>
</section>
