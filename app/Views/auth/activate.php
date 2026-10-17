<?php
/**
 * Activation d'un compte créé par l'administration : identifiant + code reçu + choix du mot de passe.
 *
 * @var \App\Core\View $this
 * @var string $token
 * @var string $role
 */
$this->extend('layouts/auth');
$this->section('title', __('auth.activate.title'));
$this->section('subtitle', __('auth.activate.subtitle', ['role' => __('account.roles.' . $role)]));
?>
<?php $this->section('content'); ?>
<form method="post" action="<?= e(route('auth.activate.post', ['token' => $token])) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="mb-3">
        <label class="form-label" for="identifier"><?= e(__('auth.activate.identifier')) ?></label>
        <input type="text" class="form-control<?= error_for('identifier') !== null ? ' is-invalid' : '' ?>" id="identifier" name="identifier" value="<?= e(old('identifier')) ?>" autocomplete="username" inputmode="email" required autofocus dir="ltr" aria-describedby="identifier-help<?= error_for('identifier') !== null ? ' identifier-error' : '' ?>">
        <div class="form-text" id="identifier-help"><?= e(__('auth.activate.identifier_help')) ?></div>
        <?= $this->partial('partials/field-error', ['name' => 'identifier']) ?>
    </div>
    <div class="mb-3">
        <label class="form-label" for="code"><?= e(__('auth.activate.code')) ?></label>
        <input type="text" class="form-control form-control-lg df-code-input<?= error_for('code') !== null ? ' is-invalid' : '' ?>" id="code" name="code" value="<?= e(old('code')) ?>" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{6,7}" maxlength="7" required dir="ltr" aria-describedby="code-help<?= error_for('code') !== null ? ' code-error' : '' ?>">
        <div class="form-text" id="code-help"><?= e(__('auth.activate.code_help')) ?></div>
        <?= $this->partial('partials/field-error', ['name' => 'code']) ?>
    </div>
    <?= $this->partial('partials/password-field', ['name' => 'password', 'label' => __('auth.activate.password'), 'autocomplete' => 'new-password', 'strength' => true, 'help' => __('auth.setup.password_help')]) ?>
    <?= $this->partial('partials/password-field', ['name' => 'password_confirmation', 'label' => __('auth.activate.password_confirmation'), 'autocomplete' => 'new-password']) ?>
    <button type="submit" class="btn btn-primary w-100 btn-lg mt-2"><i class="bi bi-unlock" aria-hidden="true"></i> <?= e(__('auth.activate.submit')) ?></button>
</form>
<?php $this->endSection(); ?>

<?php $this->section('aside'); ?>
<h2 class="h5"><?= e(__('auth.activate.aside_title')) ?></h2>
<p class="small mb-2"><?= e(__('auth.activate.aside_text')) ?></p>
<p class="small mb-0"><a href="<?= e(route('contact')) ?>"><?= e(__('auth.activate.aside_contact')) ?></a></p>
<?php $this->endSection(); ?>
