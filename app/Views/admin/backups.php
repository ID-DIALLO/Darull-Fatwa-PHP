<?php
/**
 * Sauvegardes de la base (super-admin).
 *
 * @var \App\Core\View $this
 * @var list<array{name: string, size: int, created_at: string}> $backups
 * @var int $retention
 */
$this->extend('layouts/admin');
$this->section('title', __('admin.backups.title'));
$this->section('subtitle', __('admin.backups.intro', ['days' => $retention]));
$this->section('actions');
?>
<?= $this->partial('admin/partials/post-button', ['url' => route('admin.backups.store'), 'label' => __('admin.backups.create'), 'icon' => 'database-down', 'class' => 'btn btn-primary']) ?>
<?php $this->endSection(); ?>
<?php $this->section('content'); ?>
<div class="alert alert-info small"><i class="bi bi-info-circle" aria-hidden="true"></i> <?= e(__('admin.backups.offsite')) ?></div>
<?php if ($backups === []): ?><?= $this->partial('admin/partials/empty', ['text' => __('admin.backups.empty')]) ?><?php else: ?>
<div class="df-table-wrap">
    <table class="table df-table">
        <thead><tr><th><?= e(__('admin.backups.col.file')) ?></th><th><?= e(__('admin.backups.col.date')) ?></th><th><?= e(__('admin.backups.col.size')) ?></th><th class="actions"></th></tr></thead>
        <tbody>
            <?php foreach ($backups as $b): ?>
            <tr>
                <td class="ltr"><span class="df-code"><?= e($b['name']) ?></span> <?php if (str_contains($b['name'], '-cron.')): ?><span class="df-badge df-badge--info"><?= e(__('admin.backups.auto')) ?></span><?php endif ?></td>
                <td><?= e(format_date($b['created_at'], true)) ?></td>
                <td><?= e(number_format($b['size'] / 1024, 0, ',', ' ')) ?> Ko</td>
                <td class="actions">
                    <a class="btn btn-sm btn-outline-primary" href="<?= e(route('admin.backups.download', ['name' => $b['name']])) ?>"><i class="bi bi-download" aria-hidden="true"></i> <?= e(__('admin.backups.download')) ?></a>
                    <?= $this->partial('admin/partials/post-button', ['url' => route('admin.backups.destroy', ['name' => $b['name']]), 'label' => __('admin.common.delete'), 'icon' => 'trash', 'class' => 'btn btn-sm btn-outline-danger', 'confirm' => __('admin.common.delete_confirm')]) ?>
                </td>
            </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</div>
<?php endif ?>
<?php $this->endSection(); ?>
