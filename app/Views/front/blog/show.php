<?php
/**
 * Article (§4.7) : en-tête, audio, contenu riche, tags, partage, favoris, auteur, articles liés, commentaires, JSON-LD Article.
 *
 * @var \App\Core\View $this
 * @var array<string, mixed> $article
 * @var list<array<string, mixed>> $related
 * @var bool $isFavorite
 * @var int $favorites
 * @var list<array<string, mixed>> $comments
 * @var list<array<string, mixed>> $pending
 */
$this->extend('layouts/front');
$this->section('title', e($article['meta_title'] ?: $article['title']));
$this->section('description', e($article['meta_description'] ?: excerpt((string) $article['excerpt'], 160)));
if ($article['cover_path']) {
    $this->section('og_image', e(url($article['cover_path'])));
}
$url = url(route('blog.show', ['slug' => $article['slug']]));
$authorPublic = (int) ($article['author_public'] ?? 0) === 1 && $article['author_name'];
$jsonLd = ['@context' => 'https://schema.org', '@type' => 'Article', 'headline' => $article['title'], 'datePublished' => $article['published_at'], 'dateModified' => $article['updated_at'], 'inLanguage' => locale(), 'mainEntityOfPage' => $url,
    'author' => ['@type' => $article['author_name'] ? 'Person' : 'Organization', 'name' => $article['author_name'] ?: __('common.site.name')], 'publisher' => ['@type' => 'Organization', 'name' => __('common.site.name')]];
if ($article['cover_path']) {
    $jsonLd['image'] = url($article['cover_path']);
}
$this->section('head');
?>
<script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
<?php $this->endSection(); ?>
<?php $this->section('scripts'); ?>
<script src="<?= e(asset('assets/js/audio-player.js')) ?>" type="module"></script>
<?php $this->endSection(); ?>
<?php $this->section('content'); ?>
<?= $this->partial('partials/breadcrumb', ['items' => array_values(array_filter([['label' => __('blog.index.title'), 'url' => route('blog.index')], $article['rubrique_name'] ? ['label' => $article['rubrique_name'], 'url' => route('blog.index', ['rubrique' => $article['rubrique_id']])] : null, ['label' => excerpt((string) $article['title'], 60)]]))]) ?>
<article class="df-section df-article">
    <div class="container">
        <header class="df-article__header df-measure-wide mx-auto text-center">
            <?php if ($article['rubrique_name']): ?><p class="df-qa-card__rubrique justify-content-center"><a href="<?= e(route('blog.index', ['rubrique' => $article['rubrique_id']])) ?>"><?= e($article['rubrique_name']) ?></a></p><?php endif ?>
            <h1 class="df-title" dir="auto"><?= e($article['title']) ?></h1>
            <p class="df-article-card__meta justify-content-center">
                <?php if ($article['author_name']): ?><span><i class="bi bi-person" aria-hidden="true"></i> <?php if ($authorPublic): ?><a href="<?= e(route('scholars.show', ['slug' => \App\Models\ScholarProfile::slug(['id' => $article['author_id'], 'full_name' => $article['author_name']])])) ?>"><?= e($article['author_name']) ?></a><?php else: ?><?= e($article['author_name']) ?><?php endif ?></span><?php endif ?>
                <span><i class="bi bi-calendar3" aria-hidden="true"></i> <time datetime="<?= e($article['published_at']) ?>"><?= e(format_date($article['published_at'])) ?></time></span>
                <span><i class="bi bi-clock" aria-hidden="true"></i> <?= e(__('blog.show.reading', ['min' => (int) $article['reading_minutes']])) ?></span>
                <span><i class="bi bi-eye" aria-hidden="true"></i> <?= e(trans_choice('qa.show.views', (int) $article['views'])) ?></span>
            </p>
        </header>
        <?php if ($article['cover_path']): ?><figure class="df-article__cover df-measure-wide mx-auto"><img src="<?= e($article['cover_path']) ?>" alt="" width="1280" height="720"></figure><?php endif ?>
        <div class="df-measure-wide mx-auto">
            <?php if (!empty($article['audio_uuid'])): ?>
            <div class="df-card mb-4"><p class="fw-semibold mb-2"><i class="bi bi-headphones" aria-hidden="true"></i> <?= e(__('blog.show.listen')) ?></p><?= $this->partial('partials/audio-player', ['src' => route('media.audio', ['uuid' => $article['audio_uuid']]), 'duration' => $article['audio_duration'] !== null ? (int) $article['audio_duration'] : null, 'mime' => $article['audio_mime'], 'label' => __('blog.show.listen')]) ?></div>
            <?php endif ?>
            <div class="df-prose df-prose--article" dir="auto"><?= $article['content'] /* HTML purifié à l'enregistrement */ ?></div>
            <?php if ($article['tags'] !== []): ?><ul class="df-chips mt-4"><?php foreach ($article['tags'] as $t): ?><li><a class="df-chip" href="<?= e(route('blog.index', ['tag' => $t['slug']])) ?>">#<?= e($t['name']) ?></a></li><?php endforeach ?></ul><?php endif ?>
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mt-4">
                <?= $this->partial('partials/share', ['url' => $url, 'title' => $article['title']]) ?>
                <?= $this->partial('partials/favorite-button', ['type' => 'article', 'id' => (int) $article['id'], 'active' => $isFavorite, 'count' => $favorites]) ?>
            </div>
            <?php if ($authorPublic): ?>
            <aside class="df-card df-author mt-4">
                <?php if ($article['author_photo']): ?><img class="df-avatar df-avatar--lg" src="<?= e($article['author_photo']) ?>" alt="" width="80" height="80"><?php endif ?>
                <div><p class="small text-secondary mb-1"><?= e(__('blog.show.author')) ?></p><p class="h5 mb-1"><a href="<?= e(route('scholars.show', ['slug' => \App\Models\ScholarProfile::slug(['id' => $article['author_id'], 'full_name' => $article['author_name']])])) ?>"><?= e($article['author_name']) ?></a></p><a class="small" href="<?= e(route('scholars.show', ['slug' => \App\Models\ScholarProfile::slug(['id' => $article['author_id'], 'full_name' => $article['author_name']])])) ?>"><?= e(__('front.scholars.profile')) ?></a></div>
            </aside>
            <?php endif ?>
        </div>
    </div>
</article>
<?php if ((int) $article['allow_comments'] === 1): ?>
<?= $this->partial('partials/comments', ['comments' => $comments, 'pending' => $pending, 'action' => route('blog.comment', ['slug' => $article['slug']])]) ?>
<?php endif ?>
<?php if ($related !== []): ?>
<section class="df-section df-section--cream">
    <div class="container">
        <h2 class="df-section__title"><?= e(__('blog.show.related')) ?></h2>
        <div class="row g-3"><?php foreach ($related as $r): ?><div class="col-12 col-md-4"><?= $this->partial('partials/article-card', ['article' => $r]) ?></div><?php endforeach ?></div>
    </div>
</section>
<?php endif ?>
<?php $this->endSection(); ?>
