<?php
/**
 * Connexion : e-mail ou téléphone + mot de passe, « se souvenir de moi ».
 *
 * @var \App\Core\View $this
 * @var string|null $redirect
 */
$this->extend('layouts/auth');
$this->section('title', __('auth.login.title'));
$this->section('subtitle', $redirect !== null ? __('auth.login.required_notice') : __('auth.login.subtitle'));
?>
<?php $this->section('content'); ?>
<form method="post" action="<?= e(route('auth.login.post')) ?>" novalidate>
    <?= csrf_field() ?>
    <?php if ($redirect !== null): ?><input type="hidden" name="redirect" value="<?= e($redirect) ?>"><?php endif ?>

    <div class="mb-3">
        <label class="form-label" for="identifier"><?= e(__('auth.login.identifier')) ?></label>
        <input type="text" class="form-control<?= error_for('identifier') !== null ? ' is-invalid' : '' ?>" id="identifier" name="identifier"
               value="<?= e(old('identifier')) ?>" autocomplete="username" inputmode="email" required autofocus dir="ltr"
               aria-describedby="identifier-help<?= error_for('identifier') !== null ? ' identifier-error' : '' ?>">
        <div class="form-text" id="identifier-help"><?= e(__('auth.login.identifier_help')) ?></div>
        <?= $this->partial('partials/field-error', ['name' => 'identifier']) ?>
    </div>

    <?= $this->partial('partials/password-field', ['name' => 'password', 'label' => __('auth.login.password'), 'autocomplete' => 'current-password']) ?>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1" <?= old('remember') ? 'checked' : '' ?>>
            <label class="form-check-label" for="remember"><?= e(__('auth.login.remember')) ?></label>
        </div>
        <a href="<?= e(route('auth.forgot')) ?>"><?= e(__('auth.login.forgot')) ?></a>
    </div>

    <button type="submit" class="btn btn-primary w-100 btn-lg"><i class="bi bi-box-arrow-in-right icon-dir" aria-hidden="true"></i> <?= e(__('auth.login.submit')) ?></button>
</form>
<?php $this->endSection(); ?>

<?php $this->section('aside'); ?>
<p class="mb-0"><?= e(__('auth.login.no_account')) ?>
    <a class="fw-semibold" href="<?= e(route('auth.register', $redirect !== null ? ['redirect' => $redirect] : [])) ?>"><?= e(__('auth.login.register_link')) ?></a>
</p>
<?php $this->endSection(); ?>
