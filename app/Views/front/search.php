<?php
/**
 * Résultats de recherche regroupés (§4.13).
 *
 * @var \App\Core\View $this
 * @var string $query
 * @var array{qa: list<array<string, mixed>>, articles: list<array<string, mixed>>, topics: list<array<string, mixed>>, total: int} $results
 * @var list<string> $words
 */
$this->extend('layouts/front');
$this->section('title', e($query !== '' ? __('search.title_query', ['query' => $query]) : __('search.title')));
$this->section('robots', 'noindex, follow');
?>
<?php $this->section('content'); ?>
<?= $this->partial('partials/breadcrumb', ['items' => [['label' => __('search.title')]]]) ?>
<section class="df-section">
    <div class="container">
        <div class="df-measure-wide mx-auto">
            <h1 class="df-title"><?= e(__('search.title')) ?></h1>
            <form method="get" action="<?= e(route('search')) ?>" role="search" class="df-search mb-4">
                <label class="visually-hidden" for="search-q"><?= e(__('nav.search')) ?></label>
                <div class="input-group input-group-lg">
                    <input type="search" class="form-control" id="search-q" name="q" value="<?= e($query) ?>" placeholder="<?= e(__('nav.search_placeholder')) ?>" minlength="2" maxlength="120" required autofocus>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-search" aria-hidden="true"></i> <span class="d-none d-sm-inline"><?= e(__('nav.search')) ?></span></button>
                </div>
            </form>
            <?php if ($query === ''): ?>
            <p class="text-secondary"><?= e(__('search.hint')) ?></p>
            <?php elseif ($words === []): ?>
            <p class="text-secondary"><?= e(__('search.too_short')) ?></p>
            <?php elseif ($results['total'] === 0): ?>
            <div class="df-empty"><i class="bi bi-search" aria-hidden="true"></i><p><?= e(__('search.empty', ['query' => $query])) ?></p><?php if (ask_url() !== null): ?><a class="btn btn-gold" href="<?= e(ask_url()) ?>"><i class="bi bi-mic-fill" aria-hidden="true"></i> <?= e(__('search.ask_instead')) ?></a><?php endif ?></div>
            <?php else: ?>
            <p class="text-secondary"><?= e(trans_choice('search.count', $results['total'], ['query' => $query])) ?></p>
            <?php if ($results['qa'] !== []): ?>
            <h2 class="h4 mt-4"><i class="bi bi-patch-question" aria-hidden="true"></i> <?= e(__('search.group_qa')) ?> <small class="text-secondary">(<?= count($results['qa']) ?>)</small></h2>
            <ul class="df-results list-unstyled">
                <?php foreach ($results['qa'] as $r): ?><li class="df-result"><a class="df-result__title" href="<?= e(route('qa.show', ['slug' => $r['slug']])) ?>" dir="auto"><?= e($r['title']) ?></a><p class="df-result__excerpt" dir="auto"><?= e(excerpt((string) $r['question_text'], 180)) ?></p><p class="df-result__meta small text-secondary"><?= e($r['rubrique_name']) ?> · <?= e(format_date($r['published_at'])) ?></p></li><?php endforeach ?>
            </ul>
            <p><a href="<?= e(route('qa.index', ['q' => $query])) ?>"><?= e(__('search.more_qa')) ?></a></p>
            <?php endif ?>
            <?php if ($results['articles'] !== []): ?>
            <h2 class="h4 mt-4"><i class="bi bi-journal-text" aria-hidden="true"></i> <?= e(__('search.group_articles')) ?> <small class="text-secondary">(<?= count($results['articles']) ?>)</small></h2>
            <ul class="df-results list-unstyled">
                <?php foreach ($results['articles'] as $r): ?><li class="df-result"><a class="df-result__title" href="<?= e(route('blog.show', ['slug' => $r['slug']])) ?>" dir="auto"><?= e($r['title']) ?></a><p class="df-result__excerpt" dir="auto"><?= e(excerpt((string) $r['excerpt'], 180)) ?></p><p class="df-result__meta small text-secondary"><?= e($r['author_name'] ?? '') ?> · <?= e(format_date($r['published_at'])) ?></p></li><?php endforeach ?>
            </ul>
            <p><a href="<?= e(route('blog.index', ['q' => $query])) ?>"><?= e(__('search.more_articles')) ?></a></p>
            <?php endif ?>
            <?php if ($results['topics'] !== []): ?>
            <h2 class="h4 mt-4"><i class="bi bi-chat-square-text" aria-hidden="true"></i> <?= e(__('search.group_topics')) ?> <small class="text-secondary">(<?= count($results['topics']) ?>)</small></h2>
            <ul class="df-results list-unstyled">
                <?php foreach ($results['topics'] as $r): ?><li class="df-result"><a class="df-result__title" href="<?= e(route('forum.topic', ['slug' => $r['slug']])) ?>" dir="auto"><?= e($r['title']) ?></a><p class="df-result__meta small text-secondary"><?= e(trans_choice('search.replies', (int) $r['replies_count'])) ?> · <?= e(format_date($r['created_at'])) ?></p></li><?php endforeach ?>
            </ul>
            <?php endif ?>
            <?php endif ?>
        </div>
    </div>
</section>
<?php $this->endSection(); ?>
