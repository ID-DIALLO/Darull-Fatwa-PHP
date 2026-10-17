<?php
/**
 * Questions à débattre : sujets de forum ouverts par le professeur, et création d'un nouveau sujet.
 *
 * @var \App\Core\View $this
 * @var list<array<string, mixed>> $topics
 * @var list<array<string, mixed>> $categories
 */
$this->extend('layouts/scholar');
$this->section('title', __('scholar.debates.title'));
$this->section('subtitle', __('scholar.debates.intro'));
?>
<?php $this->section('content'); ?>
<div class="df-card mb-3">
    <p class="mb-2"><?= e(__('scholar.debates.help')) ?></p>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-gold" href="<?= e(route('forum.create')) ?>"><i class="bi bi-plus-lg" aria-hidden="true"></i> <?= e(__('scholar.debates.new')) ?></a>
        <a class="btn btn-outline-primary" href="<?= e(route('forum.index')) ?>"><i class="bi bi-chat-square-text" aria-hidden="true"></i> <?= e(__('scholar.debates.open_forum')) ?></a>
    </div>
</div>
<?php if ($topics === []): ?>
<div class="df-card"><p class="text-secondary mb-0"><?= e(__('scholar.debates.empty')) ?></p></div>
<?php else: ?>
<ul class="df-question-list">
    <?php foreach ($topics as $t): ?>
    <li class="df-question-item">
        <a class="df-question-item__link" href="<?= e(route('forum.topic', ['id' => $t['id'], 'slug' => $t['slug']])) ?>">
            <div class="df-question-item__head">
                <span class="df-question-item__ref"><?= e(__('forum.status.' . $t['status'])) ?></span>
                <?php if ($t['status'] === 'pending'): ?><span class="df-badge df-badge--warning"><?= e(__('forum.pending_badge')) ?></span><?php elseif ($t['status'] === 'locked'): ?><span class="df-badge df-badge--secondary"><?= e(__('forum.locked')) ?></span><?php endif ?>
            </div>
            <p class="df-question-item__title" dir="auto"><?= e($t['title']) ?></p>
            <p class="df-question-item__meta">
                <span><i class="bi bi-chat-dots" aria-hidden="true"></i> <?= e(trans_choice('forum.replies_count', (int) $t['replies_count'])) ?></span>
                <span><i class="bi bi-eye" aria-hidden="true"></i> <?= (int) $t['views'] ?></span>
                <span><i class="bi bi-calendar3" aria-hidden="true"></i> <?= e(format_date((string) $t['created_at'])) ?></span>
            </p>
        </a>
    </li>
    <?php endforeach ?>
</ul>
<?php endif ?>
<?php $this->endSection(); ?>
