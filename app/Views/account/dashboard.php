<?php
/**
 * Tableau de bord du membre (§4.4). Les listes de questions arrivent en Phase 3.
 *
 * @var \App\Core\View $this
 * @var array<string, mixed> $user
 * @var array{asked: int, answered: int, pending: int} $counts
 */
$this->extend('layouts/account');
$this->section('title', __('account.dashboard.welcome', ['name' => \App\Models\User::publicName($user)]));
$this->section('subtitle', __('account.dashboard.intro'));
?>
<?php $this->section('content'); ?>
<div class="row g-3 mb-4">
    <?php foreach (['asked' => 'chat-left-dots', 'answered' => 'check2-circle', 'pending' => 'hourglass-split'] as $key => $icon): ?>
    <div class="col-12 col-sm-4">
        <div class="df-card df-stat">
            <i class="bi bi-<?= e($icon) ?> df-stat__icon" aria-hidden="true"></i>
            <p class="df-stat__value mb-0"><?= (int) $counts[$key] ?></p>
            <p class="df-stat__label mb-0"><?= e(__('account.dashboard.stats.' . $key)) ?></p>
        </div>
    </div>
    <?php endforeach ?>
</div>

<div class="df-card">
    <p class="mb-3"><?= e(__('account.dashboard.soon')) ?></p>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-gold" href="<?= e(route_exists('questions.create') ? route('questions.create') : route('home')) ?>"><i class="bi bi-mic-fill" aria-hidden="true"></i> <?= e(__('account.dashboard.ask')) ?></a>
        <a class="btn btn-outline-primary" href="<?= e(route('account.profile')) ?>"><i class="bi bi-person" aria-hidden="true"></i> <?= e(__('account.dashboard.complete_profile')) ?></a>
    </div>
</div>
<?php $this->endSection(); ?>
