<?php
/**
 * Pages statiques.
 *
 * @var \App\Core\View $this
 * @var list<array<string, mixed>> $pages
 */
$this->extend('layouts/admin');
$this->section('title', __('admin.pages.title'));
$this->section('subtitle', __('admin.pages.intro'));
$this->section('actions');
?>
<a class="btn btn-primary" href="<?= e(route('admin.pages.create')) ?>"><i class="bi bi-plus-lg" aria-hidden="true"></i> <?= e(__('admin.pages.create')) ?></a>
<?php $this->endSection(); ?>
<?php $this->section('content'); ?>
<div class="df-table-wrap">
    <table class="table df-table">
        <thead><tr><th><?= e(__('admin.pages.col.title')) ?></th><th><?= e(__('admin.rubriques.field.code')) ?></th><th><?= e(__('admin.qa.col.locales')) ?></th><th><?= e(__('admin.pages.col.footer')) ?></th><th><?= e(__('admin.users.col.status')) ?></th><th><?= e(__('admin.common.updated_at')) ?></th><th class="actions"></th></tr></thead>
        <tbody>
            <?php foreach ($pages as $p): ?>
            <tr>
                <td dir="auto"><a href="<?= e(route('admin.pages.edit', ['id' => $p['id']])) ?>"><?= e($p['title'] ?? $p['code']) ?></a></td>
                <td><span class="df-code"><?= e($p['code']) ?></span></td>
                <td class="ltr"><?= e(strtoupper((string) ($p['locales'] ?? '—'))) ?></td>
                <td><?= (int) $p['show_in_footer'] === 1 ? '<i class="bi bi-check-lg text-success" aria-hidden="true"></i> ' . e(__('common.yes')) : e(__('common.no')) ?></td>
                <td><span class="df-badge df-badge--<?= (int) $p['is_active'] === 1 ? 'success' : 'secondary' ?>"><?= e((int) $p['is_active'] === 1 ? __('admin.common.active') : __('admin.common.inactive')) ?></span></td>
                <td class="df-muted-cell"><?= e(format_date((string) $p['updated_at'], true)) ?></td>
                <td class="actions"><a class="btn btn-sm btn-outline-primary" href="<?= e(route('admin.pages.edit', ['id' => $p['id']])) ?>"><i class="bi bi-pencil" aria-hidden="true"></i> <?= e(__('admin.common.edit')) ?></a></td>
            </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</div>
<?php $this->endSection(); ?>
