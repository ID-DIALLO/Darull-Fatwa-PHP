<?php
/**
 * Choix d'un nouveau mot de passe (lien reçu par e-mail).
 *
 * @var \App\Core\View $this
 * @var string $token
 */
$this->extend('layouts/auth');
$this->section('title', __('auth.reset.title'));
$this->section('subtitle', __('auth.reset.subtitle'));
?>
<?php $this->section('content'); ?>
<form method="post" action="<?= e(route('auth.reset.post', ['token' => $token])) ?>" novalidate>
    <?= csrf_field() ?>
    <?= $this->partial('partials/password-field', ['name' => 'password', 'label' => __('auth.reset.password'), 'autocomplete' => 'new-password', 'strength' => true, 'help' => __('auth.register.password_help')]) ?>
    <?= $this->partial('partials/password-field', ['name' => 'password_confirmation', 'label' => __('auth.reset.password_confirmation'), 'autocomplete' => 'new-password']) ?>
    <button type="submit" class="btn btn-primary w-100 btn-lg mt-2"><i class="bi bi-shield-check" aria-hidden="true"></i> <?= e(__('auth.reset.submit')) ?></button>
</form>
<?php $this->endSection(); ?>
