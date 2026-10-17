<?php
/**
 * Bannières du Hero (§6.1).
 *
 * @var \App\Core\View $this
 * @var list<array<string, mixed>> $banners
 */
$this->extend('layouts/admin');
$this->section('title', __('admin.banners.title'));
$this->section('subtitle', __('admin.banners.intro'));
$this->section('actions');
?>
<a class="btn btn-primary" href="<?= e(route('admin.banners.create')) ?>"><i class="bi bi-plus-lg" aria-hidden="true"></i> <?= e(__('admin.banners.create')) ?></a>
<?php $this->endSection(); ?>
<?php $this->section('content'); ?>
<?php if ($banners === []): ?><?= $this->partial('admin/partials/empty') ?><?php else: ?>
<div class="df-table-wrap">
    <table class="table df-table">
        <thead><tr><th></th><th><?= e(__('admin.banners.col.title')) ?></th><th><?= e(__('admin.banners.col.links')) ?></th><th><?= e(__('admin.banners.col.dates')) ?></th><th><?= e(__('admin.common.sort_order')) ?></th><th><?= e(__('admin.users.col.status')) ?></th><th class="actions"></th></tr></thead>
        <tbody>
            <?php foreach ($banners as $b): $now = gmdate('Y-m-d H:i:s'); $live = (int) $b['is_active'] === 1 && ($b['starts_at'] === null || $b['starts_at'] <= $now) && ($b['ends_at'] === null || $b['ends_at'] >= $now); ?>
            <tr>
                <td><img class="df-thumb" src="<?= e($b['image_desktop']) ?>" alt="" width="96" height="54"></td>
                <td dir="auto"><a href="<?= e(route('admin.banners.edit', ['id' => $b['id']])) ?>"><?= e($b['title'] ?? '—') ?></a><br><small class="text-secondary"><?= e(__('admin.common.languages_filled', ['list' => strtoupper((string) ($b['locales'] ?? '—'))])) ?></small></td>
                <td class="small ltr"><?= e($b['link_url'] ?? '—') ?><?php if ($b['link2_url']): ?><br><?= e($b['link2_url']) ?><?php endif ?></td>
                <td class="small"><?= $b['starts_at'] ? e(format_date((string) $b['starts_at'])) : '—' ?> → <?= $b['ends_at'] ? e(format_date((string) $b['ends_at'])) : '—' ?></td>
                <td><?= (int) $b['sort_order'] ?></td>
                <td><span class="df-badge df-badge--<?= $live ? 'success' : 'secondary' ?>"><?= e($live ? __('admin.banners.live') : __('admin.common.inactive')) ?></span></td>
                <td class="actions"><a class="btn btn-sm btn-outline-primary" href="<?= e(route('admin.banners.edit', ['id' => $b['id']])) ?>"><i class="bi bi-pencil" aria-hidden="true"></i></a></td>
            </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</div>
<?php endif ?>
<?php $this->endSection(); ?>
