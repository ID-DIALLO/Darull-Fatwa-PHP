<?php
/**
 * Statistiques du professeur : chiffres clés, évolution mensuelle, répartition par rubrique, langue, source et type.
 *
 * @var \App\Core\View $this
 * @var array<string, mixed> $stats
 */
$this->extend('layouts/scholar');
$this->section('title', __('scholar.stats.title'));
$this->section('subtitle', __('scholar.stats.intro'));
$this->section('scripts'); ?>
<script src="<?= e(asset('vendor/chart.js/chart.umd.min.js')) ?>" defer></script>
<script src="<?= e(asset('assets/js/scholar-stats.js')) ?>" type="module"></script>
<?php $this->endSection();
$chartData = [
    'labels'   => array_keys($stats['months']),
    'received' => array_values(array_map(static fn (array $m): int => $m['received'], $stats['months'])),
    'answered' => array_values(array_map(static fn (array $m): int => $m['answered'], $stats['months'])),
    'legend'   => ['received' => __('scholar.stats.received'), 'answered' => __('scholar.stats.answered')],
    'rubriques' => ['labels' => array_column($stats['by_rubrique'], 'name'), 'values' => array_map('intval', array_column($stats['by_rubrique'], 'n'))],
];
$cards = [
    ['label' => __('scholar.stats.total'), 'value' => $stats['total'], 'icon' => 'inbox'],
    ['label' => __('scholar.stats.answered'), 'value' => $stats['answered'], 'icon' => 'check2-circle'],
    ['label' => __('scholar.stats.open'), 'value' => $stats['open'], 'icon' => 'arrow-repeat'],
    ['label' => __('scholar.stats.published'), 'value' => $stats['published'], 'icon' => 'globe2'],
    ['label' => __('scholar.stats.requested'), 'value' => $stats['requested'], 'icon' => 'star'],
    ['label' => __('scholar.stats.avg_delay'), 'value' => $stats['avg_hours'] !== null ? __('scholar.stats.hours', ['n' => $stats['avg_hours']]) : '—', 'icon' => 'hourglass-split'],
];
?>
<?php $this->section('content'); ?>
<script type="application/json" id="scholar-stats-data"><?= json_encode($chartData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<div class="row g-3 mb-4">
    <?php foreach ($cards as $card): ?>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="df-card df-stat h-100">
            <i class="bi bi-<?= e($card['icon']) ?> df-stat__icon" aria-hidden="true"></i>
            <p class="df-stat__value mb-0"><?= e((string) $card['value']) ?></p>
            <p class="df-stat__label mb-0"><?= e($card['label']) ?></p>
        </div>
    </div>
    <?php endforeach ?>
</div>
<div class="row g-3">
    <div class="col-12 col-xl-8">
        <section class="df-card h-100">
            <h2 class="df-card__title h6"><i class="bi bi-graph-up" aria-hidden="true"></i> <?= e(__('scholar.stats.monthly')) ?></h2>
            <div class="df-chart" style="height: 280px"><canvas data-chart="monthly" aria-label="<?= e(__('scholar.stats.monthly')) ?>" role="img"></canvas></div>
            <table class="table table-sm df-table mt-3 visually-hidden-focusable">
                <caption class="visually-hidden"><?= e(__('scholar.stats.monthly')) ?></caption>
                <thead><tr><th><?= e(__('scholar.stats.month')) ?></th><th><?= e(__('scholar.stats.received')) ?></th><th><?= e(__('scholar.stats.answered')) ?></th></tr></thead>
                <tbody><?php foreach ($stats['months'] as $month => $m): ?><tr><td><?= e($month) ?></td><td><?= (int) $m['received'] ?></td><td><?= (int) $m['answered'] ?></td></tr><?php endforeach ?></tbody>
            </table>
        </section>
    </div>
    <div class="col-12 col-xl-4">
        <section class="df-card h-100">
            <h2 class="df-card__title h6"><i class="bi bi-pie-chart" aria-hidden="true"></i> <?= e(__('scholar.stats.by_rubrique')) ?></h2>
            <?php if ($stats['by_rubrique'] === []): ?><p class="text-secondary mb-0"><?= e(__('scholar.stats.empty')) ?></p><?php else: ?>
            <div class="df-chart" style="height: 220px"><canvas data-chart="rubriques" aria-label="<?= e(__('scholar.stats.by_rubrique')) ?>" role="img"></canvas></div>
            <ul class="list-unstyled small mb-0 mt-2">
                <?php foreach ($stats['by_rubrique'] as $r): ?><li class="d-flex justify-content-between"><span><?= e($r['name']) ?></span><span><strong><?= (int) $r['n'] ?></strong> · <?= e(__('scholar.stats.answered_short', ['n' => (int) $r['answered']])) ?></span></li><?php endforeach ?>
            </ul>
            <?php endif ?>
        </section>
    </div>
    <div class="col-12 col-md-4">
        <section class="df-card h-100">
            <h2 class="df-card__title h6"><i class="bi bi-translate" aria-hidden="true"></i> <?= e(__('scholar.stats.by_language')) ?></h2>
            <?php if ($stats['by_language'] === []): ?><p class="text-secondary mb-0"><?= e(__('scholar.stats.empty')) ?></p><?php else: ?>
            <ul class="list-unstyled mb-0"><?php foreach ($stats['by_language'] as $l): ?><li class="d-flex justify-content-between border-bottom py-1"><span><?= e(__('questions.languages.' . $l['code'])) ?></span><strong><?= (int) $l['n'] ?></strong></li><?php endforeach ?></ul>
            <?php endif ?>
        </section>
    </div>
    <div class="col-12 col-md-4">
        <section class="df-card h-100">
            <h2 class="df-card__title h6"><i class="bi bi-whatsapp" aria-hidden="true"></i> <?= e(__('scholar.stats.by_source')) ?></h2>
            <?php if ($stats['by_source'] === []): ?><p class="text-secondary mb-0"><?= e(__('scholar.stats.empty')) ?></p><?php else: ?>
            <ul class="list-unstyled mb-0"><?php foreach ($stats['by_source'] as $s): ?><li class="d-flex justify-content-between border-bottom py-1"><span><?= e(__('scholar.question.source_' . $s['source'])) ?></span><strong><?= (int) $s['n'] ?></strong></li><?php endforeach ?></ul>
            <?php endif ?>
            <ul class="list-unstyled mb-0 mt-3"><?php foreach ($stats['by_type'] as $t): ?><li class="d-flex justify-content-between border-bottom py-1"><span><?= e(__('questions.types.' . $t['input_type'])) ?></span><strong><?= (int) $t['n'] ?></strong></li><?php endforeach ?></ul>
        </section>
    </div>
    <div class="col-12 col-md-4">
        <section class="df-card h-100">
            <h2 class="df-card__title h6"><i class="bi bi-hand-thumbs-up" aria-hidden="true"></i> <?= e(__('scholar.stats.satisfaction')) ?></h2>
            <dl class="df-dl df-dl--compact mb-0">
                <dt><?= e(__('scholar.stats.helpful')) ?></dt><dd><?= (int) $stats['helpful'] ?></dd>
                <dt><?= e(__('scholar.stats.not_helpful')) ?></dt><dd><?= (int) $stats['not_helpful'] ?></dd>
                <dt><?= e(__('scholar.stats.min_delay')) ?></dt><dd><?= $stats['min_hours'] !== null ? e(__('scholar.stats.hours', ['n' => $stats['min_hours']])) : '—' ?></dd>
                <dt><?= e(__('scholar.stats.max_delay')) ?></dt><dd><?= $stats['max_hours'] !== null ? e(__('scholar.stats.hours', ['n' => $stats['max_hours']])) : '—' ?></dd>
            </dl>
        </section>
    </div>
</div>
<?php $this->endSection(); ?>
