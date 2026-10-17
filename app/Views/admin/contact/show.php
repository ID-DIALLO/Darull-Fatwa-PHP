<?php
/**
 * Message de contact : lecture, statut, réponse par e-mail.
 *
 * @var \App\Core\View $this
 * @var array<string, mixed> $message
 */
$this->extend('layouts/admin');
$this->section('title', e(__('admin.contact.message_from', ['name' => $message['name']])));
$this->section('subtitle', e(__('front.contact.subjects.' . $message['subject'])) . ' · ' . e(format_date((string) $message['created_at'], true)));
$id = (int) $message['id'];
?>
<?php $this->section('content'); ?>
<div class="row g-3">
    <div class="col-12 col-xl-7">
        <div class="df-card mb-3">
            <dl class="df-dl df-dl--compact">
                <dt><?= e(__('admin.users.col.email')) ?></dt><dd class="ltr"><a href="mailto:<?= e($message['email']) ?>"><?= e($message['email']) ?></a></dd>
                <?php if ($message['phone']): ?><dt><?= e(__('admin.users.col.phone')) ?></dt><dd class="ltr"><?= e($message['phone']) ?></dd><?php endif ?>
                <dt><?= e(__('validation.attributes.locale')) ?></dt><dd><?= e(__('common.lang.' . $message['locale'])) ?></dd>
                <dt><?= e(__('admin.users.col.status')) ?></dt><dd><?= e(__('admin.contact.status.' . $message['status'])) ?><?php if ($message['replied_at']): ?> · <?= e(__('admin.contact.replied_by', ['name' => $message['replied_by_name'] ?? '', 'date' => format_date((string) $message['replied_at'], true)])) ?><?php endif ?></dd>
            </dl>
            <div class="df-prose border-top pt-3" dir="auto"><?= nl2br(e($message['message'])) ?></div>
        </div>
        <form method="post" action="<?= e(route('admin.contact.reply', ['id' => $id])) ?>" class="df-card">
            <?= csrf_field() ?>
            <h2 class="df-card__title h6"><i class="bi bi-reply" aria-hidden="true"></i> <?= e(__('admin.contact.reply')) ?></h2>
            <p class="small text-secondary"><?= e(__('admin.contact.reply_help', ['lang' => __('common.lang.' . $message['locale'])])) ?></p>
            <textarea class="form-control mb-2<?= error_for('body') ? ' is-invalid' : '' ?>" name="body" rows="7" required minlength="5" dir="auto" aria-label="<?= e(__('admin.contact.reply')) ?>"><?= e(old('body')) ?></textarea>
            <?= $this->partial('partials/field-error', ['name' => 'body']) ?>
            <button type="submit" class="btn btn-primary"><i class="bi bi-send" aria-hidden="true"></i> <?= e(__('admin.contact.send_reply')) ?></button>
        </form>
    </div>
    <div class="col-12 col-xl-5">
        <div class="df-card mb-3">
            <h2 class="df-card__title h6"><?= e(__('admin.users.col.status')) ?></h2>
            <form method="post" action="<?= e(route('admin.contact.status', ['id' => $id])) ?>" class="input-group">
                <?= csrf_field() ?>
                <select class="form-select" name="status" aria-label="<?= e(__('admin.users.col.status')) ?>"><?php foreach (['new', 'read', 'replied', 'archived'] as $s): ?><option value="<?= e($s) ?>" <?= $message['status'] === $s ? 'selected' : '' ?>><?= e(__('admin.contact.status.' . $s)) ?></option><?php endforeach ?></select>
                <button type="submit" class="btn btn-outline-primary"><?= e(__('admin.common.change')) ?></button>
            </form>
        </div>
        <div class="df-card df-card--danger">
            <?= $this->partial('admin/partials/post-button', ['url' => route('admin.contact.destroy', ['id' => $id]), 'label' => __('admin.common.delete'), 'icon' => 'trash', 'class' => 'btn btn-sm btn-outline-danger', 'confirm' => __('admin.common.delete_confirm')]) ?>
        </div>
    </div>
</div>
<?php $this->endSection(); ?>
