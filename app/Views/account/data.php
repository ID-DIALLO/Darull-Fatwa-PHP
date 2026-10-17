<?php
/**
 * Mes données : export JSON et suppression du compte (§4.1, §12.2).
 *
 * @var \App\Core\View $this
 * @var array<string, mixed> $user
 */
$this->extend('layouts/account');
$this->section('title', __('account.data.title'));
$this->section('subtitle', __('account.data.subtitle'));
?>
<?php $this->section('content'); ?>
<div class="df-card mb-4">
    <h2 class="df-card__title"><i class="bi bi-download" aria-hidden="true"></i> <?= e(__('account.data.export')) ?></h2>
    <p class="text-secondary"><?= e(__('account.data.export_help')) ?></p>
    <a class="btn btn-outline-primary" href="<?= e(route('account.export')) ?>"><i class="bi bi-file-earmark-arrow-down" aria-hidden="true"></i> <?= e(__('account.data.export_button')) ?></a>
</div>

<div class="df-card df-card--danger">
    <h2 class="df-card__title"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> <?= e(__('account.data.delete')) ?></h2>
    <p class="text-secondary"><?= e(__('account.data.delete_help')) ?></p>
    <form method="post" action="<?= e(route('account.delete')) ?>" novalidate class="df-measure">
        <?= csrf_field() ?>
        <?= $this->partial('partials/password-field', ['name' => 'password', 'label' => __('account.data.delete_password'), 'autocomplete' => 'current-password']) ?>
        <div class="form-check mb-3">
            <input class="form-check-input<?= error_for('confirm') !== null ? ' is-invalid' : '' ?>" type="checkbox" id="confirm" name="confirm" value="1" required>
            <label class="form-check-label" for="confirm"><?= e(__('account.data.delete_confirm_label')) ?></label>
            <?= $this->partial('partials/field-error', ['name' => 'confirm']) ?>
        </div>
        <button type="submit" class="btn btn-danger"><i class="bi bi-trash3" aria-hidden="true"></i> <?= e(__('account.data.delete_button')) ?></button>
    </form>
</div>
<?php $this->endSection(); ?>
