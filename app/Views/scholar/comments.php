<?php
/**
 * Commentaires sous les articles et les Q/R publiques du professeur (lecture ; modération par l'équipe).
 *
 * @var \App\Core\View $this
 * @var list<array<string, mixed>> $comments
 */
$this->extend('layouts/scholar');
$this->section('title', __('scholar.comments.title'));
$this->section('subtitle', __('scholar.comments.intro'));
?>
<?php $this->section('content'); ?>
<?php if ($comments === []): ?>
<div class="df-card"><p class="text-secondary mb-0"><i class="bi bi-chat-left-quote" aria-hidden="true"></i> <?= e(__('scholar.comments.empty')) ?></p></div>
<?php else: ?>
<ul class="df-comment-list list-unstyled mb-0">
    <?php foreach ($comments as $c): ?>
    <li class="df-card mb-2">
        <div class="d-flex flex-wrap justify-content-between gap-2 small text-secondary mb-1">
            <span><i class="bi bi-person" aria-hidden="true"></i> <span dir="auto"><?= e($c['author']) ?></span> · <?= e(format_date((string) $c['created_at'], true)) ?></span>
            <span><?php if ($c['status'] === 'pending'): ?><span class="df-badge df-badge--warning"><?= e(__('scholar.comments.pending')) ?></span><?php endif ?>
                <i class="bi bi-<?= $c['commentable_type'] === 'article' ? 'journal-text' : 'patch-question' ?>" aria-hidden="true"></i>
                <?php if ($c['url']): ?><a href="<?= e($c['url']) ?>#comments" dir="auto"><?= e($c['target_title'] ?? '') ?></a><?php else: ?><span dir="auto"><?= e($c['target_title'] ?? '') ?></span><?php endif ?></span>
        </div>
        <p class="mb-0" dir="auto"><?= nl2br(e($c['body'])) ?></p>
    </li>
    <?php endforeach ?>
</ul>
<?php endif ?>
<p class="small text-secondary mt-3 mb-0"><i class="bi bi-info-circle" aria-hidden="true"></i> <?= e(__('scholar.comments.forum_hint')) ?> <a href="<?= e(route('forum.index')) ?>"><?= e(__('nav.forum')) ?></a></p>
<?php $this->endSection(); ?>
