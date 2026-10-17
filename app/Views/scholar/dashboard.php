<?php
/**
 * Tableau de bord du professeur (§4.5).
 *
 * @var \App\Core\View $this
 * @var array<string, mixed> $user
 * @var array<string, int|float|null> $stats
 * @var list<array<string, mixed>> $rubriques
 * @var list<array<string, mixed>> $recent
 */
$this->extend('layouts/scholar');
$this->section('title', __('scholar.dashboard.welcome', ['name' => \App\Models\User::publicName($user)]));
$this->section('subtitle', __('scholar.dashboard.intro'));
$cards = [
    ['key' => 'assigned', 'icon' => 'inbox', 'url' => route('scholar.questions', ['statut' => 'assigned'])],
    ['key' => 'in_progress', 'icon' => 'arrow-repeat', 'url' => route('scholar.questions', ['statut' => 'in_progress'])],
    ['key' => 'needs_info', 'icon' => 'question-circle', 'url' => route('scholar.questions', ['statut' => 'needs_info'])],
    ['key' => 'unassigned', 'icon' => 'hand-index-thumb', 'url' => route('scholar.questions', ['file' => 'a-prendre'])],
    ['key' => 'answered_month', 'icon' => 'check2-circle', 'url' => route('scholar.questions', ['statut' => 'done'])],
    ['key' => 'late', 'icon' => 'alarm', 'url' => route('scholar.questions'), 'danger' => true],
];
?>
<?php $this->section('content'); ?>
<div class="row g-3 mb-4">
    <?php foreach ($cards as $card): $value = (int) $stats[$card['key']]; ?>
    <div class="col-6 col-md-4">
        <a class="df-card df-stat d-block text-decoration-none<?= !empty($card['danger']) && $value > 0 ? ' df-stat--danger' : '' ?>" href="<?= e($card['url']) ?>">
            <i class="bi bi-<?= e($card['icon']) ?> df-stat__icon" aria-hidden="true"></i>
            <p class="df-stat__value mb-0"><?= $value ?></p>
            <p class="df-stat__label mb-0"><?= e(__('scholar.dashboard.' . $card['key'], ['hours' => $stats['late_hours']])) ?></p>
        </a>
    </div>
    <?php endforeach ?>
    <div class="col-12 col-md-4">
        <div class="df-card df-stat">
            <i class="bi bi-stopwatch df-stat__icon" aria-hidden="true"></i>
            <p class="df-stat__value mb-0"><?= $stats['avg_hours'] !== null ? e(__('scholar.dashboard.hours', ['count' => $stats['avg_hours']])) : e(__('scholar.dashboard.no_data')) ?></p>
            <p class="df-stat__label mb-0"><?= e(__('scholar.dashboard.avg_delay')) ?></p>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-12 col-xl-8">
        <div class="df-card h-100">
            <div class="df-section__head df-section__head--row mb-2">
                <h2 class="df-card__title mb-0"><i class="bi bi-inbox" aria-hidden="true"></i> <?= e(__('scholar.queue.title_mine')) ?></h2>
                <a class="small" href="<?= e(route('scholar.questions')) ?>"><?= e(__('scholar.dashboard.see_queue')) ?></a>
            </div>
            <?php if ($recent === []): ?>
            <p class="text-secondary mb-0"><?= e(__('scholar.queue.empty_mine')) ?></p>
            <?php else: ?>
            <ul class="df-list">
                <?php foreach ($recent as $q): ?>
                <li>
                    <a href="<?= e(route('scholar.questions.show', ['reference' => $q['reference']])) ?>" dir="auto"><span class="ltr"><?= e($q['reference']) ?></span> · <?= e($q['title'] ?: $q['rubrique_name']) ?></a>
                    <small><?= $this->partial('partials/status-badge', ['status' => $q['status']]) ?> <?= e(time_ago((string) $q['created_at'])) ?></small>
                </li>
                <?php endforeach ?>
            </ul>
            <?php endif ?>
        </div>
    </div>
    <div class="col-12 col-xl-4">
        <div class="df-card h-100">
            <h2 class="df-card__title"><i class="bi bi-grid" aria-hidden="true"></i> <?= e(__('scholar.dashboard.my_rubriques')) ?></h2>
            <?php if ($rubriques === []): ?>
            <p class="text-secondary mb-0"><?= e(__('scholar.dashboard.no_rubriques')) ?></p>
            <?php else: ?>
            <ul class="df-chips">
                <?php foreach ($rubriques as $r): ?>
                <li><a class="df-chip" href="<?= e(route('rubriques.show', ['slug' => $r['slug']])) ?>"><img src="<?= e(asset((string) $r['icon'])) ?>" alt="" width="22" height="22"> <?= e($r['name']) ?></a></li>
                <?php endforeach ?>
            </ul>
            <?php endif ?>
        </div>
    </div>
</div>
<?php $this->endSection(); ?>
