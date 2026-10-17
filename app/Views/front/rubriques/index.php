<?php
/**
 * Liste des rubriques (§3).
 *
 * @var \App\Core\View $this
 * @var list<array<string, mixed>> $rubriques
 */
$this->extend('layouts/front');
$this->section('title', __('front.rubriques.title'));
$this->section('description', __('front.rubriques.meta'));
?>
<?php $this->section('content'); ?>
<?= $this->partial('partials/breadcrumb', ['items' => [['label' => __('front.rubriques.title')]]]) ?>
<section class="df-section df-section--cream">
    <div class="container">
        <div class="df-section__head">
            <h1 class="df-section__title"><?= e(__('front.rubriques.title')) ?></h1>
            <p class="df-section__subtitle df-measure"><?= e(__('front.rubriques.intro')) ?></p>
        </div>
        <div class="df-rubriques-grid">
            <?php foreach ($rubriques as $rubrique): ?>
            <?= $this->partial('partials/rubrique-card', ['rubrique' => $rubrique]) ?>
            <?php endforeach ?>
        </div>
    </div>
</section>
<?php $this->endSection(); ?>
