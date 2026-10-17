<?php
/**
 * Base publique de questions-réponses (§4.8) : filtres rubrique, tri, recherche plein texte.
 *
 * @var \App\Core\View $this
 * @var array{rubrique: string, sort: string, q: string} $filters
 * @var array{data: list<array<string, mixed>>, total: int, page: int, last_page: int} $result
 * @var list<array<string, mixed>> $rubriques
 * @var array<int, int> $counts
 */
$this->extend('layouts/front');
$this->section('title', e(__('qa.index.title')));
$this->section('description', e(__('qa.index.meta')));
$active = array_filter($filters, static fn (string $v): bool => $v !== '' && $v !== 'recent');
$request = \App\Core\View::shared('request');
?>
<?php $this->section('content'); ?>
<?= $this->partial('partials/breadcrumb', ['items' => [['label' => __('qa.index.title')]]]) ?>
<?php if ($request instanceof \App\Core\Request && $request->query('missing') !== null): ?><div class="container pt-3"><div class="alert alert-info" role="status"><i class="bi bi-translate" aria-hidden="true"></i> <?= e(__('qa.index.missing_translation')) ?></div></div><?php endif ?>
<section class="df-section">
    <div class="container">
        <div class="df-page__header text-center mb-4">
            <h1 class="df-title"><?= e(__('qa.index.title')) ?></h1>
            <p class="df-lead df-measure mx-auto"><?= e(__('qa.index.intro')) ?></p>
        </div>
        <form method="get" action="<?= e(route('qa.index')) ?>" class="df-card df-filters mb-4" role="search">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-5"><label class="form-label" for="q"><?= e(__('qa.index.search')) ?></label><input type="search" class="form-control" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(__('qa.index.search_placeholder')) ?>"></div>
                <div class="col-6 col-md-3"><label class="form-label" for="rubrique"><?= e(__('qa.index.rubrique')) ?></label><select class="form-select" id="rubrique" name="rubrique"><option value=""><?= e(__('qa.index.all_rubriques')) ?></option><?php foreach ($rubriques as $r): ?><option value="<?= (int) $r['id'] ?>" <?= $filters['rubrique'] === (string) $r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?> (<?= (int) ($counts[(int) $r['id']] ?? 0) ?>)</option><?php endforeach ?></select></div>
                <div class="col-6 col-md-2"><label class="form-label" for="sort"><?= e(__('qa.index.sort')) ?></label><select class="form-select" id="sort" name="sort"><option value="recent" <?= $filters['sort'] === 'recent' ? 'selected' : '' ?>><?= e(__('qa.index.sort_recent')) ?></option><option value="popular" <?= $filters['sort'] === 'popular' ? 'selected' : '' ?>><?= e(__('qa.index.sort_popular')) ?></option></select></div>
                <div class="col-12 col-md-2 d-grid"><button type="submit" class="btn btn-primary"><i class="bi bi-search" aria-hidden="true"></i> <?= e(__('nav.search')) ?></button></div>
            </div>
        </form>
        <?php if ($result['data'] === []): ?>
        <div class="df-empty"><i class="bi bi-patch-question" aria-hidden="true"></i><p><?= e($active === [] ? __('qa.index.empty') : __('qa.index.empty_filtered')) ?></p><?php if (ask_url() !== null): ?><a class="btn btn-gold" href="<?= e(ask_url()) ?>"><i class="bi bi-mic-fill" aria-hidden="true"></i> <?= e(__('nav.ask')) ?></a><?php endif ?></div>
        <?php else: ?>
        <p class="text-secondary small"><?= e(trans_choice('qa.index.count', (int) $result['total'])) ?></p>
        <div class="row g-3">
            <?php foreach ($result['data'] as $qa): ?><div class="col-12 col-md-6 col-lg-4"><?= $this->partial('partials/qa-card', ['qa' => $qa]) ?></div><?php endforeach ?>
        </div>
        <div class="mt-4"><?= $this->partial('partials/pagination', ['result' => $result, 'query' => $active]) ?></div>
        <?php endif ?>
    </div>
</section>
<section class="df-cta df-pattern">
    <div class="container df-cta__inner">
        <div class="df-cta__text"><h2 class="df-cta__title"><?= e(__('qa.index.cta_title')) ?></h2><p class="mb-0"><?= e(__('qa.index.cta_text')) ?></p></div>
        <?php if (ask_url() !== null): ?><div class="df-cta__actions"><a class="btn btn-gold btn-lg" href="<?= e(ask_url()) ?>"><i class="bi bi-mic-fill" aria-hidden="true"></i> <?= e(__('nav.ask')) ?></a></div><?php endif ?>
    </div>
</section>
<?php $this->endSection(); ?>
