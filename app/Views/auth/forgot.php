<?php
/**
 * Mot de passe oublié : envoi d'un lien par e-mail.
 *
 * @var \App\Core\View $this
 */
$this->extend('layouts/auth');
$this->section('title', __('auth.forgot.title'));
$this->section('subtitle', __('auth.forgot.subtitle'));
$whatsapp = (string) (\App\Models\Setting::get('whatsapp_number') ?? config('whatsapp.official_number', ''));
?>
<?php $this->section('content'); ?>
<form method="post" action="<?= e(route('auth.forgot.post')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="mb-4">
        <label class="form-label" for="email"><?= e(__('auth.forgot.email')) ?></label>
        <input type="email" class="form-control<?= error_for('email') !== null ? ' is-invalid' : '' ?>" id="email" name="email"
               value="<?= e(old('email')) ?>" autocomplete="email" inputmode="email" required dir="ltr" autofocus>
        <?= $this->partial('partials/field-error', ['name' => 'email']) ?>
    </div>
    <button type="submit" class="btn btn-primary w-100 btn-lg"><i class="bi bi-envelope" aria-hidden="true"></i> <?= e(__('auth.forgot.submit')) ?></button>
</form>

<div class="df-auth__note mt-4">
    <p class="mb-2"><?= e(__('auth.forgot.no_email')) ?></p>
    <?php if ($whatsapp !== '' && !str_contains($whatsapp, 'X')): ?>
    <a class="btn btn-outline-primary btn-sm" href="https://wa.me/<?= e(\App\Services\PhoneNumber::forWhatsApp($whatsapp)) ?>" target="_blank" rel="noopener"><i class="bi bi-whatsapp" aria-hidden="true"></i> <?= e(__('auth.forgot.whatsapp')) ?></a>
    <?php endif ?>
</div>
<?php $this->endSection(); ?>

<?php $this->section('aside'); ?>
<a href="<?= e(route('auth.login')) ?>"><i class="bi bi-arrow-left icon-dir" aria-hidden="true"></i> <?= e(__('auth.forgot.back')) ?></a>
<?php $this->endSection(); ?>
