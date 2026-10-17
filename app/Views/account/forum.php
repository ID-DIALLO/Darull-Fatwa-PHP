<?php
/** Mes sujets et mes abonnements. @var \App\Core\View $this @var list<array<string, mixed>> $topics @var list<array<string, mixed>> $subscriptions */
$this->extend('layouts/account');
$this->section('title', __('account.forum.title'));
$this->section('subtitle', __('account.forum.intro'));
$list = function (array $rows, string $emptyKey): void { ?>
    <?php if ($rows === []): ?><p class="text-secondary"><?= e(__($emptyKey)) ?></p><?php else: ?>
    <ul class="df-question-list">
        <?php foreach ($rows as $t): ?>
        <li class="df-question-item"><a class="df-question-item__link" href="<?= e(route('forum.topic', ['id' => $t['id'], 'slug' => $t['slug']])) ?>">
            <div class="df-question-item__head"><span class="df-badge df-badge--<?= ['pending' => 'warning', 'open' => 'success', 'locked' => 'secondary', 'hidden' => 'danger'][$t['status']] ?>"><?= e(__('forum.status.' . $t['status'])) ?></span><small class="text-secondary"><?= e(time_ago($t['last_post_at'] ?? $t['created_at'])) ?></small></div>
            <p class="df-question-item__title" dir="auto"><?= e($t['title']) ?></p>
            <p class="df-question-item__meta"><span><i class="bi bi-chat-left" aria-hidden="true"></i> <?= (int) $t['replies_count'] ?></span><span><i class="bi bi-eye" aria-hidden="true"></i> <?= (int) $t['views'] ?></span></p>
        </a></li>
        <?php endforeach ?>
    </ul>
    <?php endif ?>
<?php };
?>
<?php $this->section('content'); ?>
<p><a class="btn btn-primary" href="<?= e(route('forum.create')) ?>"><i class="bi bi-plus-lg" aria-hidden="true"></i> <?= e(__('forum.new_topic')) ?></a></p>
<h2 class="h5 mt-3"><?= e(__('account.forum.my_topics')) ?></h2>
<?php $list($topics, 'account.forum.empty_topics') ?>
<h2 class="h5 mt-4"><?= e(__('account.forum.subscriptions')) ?></h2>
<?php $list($subscriptions, 'account.forum.empty_subscriptions') ?>
<?php $this->endSection(); ?>
