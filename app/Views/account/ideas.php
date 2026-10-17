<?php
/** Mes idées. @var \App\Core\View $this @var list<array<string, mixed>> $ideas */
$this->extend('layouts/account');
$this->section('title', __('account.ideas.title'));
$this->section('subtitle', __('account.ideas.intro'));
?>
<?php $this->section('content'); ?>
<p><a class="btn btn-primary" href="<?= e(route('ideas.create')) ?>"><i class="bi bi-lightbulb" aria-hidden="true"></i> <?= e(__('ideas.propose')) ?></a></p>
<?php if ($ideas === []): ?><div class="df-empty"><i class="bi bi-lightbulb" aria-hidden="true"></i><p class="mb-0"><?= e(__('account.ideas.empty')) ?></p></div><?php else: ?>
<ul class="df-question-list">
    <?php foreach ($ideas as $idea): ?>
    <li class="df-question-item"><a class="df-question-item__link" href="<?= e(route('ideas.show', ['id' => $idea['id']])) ?>">
        <div class="df-question-item__head"><span class="df-badge df-badge--<?= ['pending' => 'warning', 'new' => 'info', 'under_review' => 'info', 'accepted' => 'success', 'done' => 'success', 'declined' => 'secondary'][$idea['status']] ?>"><?= e(__('ideas.status.' . $idea['status'])) ?></span><small class="text-secondary"><?= e(time_ago((string) $idea['created_at'])) ?></small></div>
        <p class="df-question-item__title" dir="auto"><?= e($idea['title']) ?></p>
        <p class="df-question-item__meta"><span><i class="bi bi-hand-thumbs-up" aria-hidden="true"></i> <?= (int) $idea['votes_count'] ?></span><span><i class="bi bi-chat-left" aria-hidden="true"></i> <?= (int) $idea['comments_count'] ?></span><?php if ($idea['admin_response']): ?><span class="text-success"><i class="bi bi-patch-check-fill" aria-hidden="true"></i> <?= e(__('ideas.official_response')) ?></span><?php endif ?></p>
    </a></li>
    <?php endforeach ?>
</ul>
<?php endif ?>
<?php $this->endSection(); ?>
