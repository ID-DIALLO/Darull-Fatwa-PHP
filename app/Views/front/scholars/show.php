<?php
/**
 * Profil public d'un professeur (§4.5) : photo, titre, biographie, spécialités, langues, nombre de réponses.
 *
 * @var \App\Core\View $this
 * @var array<string, mixed> $scholar
 * @var string|null $fallbackLocale
 */
$this->extend('layouts/front');
$name = trim(($scholar['title'] ? $scholar['title'] . ' ' : '') . $scholar['full_name']);
$this->section('title', $name);
$this->section('description', excerpt((string) ($scholar['bio'] ?? __('front.scholars.intro')), 160));
$photo = $scholar['photo_path'] ?: $scholar['avatar_path'];
$askUrl = route_exists('questions.create') ? route('questions.create') : route('auth.register');
?>
<?php $this->section('content'); ?>
<?= $this->partial('partials/breadcrumb', ['items' => [['label' => __('front.scholars.title'), 'url' => route('scholars.index')], ['label' => $name]]]) ?>
<?= $this->partial('partials/content-fallback', ['fallbackLocale' => $fallbackLocale]) ?>
<section class="df-section">
    <div class="container">
        <div class="row g-4">
            <div class="col-12 col-lg-4">
                <div class="df-card text-center df-scholar-profile">
                    <?php if ($photo): ?>
                    <img class="df-scholar-profile__photo" src="<?= e($photo) ?>" alt="" width="160" height="160">
                    <?php else: ?>
                    <span class="df-avatar df-avatar--initials df-avatar--xl mx-auto"><?= e(mb_strtoupper(mb_substr((string) $scholar['full_name'], 0, 1))) ?></span>
                    <?php endif ?>
                    <h1 class="df-scholar-profile__name h3" dir="auto"><?= e($name) ?></h1>
                    <?php if (!empty($scholar['city']) || !empty($scholar['country'])): ?>
                    <p class="text-secondary"><i class="bi bi-geo-alt" aria-hidden="true"></i> <?= e(trim(($scholar['city'] ?? '') . ' ' . ($scholar['country'] ? \Locale::getDisplayRegion('-' . $scholar['country'], (string) config('app.intl_locales.' . locale(), 'fr_FR')) : ''))) ?></p>
                    <?php endif ?>
                    <p class="df-scholar-profile__count"><i class="bi bi-check2-circle" aria-hidden="true"></i> <?= e(trans_choice('front.scholars.answers', (int) $scholar['answers_count'])) ?></p>
                    <?php if ($scholar['languages_list'] !== []): ?>
                    <p class="mb-2"><strong><?= e(__('front.scholars.languages')) ?> :</strong> <?= e(implode(', ', array_map(static fn (string $l): string => __('front.scholars.lang.' . $l), $scholar['languages_list']))) ?></p>
                    <?php endif ?>
                    <a class="btn btn-gold w-100" href="<?= e($askUrl) ?>"><i class="bi bi-mic-fill" aria-hidden="true"></i> <?= e(__('front.scholars.ask')) ?></a>
                </div>
            </div>
            <div class="col-12 col-lg-8">
                <?php if ($scholar['rubriques'] !== []): ?>
                <h2 class="h5 df-title"><?= e(__('front.scholars.specialties')) ?></h2>
                <ul class="df-chips mb-4">
                    <?php foreach ($scholar['rubriques'] as $rubrique): ?>
                    <li><a class="df-chip" href="<?= e(route('rubriques.show', ['slug' => $rubrique['slug']])) ?>"><img src="<?= e(asset((string) $rubrique['icon'])) ?>" alt="" width="22" height="22"> <?= e($rubrique['name']) ?></a></li>
                    <?php endforeach ?>
                </ul>
                <?php endif ?>
                <h2 class="h5 df-title"><?= e(__('front.scholars.bio')) ?></h2>
                <div class="df-prose" dir="auto"><?= nl2br(e($scholar['bio'] ?? '')) ?></div>
            </div>
        </div>
    </div>
</section>
<?php $this->endSection(); ?>
