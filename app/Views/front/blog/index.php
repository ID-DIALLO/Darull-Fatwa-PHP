<?php
/**
 * Blog (§4.7) : article mis en avant, grille, filtres rubrique/tag, recherche, barre latérale.
 *
 * @var \App\Core\View $this
 * @var array{rubrique: string, tag: string, q: string} $filters
 * @var array{data: list<array<string, mixed>>, total: int, page: int, last_page: int} $result
 * @var array<string, mixed>|null $featured
 * @var list<array<string, mixed>> $rubriques
 * @var list<array<string, mixed>> $popular
 * @var list<array<string, mixed>> $tags
 * @var bool $missing
 */
$this->extend('layouts/front');
$this->section('title', e(__('blog.index.title')));
$this->section('description', e(__('blog.index.meta')));
$active = array_filter($filters);
?>
<?php $this->section('content'); ?>
<?= $this->partial('partials/breadcrumb', ['items' => [['label' => __('blog.index.title')]]]) ?>
<?php if ($missing): ?><div class="container pt-3"><div class="alert alert-info" role="status"><i class="bi bi-translate" aria-hidden="true"></i> <?= e(__('blog.index.missing_translation')) ?></div></div><?php endif ?>
<section class="df-section">
    <div class="container">
        <div class="df-page__header mb-4">
            <h1 class="df-title"><?= e(__('blog.index.title')) ?></h1>
            <p class="df-lead df-measure"><?= e(__('blog.index.intro')) ?></p>
        </div>
        <?php if ($featured !== null): ?>
        <article class="df-card df-featured mb-4">
            <a class="df-featured__cover" href="<?= e(route('blog.show', ['slug' => $featured['slug']])) ?>" tabindex="-1" aria-hidden="true"><?php if ($featured['cover_path']): ?><img src="<?= e($featured['cover_path']) ?>" alt="" width="1280" height="720"><?php else: ?><span class="df-article-card__placeholder df-pattern"><i class="bi bi-journal-text" aria-hidden="true"></i></span><?php endif ?></a>
            <div class="df-featured__body">
                <p class="df-badge df-badge-gold mb-2"><i class="bi bi-star-fill" aria-hidden="true"></i> <?= e(__('blog.index.featured')) ?></p>
                <h2 class="df-featured__title"><a href="<?= e(route('blog.show', ['slug' => $featured['slug']])) ?>" dir="auto"><?= e($featured['title']) ?></a></h2>
                <p class="text-secondary" dir="auto"><?= e(excerpt((string) $featured['excerpt'], 220)) ?></p>
                <p class="df-article-card__meta"><?php if ($featured['author_name']): ?><span><i class="bi bi-person" aria-hidden="true"></i> <?= e($featured['author_name']) ?></span><?php endif ?><span><i class="bi bi-calendar3" aria-hidden="true"></i> <?= e(format_date($featured['published_at'])) ?></span><span><i class="bi bi-clock" aria-hidden="true"></i> <?= e(__('blog.show.reading', ['min' => (int) $featured['reading_minutes']])) ?></span></p>
                <a class="btn btn-primary" href="<?= e(route('blog.show', ['slug' => $featured['slug']])) ?>"><?= e(__('front.read_more')) ?></a>
            </div>
        </article>
        <?php endif ?>
        <div class="row g-4">
            <div class="col-12 col-lg-8">
                <form method="get" action="<?= e(route('blog.index')) ?>" class="df-card df-filters mb-3" role="search">
                    <div class="row g-2 align-items-end">
                        <div class="col-12 col-md-5"><label class="form-label" for="q"><?= e(__('blog.index.search')) ?></label><input type="search" class="form-control" id="q" name="q" value="<?= e($filters['q']) ?>"></div>
                        <div class="col-8 col-md-4"><label class="form-label" for="rubrique"><?= e(__('qa.index.rubrique')) ?></label><select class="form-select" id="rubrique" name="rubrique"><option value=""><?= e(__('qa.index.all_rubriques')) ?></option><?php foreach ($rubriques as $r): ?><option value="<?= (int) $r['id'] ?>" <?= $filters['rubrique'] === (string) $r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option><?php endforeach ?></select></div>
                        <?php if ($filters['tag'] !== ''): ?><input type="hidden" name="tag" value="<?= e($filters['tag']) ?>"><?php endif ?>
                        <div class="col-4 col-md-3 d-grid"><button type="submit" class="btn btn-primary"><i class="bi bi-search" aria-hidden="true"></i></button></div>
                    </div>
                    <?php if ($filters['tag'] !== ''): ?><p class="small mt-2 mb-0"><?= e(__('blog.index.tag_filter')) ?> <span class="df-chip">#<?= e($filters['tag']) ?></span> <a href="<?= e(route('blog.index', array_diff_key($active, ['tag' => 1]))) ?>"><?= e(__('admin.common.reset')) ?></a></p><?php endif ?>
                </form>
                <?php if ($result['data'] === []): ?>
                <div class="df-empty"><i class="bi bi-journal-text" aria-hidden="true"></i><p class="mb-0"><?= e($active === [] && $featured === null ? __('blog.index.empty') : __('blog.index.empty_filtered')) ?></p></div>
                <?php else: ?>
                <div class="row g-3"><?php foreach ($result['data'] as $article): ?><div class="col-12 col-md-6"><?= $this->partial('partials/article-card', ['article' => $article]) ?></div><?php endforeach ?></div>
                <div class="mt-4"><?= $this->partial('partials/pagination', ['result' => $result, 'query' => $active]) ?></div>
                <?php endif ?>
            </div>
            <aside class="col-12 col-lg-4">
                <?php if ($popular !== []): ?>
                <div class="df-card mb-3">
                    <h2 class="df-card__title h6"><i class="bi bi-fire" aria-hidden="true"></i> <?= e(__('blog.index.popular')) ?></h2>
                    <ol class="df-list ps-3 mb-0"><?php foreach ($popular as $p): ?><li><a href="<?= e(route('blog.show', ['slug' => $p['slug']])) ?>" dir="auto"><?= e($p['title']) ?></a></li><?php endforeach ?></ol>
                </div>
                <?php endif ?>
                <div class="df-card mb-3">
                    <h2 class="df-card__title h6"><i class="bi bi-grid" aria-hidden="true"></i> <?= e(__('front.rubriques.title')) ?></h2>
                    <ul class="df-chips mb-0"><?php foreach ($rubriques as $r): ?><li><a class="df-chip" href="<?= e(route('blog.index', ['rubrique' => $r['id']])) ?>"><img src="<?= e(asset((string) $r['icon'])) ?>" alt="" width="20" height="20"> <?= e($r['name']) ?></a></li><?php endforeach ?></ul>
                </div>
                <?php if ($tags !== []): ?>
                <div class="df-card mb-3">
                    <h2 class="df-card__title h6"><i class="bi bi-tags" aria-hidden="true"></i> <?= e(__('blog.index.tags')) ?></h2>
                    <ul class="df-chips mb-0"><?php foreach ($tags as $t): ?><li><a class="df-chip" href="<?= e(route('blog.index', ['tag' => $t['slug']])) ?>">#<?= e($t['name']) ?> <small class="text-secondary"><?= (int) $t['n'] ?></small></a></li><?php endforeach ?></ul>
                </div>
                <?php endif ?>
                <?= $this->partial('partials/newsletter-form', ['compact' => true]) ?>
            </aside>
        </div>
    </div>
</section>
<?php $this->endSection(); ?>
