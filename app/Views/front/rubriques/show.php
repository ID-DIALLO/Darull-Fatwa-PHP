<?php
/**
 * Détail d'une rubrique : présentation, sous-rubriques, Q/R publiées, articles, professeurs (§3).
 *
 * @var \App\Core\View $this
 * @var array<string, mixed> $rubrique
 * @var list<array<string, mixed>> $children
 * @var list<array<string, mixed>> $qa
 * @var list<array<string, mixed>> $articles
 * @var list<array<string, mixed>> $scholars
 * @var string|null $fallbackLocale
 */
$this->extend('layouts/front');
$this->section('title', $rubrique['meta_title'] ?: $rubrique['name']);
$this->section('description', $rubrique['meta_description'] ?: $rubrique['short_description']);
$askUrl = ask_url(['rubrique' => $rubrique['code']]);
?>
<?php $this->section('content'); ?>
<?= $this->partial('partials/breadcrumb', ['items' => [['label' => __('front.rubriques.title'), 'url' => route('rubriques.index')], ['label' => $rubrique['name']]]]) ?>
<?= $this->partial('partials/content-fallback', ['fallbackLocale' => $fallbackLocale]) ?>

<section class="df-rubrique-hero" style="--df-rubrique-color: <?= e($rubrique['color'] ?? '#0B5D45') ?>">
    <div class="container">
        <div class="df-rubrique-hero__inner">
            <img class="df-rubrique-hero__icon" src="<?= e(asset((string) $rubrique['icon'])) ?>" alt="" width="88" height="88">
            <div>
                <h1 class="df-rubrique-hero__title"><?= e($rubrique['name']) ?></h1>
                <p class="df-rubrique-hero__text"><?= e($rubrique['short_description']) ?></p>
                <?php if ($askUrl !== null): ?><a class="btn btn-gold" href="<?= e($askUrl) ?>"><i class="bi bi-mic-fill" aria-hidden="true"></i> <?= e(__('front.rubriques.ask_here')) ?></a><?php endif ?>
            </div>
        </div>
    </div>
</section>

<?php if (!empty($rubrique['description'])): ?>
<section class="df-section pb-0">
    <div class="container"><div class="df-prose df-measure"><?= $rubrique['description'] /* HTML purifié à l'enregistrement */ ?></div></div>
</section>
<?php endif ?>

<?php if ($children !== []): ?>
<section class="df-section pb-0">
    <div class="container">
        <h2 class="df-section__title"><?= e(__('front.rubriques.children')) ?></h2>
        <div class="df-rubriques-grid">
            <?php foreach ($children as $child): ?>
            <?= $this->partial('partials/rubrique-card', ['rubrique' => $child]) ?>
            <?php endforeach ?>
        </div>
    </div>
</section>
<?php endif ?>

<section class="df-section">
    <div class="container">
        <h2 class="df-section__title"><?= e(__('front.rubriques.qa_title')) ?></h2>
        <?php if ($qa === []): ?>
        <div class="df-empty"><i class="bi bi-patch-question" aria-hidden="true"></i><p><?= e(__('front.rubriques.empty_qa')) ?></p><?php if ($askUrl !== null): ?><a class="btn btn-outline-primary" href="<?= e($askUrl) ?>"><?= e(__('front.rubriques.ask_here')) ?></a><?php endif ?></div>
        <?php else: ?>
        <div class="row g-3">
            <?php foreach ($qa as $item): ?>
            <div class="col-12 col-md-6 col-lg-4">
                <article class="df-card df-qa-card h-100">
                    <h3 class="df-qa-card__title"><a href="<?= e(route('qa.show', ['slug' => $item['slug']])) ?>" dir="auto"><?= e($item['title']) ?></a></h3>
                    <p class="df-qa-card__excerpt" dir="auto"><?= e(excerpt((string) $item['question_text'], 140)) ?></p>
                    <?php if (!empty($item['audio_media_id'])): ?><span class="df-qa-card__audio"><i class="bi bi-headphones" aria-hidden="true"></i> <?= e(__('home.latest_qa.audio')) ?></span><?php endif ?>
                </article>
            </div>
            <?php endforeach ?>
        </div>
        <?php endif ?>
    </div>
</section>

<section class="df-section df-section--cream">
    <div class="container">
        <h2 class="df-section__title"><?= e(__('front.rubriques.articles_title')) ?></h2>
        <?php if ($articles === []): ?>
        <div class="df-empty"><i class="bi bi-journal-text" aria-hidden="true"></i><p class="mb-0"><?= e(__('front.rubriques.empty_articles')) ?></p></div>
        <?php else: ?>
        <div class="row g-3">
            <?php foreach ($articles as $article): ?>
            <div class="col-12 col-md-6 col-lg-4"><?= $this->partial('partials/article-card', ['article' => $article]) ?></div>
            <?php endforeach ?>
        </div>
        <?php endif ?>
    </div>
</section>

<?php if ($scholars !== []): ?>
<section class="df-section">
    <div class="container">
        <h2 class="df-section__title"><?= e(__('front.rubriques.scholars')) ?></h2>
        <div class="df-scholars-strip">
            <?php foreach ($scholars as $scholar): ?>
            <?= $this->partial('partials/scholar-card', ['scholar' => $scholar]) ?>
            <?php endforeach ?>
        </div>
    </div>
</section>
<?php endif ?>
<?php $this->endSection(); ?>
