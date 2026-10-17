<?php
/**
 * Forum : catégories et derniers sujets (§4.9).
 *
 * @var \App\Core\View $this
 * @var list<array<string, mixed>> $categories
 * @var list<array<string, mixed>> $latest
 */
$this->extend('layouts/front');
$this->section('title', e(__('forum.title')));
$this->section('description', e(__('forum.meta')));
?>
<?php $this->section('content'); ?>
<?= $this->partial('partials/breadcrumb', ['items' => [['label' => __('forum.title')]]]) ?>
<section class="df-section">
    <div class="container">
        <?= $this->partial('partials/forum-banner') ?>
        <div class="df-page__header d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
            <div><h1 class="df-title mb-1"><?= e(__('forum.title')) ?></h1><p class="df-lead mb-0"><?= e(__('forum.intro')) ?></p></div>
            <a class="btn btn-gold" href="<?= e(auth() !== null ? route('forum.create') : route('auth.login', ['redirect' => route('forum.create')])) ?>"><i class="bi bi-plus-lg" aria-hidden="true"></i> <?= e(__('forum.new_topic')) ?></a>
        </div>
        <div class="row g-4">
            <div class="col-12 col-lg-8">
                <ul class="df-forum-cats list-unstyled">
                    <?php foreach ($categories as $c): ?>
                    <li class="df-forum-cat">
                        <span class="df-forum-cat__icon"><?php if (str_starts_with((string) $c['icon'], 'bi-')): ?><i class="bi <?= e($c['icon']) ?>" aria-hidden="true"></i><?php else: ?><img src="<?= e(asset((string) $c['icon'])) ?>" alt="" width="32" height="32"><?php endif ?></span>
                        <div class="df-forum-cat__body">
                            <h2 class="df-forum-cat__title h5"><a href="<?= e(route('forum.category', ['slug' => $c['slug']])) ?>"><?= e($c['name']) ?></a></h2>
                            <p class="df-forum-cat__desc mb-0"><?= e($c['description'] ?? '') ?></p>
                        </div>
                        <div class="df-forum-cat__stats">
                            <span><strong><?= (int) $c['topics_count'] ?></strong> <?= e(trans_choice('forum.topics_count_label', (int) $c['topics_count'])) ?></span>
                            <span><strong><?= (int) $c['posts_count'] ?></strong> <?= e(trans_choice('forum.posts_count_label', (int) $c['posts_count'])) ?></span>
                            <?php if ($c['last_activity_at']): ?><small class="text-secondary"><?= e(time_ago((string) $c['last_activity_at'])) ?></small><?php endif ?>
                        </div>
                    </li>
                    <?php endforeach ?>
                </ul>
            </div>
            <aside class="col-12 col-lg-4">
                <div class="df-card">
                    <h2 class="df-card__title h6"><i class="bi bi-clock-history" aria-hidden="true"></i> <?= e(__('forum.latest')) ?></h2>
                    <?php if ($latest === []): ?><p class="text-secondary mb-0"><?= e(__('forum.empty')) ?></p><?php else: ?>
                    <ul class="df-list mb-0">
                        <?php foreach ($latest as $t): ?><li><a href="<?= e(route('forum.topic', ['id' => $t['id'], 'slug' => $t['slug']])) ?>" dir="auto"><?= e($t['title']) ?></a><small><?= e(trans_choice('home.forum_ideas.replies', (int) $t['replies_count'])) ?> · <?= e(time_ago($t['last_post_at'] ?? $t['created_at'])) ?></small></li><?php endforeach ?>
                    </ul>
                    <?php endif ?>
                </div>
            </aside>
        </div>
    </div>
</section>
<?php $this->endSection(); ?>
