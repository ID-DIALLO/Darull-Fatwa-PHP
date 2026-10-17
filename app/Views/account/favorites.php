<?php
/**
 * Mes favoris (§4.4).
 *
 * @var \App\Core\View $this
 * @var list<array{type: string, id: int, title: string, url: string, rubrique: string|null, created_at: string}> $items
 */
$this->extend('layouts/account');
$this->section('title', __('account.favorites.title'));
$this->section('subtitle', __('account.favorites.intro'));
?>
<?php $this->section('content'); ?>
<?php if ($items === []): ?>
<div class="df-empty"><i class="bi bi-bookmark" aria-hidden="true"></i><p><?= e(__('account.favorites.empty')) ?></p><div class="d-flex flex-wrap gap-2 justify-content-center"><?php if (route_exists('qa.index')): ?><a class="btn btn-outline-primary" href="<?= e(route('qa.index')) ?>"><?= e(__('nav.qa')) ?></a><?php endif ?><?php if (route_exists('blog.index')): ?><a class="btn btn-outline-primary" href="<?= e(route('blog.index')) ?>"><?= e(__('nav.blog')) ?></a><?php endif ?></div></div>
<?php else: ?>
<ul class="df-question-list">
    <?php foreach ($items as $item): ?>
    <li class="df-question-item">
        <div class="df-question-item__link df-question-item__link--static">
            <div class="df-question-item__head"><span class="df-badge df-badge--info"><i class="bi bi-<?= $item['type'] === 'article' ? 'journal-text' : 'patch-question' ?>" aria-hidden="true"></i> <?= e(__('account.favorites.type_' . $item['type'])) ?></span><small class="text-secondary"><?= e(time_ago($item['created_at'])) ?></small></div>
            <p class="df-question-item__title" dir="auto"><a href="<?= e($item['url']) ?>"><?= e($item['title']) ?></a></p>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span class="small text-secondary"><?= e($item['rubrique'] ?? '') ?></span>
                <form method="post" action="<?= e(route('favorites.toggle')) ?>"><?= csrf_field() ?><input type="hidden" name="type" value="<?= e($item['type']) ?>"><input type="hidden" name="id" value="<?= (int) $item['id'] ?>"><button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-bookmark-x" aria-hidden="true"></i> <?= e(__('front.favorites.remove')) ?></button></form>
            </div>
        </div>
    </li>
    <?php endforeach ?>
</ul>
<?php endif ?>
<?php $this->endSection(); ?>
