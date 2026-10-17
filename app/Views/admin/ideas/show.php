<?php
/**
 * Idée : statut, réponse officielle, commentaires à modérer.
 *
 * @var \App\Core\View $this
 * @var array<string, mixed> $idea
 * @var list<array<string, mixed>> $comments
 */
$this->extend('layouts/admin');
$this->section('title', e($idea['title']));
$this->section('subtitle', e(__('ideas.category.' . $idea['category'])) . ' · ' . e($idea['author_name']) . ' · ' . e(format_date((string) $idea['created_at'], true)));
$this->section('actions');
?>
<a class="btn btn-outline-primary" href="<?= e(route('ideas.show', ['id' => $idea['id']])) ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> <?= e(__('admin.common.open')) ?></a>
<?php $this->endSection(); ?>
<?php $this->section('content'); ?>
<div class="row g-3">
    <div class="col-12 col-xl-7">
        <div class="df-card mb-3"><div class="df-prose" dir="auto"><?= nl2br(e($idea['description'])) ?></div><p class="small text-secondary mb-0 mt-2"><i class="bi bi-hand-thumbs-up" aria-hidden="true"></i> <?= (int) $idea['votes_count'] ?> · <i class="bi bi-chat-left" aria-hidden="true"></i> <?= (int) $idea['comments_count'] ?></p></div>
        <div class="df-card">
            <h2 class="df-card__title h6"><?= e(__('admin.comments.title')) ?></h2>
            <?php if ($comments === []): ?><p class="text-secondary mb-0"><?= e(__('admin.common.empty')) ?></p><?php else: ?>
            <ul class="df-list mb-0">
                <?php foreach ($comments as $c): ?>
                <li><span dir="auto"><strong><?= e($c['display_name'] ?: $c['full_name']) ?></strong> : <?= e($c['body']) ?> <span class="df-badge df-badge--<?= ['pending' => 'warning', 'visible' => 'success', 'hidden' => 'secondary'][$c['status']] ?>"><?= e(__('admin.forum.post_status.' . $c['status'])) ?></span></span>
                    <small><?php if ($c['status'] !== 'visible'): ?><?= $this->partial('admin/partials/post-button', ['url' => route('admin.ideas.comment', ['id' => $c['id']]), 'label' => __('forum.moderation.approve'), 'class' => 'btn btn-sm btn-success', 'hidden' => ['status' => 'visible']]) ?><?php endif ?> <?php if ($c['status'] !== 'hidden'): ?><?= $this->partial('admin/partials/post-button', ['url' => route('admin.ideas.comment', ['id' => $c['id']]), 'label' => __('forum.moderation.hide'), 'class' => 'btn btn-sm btn-outline-secondary', 'hidden' => ['status' => 'hidden']]) ?><?php endif ?></small></li>
                <?php endforeach ?>
            </ul>
            <?php endif ?>
        </div>
    </div>
    <div class="col-12 col-xl-5">
        <form method="post" action="<?= e(route('admin.ideas.update', ['id' => $idea['id']])) ?>" class="df-card mb-3">
            <?= csrf_field() ?>
            <h2 class="df-card__title h6"><?= e(__('admin.ideas.decision')) ?></h2>
            <div class="mb-3"><label class="form-label" for="status"><?= e(__('admin.qa.col.status')) ?></label><select class="form-select" id="status" name="status"><?php foreach (\App\Services\IdeaService::STATUSES as $s): ?><option value="<?= e($s) ?>" <?= $idea['status'] === $s ? 'selected' : '' ?>><?= e(__('ideas.status.' . $s)) ?></option><?php endforeach ?></select><div class="form-text"><?= e(__('admin.ideas.status_help')) ?></div></div>
            <div class="mb-3"><label class="form-label" for="admin_response"><?= e(__('ideas.official_response')) ?></label><textarea class="form-control" id="admin_response" name="admin_response" rows="6" dir="auto"><?= e(old('admin_response', $idea['admin_response'] ?? '')) ?></textarea><div class="form-text"><?= e(__('admin.ideas.response_help')) ?></div></div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check2" aria-hidden="true"></i> <?= e(__('common.save')) ?></button>
        </form>
        <div class="df-card df-card--danger"><?= $this->partial('admin/partials/post-button', ['url' => route('admin.ideas.destroy', ['id' => $idea['id']]), 'label' => __('admin.common.delete'), 'icon' => 'trash', 'class' => 'btn btn-sm btn-outline-danger', 'confirm' => __('admin.common.delete_confirm')]) ?></div>
    </div>
</div>
<?php $this->endSection(); ?>
