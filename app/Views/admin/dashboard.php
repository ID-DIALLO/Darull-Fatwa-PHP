<?php
/**
 * Tableau de bord (§4.6.1).
 *
 * @var \App\Core\View $this
 * @var array{from: string, to: string, period: string, label_from: string, label_to: string} $range
 * @var array<string, int|float|null> $kpis
 * @var list<array<string, mixed>> $recent
 * @var list<array<string, mixed>> $reviews
 */
$this->extend('layouts/admin');
$this->section('title', __('admin.dashboard.title'));
$this->section('subtitle', __('admin.dashboard.period_label', ['from' => format_date($range['from']), 'to' => format_date($range['to'])]));
$this->section('scripts');
?>
<script src="<?= e(asset('vendor/chart.js/chart.umd.min.js')) ?>" defer></script>
<?php $this->endSection(); ?>
<?php
$q = static fn (array $p = []): string => route('admin.questions', $p);
$cards = [
    ['key' => 'total', 'icon' => 'chat-left-dots', 'url' => $q(['from' => $range['label_from'], 'to' => $range['label_to']])],
    ['key' => 'new_24h', 'icon' => 'lightning-charge', 'url' => $q(['status' => 'new'])],
    ['key' => 'new_7d', 'icon' => 'calendar-week', 'url' => $q(['sort' => 'newest'])],
    ['key' => 'pending', 'icon' => 'hourglass-split', 'url' => $q(['status' => 'open']), 'tone' => 'warning'],
    ['key' => 'late', 'icon' => 'alarm', 'url' => $q(['late' => '1']), 'tone' => 'danger'],
    ['key' => 'review', 'icon' => 'eye', 'url' => $q(['review' => '1']), 'tone' => 'warning'],
    ['key' => 'answered', 'icon' => 'check2-circle', 'url' => $q(['status' => 'done'])],
    ['key' => 'answer_rate', 'icon' => 'percent', 'url' => null, 'suffix' => ' %'],
    ['key' => 'avg_hours', 'icon' => 'stopwatch', 'url' => null, 'suffix' => ' h'],
    ['key' => 'members', 'icon' => 'people', 'url' => can('users.manage') ? route('admin.users') : null],
    ['key' => 'members_new', 'icon' => 'person-plus', 'url' => can('users.manage') ? route('admin.users') : null],
    ['key' => 'contacts_new', 'icon' => 'envelope-open', 'url' => can('contact.manage') ? route('admin.contact', ['status' => 'new']) : null],
];
?>
<?php $this->section('content'); ?>
<form method="get" action="<?= e(route('admin.dashboard')) ?>" class="df-card df-filters">
    <div class="row g-2 align-items-end">
        <div class="col-6 col-md-3">
            <label class="form-label" for="period"><?= e(__('admin.dashboard.period')) ?></label>
            <select class="form-select" id="period" name="period" data-period-select>
                <?php foreach (['7' => __('admin.dashboard.period_7'), '30' => __('admin.dashboard.period_30'), '365' => __('admin.dashboard.period_365'), 'custom' => __('admin.dashboard.period_custom')] as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= $range['period'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <div class="col-12 col-md-6" data-period-custom <?= $range['period'] === 'custom' ? '' : 'hidden' ?>>
            <div class="row g-2">
                <div class="col-6"><label class="form-label" for="from"><?= e(__('admin.common.from')) ?></label><input type="date" class="form-control" id="from" name="from" value="<?= e($range['label_from']) ?>"></div>
                <div class="col-6"><label class="form-label" for="to"><?= e(__('admin.common.to')) ?></label><input type="date" class="form-control" id="to" name="to" value="<?= e($range['label_to']) ?>"></div>
            </div>
        </div>
        <div class="col-6 col-md-3 d-grid"><button type="submit" class="btn btn-primary"><i class="bi bi-funnel" aria-hidden="true"></i> <?= e(__('admin.common.apply')) ?></button></div>
    </div>
</form>

<div class="row g-3 mb-4">
    <?php foreach ($cards as $card): $value = $kpis[$card['key']]; $tone = $card['tone'] ?? null; ?>
    <div class="col-6 col-md-4 col-xl-3 col-xxl-2">
        <<?= $card['url'] ? 'a href="' . e($card['url']) . '"' : 'div' ?> class="df-kpi<?= $tone && $value > 0 ? ' df-kpi--' . $tone : '' ?>">
            <span class="df-kpi__icon"><i class="bi bi-<?= e($card['icon']) ?>" aria-hidden="true"></i></span>
            <span>
                <span class="df-kpi__value d-block"><?= $value === null ? '—' : e((string) $value) . ($card['suffix'] ?? '') ?></span>
                <span class="df-kpi__label"><?= e(__('admin.dashboard.kpi.' . $card['key'])) ?></span>
            </span>
        </<?= $card['url'] ? 'a' : 'div' ?>>
    </div>
    <?php endforeach ?>
</div>

<div data-stats-url="<?= e(route('admin.stats.data', ['period' => $range['period'], 'from' => $range['label_from'], 'to' => $range['label_to']])) ?>" data-label-questions="<?= e(__('admin.dashboard.chart.questions')) ?>" data-label-answered="<?= e(__('admin.dashboard.chart.answered')) ?>">
    <div class="row g-3 mb-4">
        <div class="col-12 col-xl-8"><div class="df-card h-100"><h2 class="df-card__title h6"><?= e(__('admin.dashboard.chart.monthly')) ?></h2><div class="df-chart df-chart--tall"><canvas data-chart="monthly" role="img" aria-label="<?= e(__('admin.dashboard.chart.monthly')) ?>"></canvas></div></div></div>
        <div class="col-12 col-md-6 col-xl-4"><div class="df-card h-100"><h2 class="df-card__title h6"><?= e(__('admin.dashboard.chart.status')) ?></h2><div class="df-chart df-chart--tall"><canvas data-chart="status" role="img" aria-label="<?= e(__('admin.dashboard.chart.status')) ?>"></canvas></div></div></div>
        <div class="col-12 col-md-6 col-xl-4"><div class="df-card h-100"><h2 class="df-card__title h6"><?= e(__('admin.dashboard.chart.rubrique')) ?></h2><div class="df-chart"><canvas data-chart="rubrique" role="img" aria-label="<?= e(__('admin.dashboard.chart.rubrique')) ?>"></canvas></div></div></div>
        <div class="col-12 col-md-6 col-xl-4"><div class="df-card h-100"><h2 class="df-card__title h6"><?= e(__('admin.dashboard.chart.language')) ?></h2><div class="df-chart"><canvas data-chart="language" role="img" aria-label="<?= e(__('admin.dashboard.chart.language')) ?>"></canvas></div></div></div>
        <div class="col-12 col-md-6 col-xl-4"><div class="df-card h-100"><h2 class="df-card__title h6"><?= e(__('admin.dashboard.chart.channel')) ?></h2><div class="df-chart"><canvas data-chart="channel" role="img" aria-label="<?= e(__('admin.dashboard.chart.channel')) ?>"></canvas></div></div></div>
        <div class="col-12 col-md-6 col-xl-4"><div class="df-card h-100"><h2 class="df-card__title h6"><?= e(__('admin.dashboard.chart.type')) ?></h2><div class="df-chart"><canvas data-chart="type" role="img" aria-label="<?= e(__('admin.dashboard.chart.type')) ?>"></canvas></div></div></div>
        <div class="col-12 col-md-6 col-xl-4"><div class="df-card h-100"><h2 class="df-card__title h6"><?= e(__('admin.dashboard.chart.country')) ?></h2><div class="df-chart"><canvas data-chart="country" role="img" aria-label="<?= e(__('admin.dashboard.chart.country')) ?>"></canvas></div></div></div>
        <div class="col-12 col-xl-4">
            <div class="df-card h-100">
                <h2 class="df-card__title h6"><?= e(__('admin.dashboard.chart.scholars')) ?></h2>
                <div class="table-responsive">
                    <table class="table table-sm df-table" data-scholars-table>
                        <thead><tr><th><?= e(__('admin.dashboard.scholar.name')) ?></th><th class="text-end"><?= e(__('admin.dashboard.scholar.assigned')) ?></th><th class="text-end"><?= e(__('admin.dashboard.scholar.open')) ?></th><th class="text-end"><?= e(__('admin.dashboard.scholar.answered')) ?></th><th class="text-end"><?= e(__('admin.dashboard.scholar.avg')) ?></th></tr></thead>
                        <tbody><tr><td colspan="5" class="text-secondary"><?= e(__('common.loading')) ?></td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-xl-7">
        <div class="df-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-2"><h2 class="df-card__title h6 mb-0"><?= e(__('admin.dashboard.recent')) ?></h2><a class="small" href="<?= e(route('admin.questions')) ?>"><?= e(__('admin.common.see_all')) ?></a></div>
            <?php if ($recent === []): ?><?= $this->partial('admin/partials/empty') ?><?php else: ?>
            <div class="table-responsive"><table class="table table-sm df-table"><tbody>
                <?php foreach ($recent as $r): ?>
                <tr>
                    <td class="ref"><a href="<?= e(route('admin.questions.show', ['id' => $r['id']])) ?>"><?= e($r['reference']) ?></a></td>
                    <td dir="auto"><?= e($r['member_name']) ?><br><small class="text-secondary"><?= e($r['title'] ?: __('questions.list.untitled')) ?></small></td>
                    <td><?= $this->partial('partials/status-badge', ['status' => $r['status']]) ?></td>
                    <td class="df-muted-cell"><?= e($r['scholar_name'] ?? '—') ?><br><?= e(time_ago((string) $r['created_at'])) ?></td>
                </tr>
                <?php endforeach ?>
            </tbody></table></div>
            <?php endif ?>
        </div>
    </div>
    <div class="col-12 col-xl-5">
        <div class="df-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-2"><h2 class="df-card__title h6 mb-0"><i class="bi bi-eye" aria-hidden="true"></i> <?= e(__('admin.dashboard.reviews')) ?></h2><a class="small" href="<?= e(route('admin.questions', ['review' => '1'])) ?>"><?= e(__('admin.common.see_all')) ?></a></div>
            <?php if ($reviews === []): ?><p class="text-secondary mb-0"><?= e(__('admin.dashboard.no_reviews')) ?></p><?php else: ?>
            <ul class="df-list">
                <?php foreach ($reviews as $r): ?>
                <li><a href="<?= e(route('admin.questions.show', ['id' => $r['id']])) ?>"><span class="ltr"><?= e($r['reference']) ?></span> · <?= e($r['scholar_name']) ?></a><small><?= e(time_ago((string) $r['created_at'])) ?></small></li>
                <?php endforeach ?>
            </ul>
            <?php endif ?>
        </div>
    </div>
</div>
<?php $this->endSection(); ?>
