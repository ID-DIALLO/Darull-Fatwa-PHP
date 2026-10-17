<?php
/**
 * Sujets d'une catégorie.
 *
 * @var \App\Core\View $this
 * @var array<string, mixed> $category
 * @var array{data: list<array<string, mixed>>, total: int, page: int, last_page: int} $result
 */
$this->extend('layouts/front');
$this->section('title', e($category['name']) . ' — ' . e(__('forum.title')));
$this->section('description', e($category['description'] ?? __('forum.meta')));
?>
<?php $this->section('content'); ?>
<?= $this->partial('partials/breadcrumb', ['items' => [['label' => __('forum.title'), 'url' => route('forum.index')], ['label' => $category['name']]]]) ?>
<section class="df-section">
    <div class="container">
        <?= $this->partial('partials/forum-banner') ?>
        <div class="df-page__header d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
            <div><h1 class="df-title mb-1"><?= e($category['name']) ?></h1><p class="df-lead mb-0"><?= e($category['description'] ?? '') ?></p></div>
            <a class="btn btn-gold" href="<?= e(auth() !== null ? route('forum.create', ['categorie' => $category['id']]) : route('auth.login', ['redirect' => route('forum.create')])) ?>"><i class="bi bi-plus-lg" aria-hidden="true"></i> <?= e(__('forum.new_topic')) ?></a>
        </div>
        <?php if ($result['data'] === []): ?>
        <div class="df-empty"><i class="bi bi-chat-square-text" aria-hidden="true"></i><p class="mb-0"><?= e(__('forum.empty_category')) ?></p></div>
        <?php else: ?>
        <ul class="df-topic-list list-unstyled">
            <?php foreach ($result['data'] as $t): ?>
            <li class="df-topic<?= (int) $t['is_pinned'] === 1 ? ' df-topic--pinned' : '' ?><?= $t['status'] === 'pending' ? ' df-topic--pending' : '' ?>">
                <div class="df-topic__main">
                    <p class="df-topic__title mb-1">
                        <?php if ((int) $t['is_pinned'] === 1): ?><i class="bi bi-pin-angle-fill text-warning" title="<?= e(__('forum.pinned')) ?>"></i> <?php endif ?>
                        <?php if ($t['status'] === 'locked'): ?><i class="bi bi-lock-fill text-secondary" title="<?= e(__('forum.locked')) ?>"></i> <?php endif ?>
                        <a href="<?= e(route('forum.topic', ['id' => $t['id'], 'slug' => $t['slug']])) ?>" dir="auto"><?= e($t['title']) ?></a>
                        <?php if ((int) $t['validated_count'] > 0): ?><span class="df-badge df-badge--success"><i class="bi bi-patch-check-fill" aria-hidden="true"></i> <?= e(__('forum.validated_short')) ?></span><?php endif ?>
                        <?php if ($t['status'] === 'pending'): ?><span class="df-badge df-badge--warning"><?= e(__('forum.pending_badge')) ?></span><?php endif ?>
                    </p>
                    <p class="small text-secondary mb-0"><?= e(\App\Models\User::publicName(['display_name' => $t['author_display_name'], 'full_name' => $t['author_name']])) ?> · <?= e(time_ago((string) $t['created_at'])) ?> · <?= e(__('questions.languages.' . $t['language'])) ?></p>
                </div>
                <div class="df-topic__stats"><span><i class="bi bi-chat-left" aria-hidden="true"></i> <?= (int) $t['replies_count'] ?></span><span><i class="bi bi-eye" aria-hidden="true"></i> <?= (int) $t['views'] ?></span><span><i class="bi bi-hand-thumbs-up" aria-hidden="true"></i> <?= (int) $t['likes_count'] ?></span></div>
                <div class="df-topic__last small text-secondary"><?php if ($t['last_post_at']): ?><?= e(__('forum.last_reply', ['name' => \App\Models\User::publicName(['display_name' => $t['last_display_name'], 'full_name' => $t['last_full_name']]), 'ago' => time_ago((string) $t['last_post_at'])])) ?><?php endif ?></div>
            </li>
            <?php endforeach ?>
        </ul>
        <div class="mt-4"><?= $this->partial('partials/pagination', ['result' => $result]) ?></div>
        <?php endif ?>
    </div>
</section>
<?php $this->endSection(); ?>
