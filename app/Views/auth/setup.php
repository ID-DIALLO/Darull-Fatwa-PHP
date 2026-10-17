<?php
/**
 * Mise en service : création du premier super-administrateur (jeton SETUP_TOKEN du fichier .env requis).
 *
 * @var \App\Core\View $this
 * @var string $token
 */
$this->extend('layouts/auth');
$this->section('title', __('auth.setup.title'));
$this->section('subtitle', __('auth.setup.subtitle'));
?>
<?php $this->section('content'); ?>
<form method="post" action="<?= e(route('setup.store')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="mb-3">
        <label class="form-label" for="token"><?= e(__('auth.setup.token')) ?></label>
        <input type="text" class="form-control" id="token" name="token" value="<?= e(old('token', $token)) ?>" required autocomplete="off" dir="ltr" aria-describedby="token-help">
        <div class="form-text" id="token-help"><?= e(__('auth.setup.token_help')) ?></div>
    </div>
    <div class="mb-3">
        <label class="form-label" for="full_name"><?= e(__('auth.setup.full_name')) ?></label>
        <input type="text" class="form-control<?= error_for('full_name') !== null ? ' is-invalid' : '' ?>" id="full_name" name="full_name" value="<?= e(old('full_name')) ?>" required autocomplete="name" aria-describedby="<?= error_for('full_name') !== null ? 'full_name-error' : '' ?>">
        <?= $this->partial('partials/field-error', ['name' => 'full_name']) ?>
    </div>
    <div class="mb-3">
        <label class="form-label" for="email"><?= e(__('auth.setup.email')) ?></label>
        <input type="email" class="form-control<?= error_for('email') !== null ? ' is-invalid' : '' ?>" id="email" name="email" value="<?= e(old('email')) ?>" required autocomplete="email" dir="ltr" aria-describedby="<?= error_for('email') !== null ? 'email-error' : '' ?>">
        <?= $this->partial('partials/field-error', ['name' => 'email']) ?>
    </div>
    <?= $this->partial('partials/password-field', ['name' => 'password', 'label' => __('auth.setup.password'), 'autocomplete' => 'new-password', 'help' => __('auth.setup.password_help')]) ?>
    <?= $this->partial('partials/password-field', ['name' => 'password_confirmation', 'label' => __('auth.setup.password_confirmation'), 'autocomplete' => 'new-password']) ?>
    <button type="submit" class="btn btn-primary w-100 btn-lg"><i class="bi bi-shield-check" aria-hidden="true"></i> <?= e(__('auth.setup.submit')) ?></button>
</form>
<?php $this->endSection(); ?>

<?php $this->section('aside'); ?>
<h2 class="h5"><?= e(__('auth.setup.aside_title')) ?></h2>
<ol class="small mb-0">
    <li><?= e(__('auth.setup.aside_1')) ?></li>
    <li><?= e(__('auth.setup.aside_2')) ?></li>
    <li><?= e(__('auth.setup.aside_3')) ?></li>
</ol>
<?php $this->endSection(); ?>
