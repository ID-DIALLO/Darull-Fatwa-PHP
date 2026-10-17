<?php
/**
 * Sécurité : changement de mot de passe, appareils connectés (§4.4).
 *
 * @var \App\Core\View $this
 * @var array<string, mixed> $user
 */
$this->extend('layouts/account');
$this->section('title', __('account.security.title'));
$this->section('subtitle', __('account.security.subtitle'));
?>
<?php $this->section('content'); ?>
<div class="df-card mb-4">
    <h2 class="df-card__title"><i class="bi bi-key" aria-hidden="true"></i> <?= e(__('account.security.change_password')) ?></h2>
    <form method="post" action="<?= e(route('account.security.password')) ?>" novalidate class="df-form df-measure">
        <?= csrf_field() ?>
        <?= $this->partial('partials/password-field', ['name' => 'current_password', 'label' => __('account.security.current_password'), 'autocomplete' => 'current-password']) ?>
        <?= $this->partial('partials/password-field', ['name' => 'password', 'label' => __('account.security.new_password'), 'autocomplete' => 'new-password', 'strength' => true, 'help' => __('auth.register.password_help')]) ?>
        <?= $this->partial('partials/password-field', ['name' => 'password_confirmation', 'label' => __('account.security.new_password_confirmation'), 'autocomplete' => 'new-password']) ?>
        <button type="submit" class="btn btn-primary"><i class="bi bi-shield-check" aria-hidden="true"></i> <?= e(__('account.security.change')) ?></button>
    </form>
</div>

<div class="df-card">
    <h2 class="df-card__title"><i class="bi bi-devices" aria-hidden="true"></i> <?= e(__('account.security.sessions')) ?></h2>
    <?php if (!empty($user['last_login_at'])): ?>
    <p class="text-secondary"><?= e(__('account.security.last_login')) ?> : <?= e(gregorian_date(new DateTimeImmutable((string) $user['last_login_at'], new DateTimeZone('UTC')))) ?></p>
    <?php endif ?>
    <p class="text-secondary"><?= e(__('account.security.sessions_help')) ?></p>
    <form method="post" action="<?= e(route('account.security.logout_everywhere')) ?>">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-outline-primary"><i class="bi bi-box-arrow-right icon-dir" aria-hidden="true"></i> <?= e(__('account.security.logout_everywhere')) ?></button>
    </form>
</div>
<?php $this->endSection(); ?>
