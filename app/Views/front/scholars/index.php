<?php
/**
 * « Nos professeurs » : grille des profils publics (§4.5).
 *
 * @var \App\Core\View $this
 * @var list<array<string, mixed>> $scholars
 */
$this->extend('layouts/front');
$this->section('title', __('front.scholars.title'));
$this->section('description', __('front.scholars.intro'));
?>
<?php $this->section('content'); ?>
<?= $this->partial('partials/breadcrumb', ['items' => [['label' => __('front.scholars.title')]]]) ?>
<section class="df-section">
    <div class="container">
        <div class="df-section__head">
            <h1 class="df-section__title"><?= e(__('front.scholars.title')) ?></h1>
            <p class="df-section__subtitle df-measure"><?= e(__('front.scholars.intro')) ?></p>
        </div>
        <?php if ($scholars === []): ?>
        <div class="df-empty"><i class="bi bi-mortarboard" aria-hidden="true"></i><p class="mb-0"><?= e(__('front.scholars.empty')) ?></p></div>
        <?php else: ?>
        <div class="df-scholars-grid">
            <?php foreach ($scholars as $scholar): ?>
            <?= $this->partial('partials/scholar-card', ['scholar' => $scholar]) ?>
            <?php endforeach ?>
        </div>
        <?php endif ?>
    </div>
</section>
<?php $this->endSection(); ?>
