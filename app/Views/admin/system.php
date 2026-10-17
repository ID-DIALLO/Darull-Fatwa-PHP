<?php
/**
 * Système (super-admin) : mises à jour de la base de données et état de l'installation.
 *
 * @var \App\Core\View $this
 * @var list<string> $applied
 * @var list<string> $pending
 * @var array<string, mixed> $info
 */
$this->extend('layouts/admin');
$this->section('title', __('admin.system.title'));
$this->section('subtitle', __('admin.system.intro'));
?>
<?php $this->section('content'); ?>
<div class="row g-3">
    <div class="col-12 col-xl-7">
        <section class="df-card mb-3">
            <h2 class="df-card__title h6"><i class="bi bi-database-gear" aria-hidden="true"></i> <?= e(__('admin.system.migrations')) ?></h2>
            <?php if ($pending === []): ?>
            <p class="alert alert-success mb-3"><i class="bi bi-check2-circle" aria-hidden="true"></i> <?= e(__('admin.system.up_to_date')) ?></p>
            <?php else: ?>
            <div class="alert alert-warning mb-3">
                <p class="fw-semibold mb-2"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> <?= e(trans_choice('admin.system.pending_count', count($pending))) ?></p>
                <ul class="mb-3 ltr"><?php foreach ($pending as $file): ?><li><span class="df-code"><?= e($file) ?></span></li><?php endforeach ?></ul>
                <p class="small mb-3"><?= e(__('admin.system.apply_help')) ?></p>
                <?= $this->partial('admin/partials/post-button', ['url' => route('admin.system.migrate'), 'label' => __('admin.system.apply'), 'icon' => 'play-fill', 'class' => 'btn btn-primary', 'confirm' => __('admin.system.apply_confirm')]) ?>
            </div>
            <?php endif ?>
            <h3 class="h6 mt-4"><?= e(__('admin.system.applied')) ?></h3>
            <?php if ($applied === []): ?><p class="text-secondary small mb-0"><?= e(__('admin.common.empty')) ?></p><?php else: ?>
            <ul class="small mb-0 ltr"><?php foreach ($applied as $file): ?><li><i class="bi bi-check2 text-success" aria-hidden="true"></i> <span class="df-code"><?= e($file) ?></span></li><?php endforeach ?></ul>
            <?php endif ?>
        </section>
    </div>
    <div class="col-12 col-xl-5">
        <section class="df-card mb-3">
            <h2 class="df-card__title h6"><i class="bi bi-hdd-network" aria-hidden="true"></i> <?= e(__('admin.system.installation')) ?></h2>
            <dl class="df-dl df-dl--compact mb-0">
                <dt>PHP</dt><dd class="ltr"><?= e($info['php']) ?></dd>
                <dt><?= e(__('admin.system.database')) ?></dt><dd class="ltr"><?= e($info['database']) ?> · <?= (int) $info['tables'] ?> <?= e(__('admin.system.tables')) ?></dd>
                <dt><?= e(__('admin.system.environment')) ?></dt><dd class="ltr"><?= e($info['env']) ?><?php if ($info['debug']): ?> <span class="df-badge df-badge--danger">APP_DEBUG</span><?php endif ?></dd>
                <dt>URL</dt><dd class="ltr"><?= e($info['url']) ?></dd>
                <dt><?= e(__('admin.system.assets')) ?></dt><dd><?= $info['assets'] ? '<i class="bi bi-check2 text-success" aria-hidden="true"></i> ' . e(__('admin.common.yes')) : '<i class="bi bi-x text-danger" aria-hidden="true"></i> ' . e(__('admin.common.no')) ?></dd>
                <dt><?= e(__('admin.system.cron')) ?></dt><dd><?= $info['cron'] !== null ? e(format_date(gmdate('Y-m-d H:i:s', (int) $info['cron']), true)) : '<span class="text-danger">' . e(__('admin.system.cron_never')) . '</span>' ?></dd>
                <dt><?= e(__('admin.system.storage')) ?></dt><dd><?= $info['writable'] === [] ? '<i class="bi bi-check2 text-success" aria-hidden="true"></i> ' . e(__('admin.system.storage_ok')) : '<span class="text-danger">' . e(__('admin.system.storage_ko', ['dirs' => implode(', ', $info['writable'])])) . '</span>' ?></dd>
            </dl>
        </section>
        <section class="df-card">
            <h2 class="df-card__title h6"><i class="bi bi-info-circle" aria-hidden="true"></i> <?= e(__('admin.system.after_deploy')) ?></h2>
            <ol class="small mb-0">
                <li><?= e(__('admin.system.step_1')) ?></li>
                <li><?= e(__('admin.system.step_2')) ?></li>
                <li><?= e(__('admin.system.step_3')) ?></li>
            </ol>
        </section>
    </div>
</div>
<?php $this->endSection(); ?>
