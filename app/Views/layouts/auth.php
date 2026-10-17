<?php
/**
 * Gabarit des pages d'authentification : carte centrée sur fond crème à motif, logo vertical.
 * Sections attendues : title, content ; facultatives : subtitle, aside (sous la carte).
 *
 * @var \App\Core\View $this
 */
$this->extend('layouts/front');
$this->section('robots', 'noindex, follow');
$this->section('head');
echo $this->yield('head');
?>
<link rel="stylesheet" href="<?= e(asset('vendor/intl-tel-input/css/intlTelInput.min.css')) ?>">
<?php $this->endSection(); ?>

<?php $this->section('scripts'); echo $this->yield('scripts'); ?>
<script src="<?= e(asset('vendor/intl-tel-input/js/intlTelInputWithUtils.min.js')) ?>" defer></script>
<script src="<?= e(asset('assets/js/auth.js')) ?>" type="module"></script>
<?php $this->endSection(); ?>

<?php $this->section('content'); ?>
<section class="df-auth df-pattern">
    <div class="container">
        <div class="df-auth__card df-card">
            <a class="df-auth__logo" href="<?= e(route('home')) ?>">
                <img src="<?= e(asset('assets/img/logo/darul-fatwa-logo-vertical.svg')) ?>" alt="Darul Fatwa – دار الفتوى" width="180" height="163">
            </a>
            <h1 class="df-auth__title"><?= $this->yield('title') ?></h1>
            <?php if ($this->hasSection('subtitle')): ?>
            <p class="df-auth__subtitle"><?= $this->yield('subtitle') ?></p>
            <?php endif ?>
            <?= $this->yield('content') ?>
        </div>
        <?php if ($this->hasSection('aside')): ?>
        <div class="df-auth__aside"><?= $this->yield('aside') ?></div>
        <?php endif ?>
    </div>
</section>
<?php $this->endSection(); ?>
