<?php
/**
 * Versets / hadiths du jour (§6.6).
 *
 * @var \App\Core\View $this
 * @var list<array<string, mixed>> $quotes
 * @var array<string, mixed>|null $today
 */
$this->extend('layouts/admin');
$this->section('title', __('admin.quotes.title'));
$this->section('subtitle', __('admin.quotes.intro'));
$this->section('actions');
?>
<a class="btn btn-primary" href="<?= e(route('admin.daily_quotes.create')) ?>"><i class="bi bi-plus-lg" aria-hidden="true"></i> <?= e(__('admin.quotes.create')) ?></a>
<?php $this->endSection(); ?>
<?php $this->section('content'); ?>
<?php if ($today !== null): ?>
<div class="df-card mb-3 df-quote-admin">
    <p class="small text-secondary mb-1"><i class="bi bi-calendar-check" aria-hidden="true"></i> <?= e(__('admin.quotes.today')) ?></p>
    <p class="df-verse__text mb-1" lang="ar" dir="rtl"><?= e($today['arabic_text']) ?></p>
    <p class="mb-0 small"><?= e($today['translation'] ?? '') ?> — <em><?= e($today['reference']) ?></em></p>
</div>
<?php endif ?>
<?php if ($quotes === []): ?><?= $this->partial('admin/partials/empty') ?><?php else: ?>
<div class="df-table-wrap">
    <table class="table df-table">
        <thead><tr><th><?= e(__('admin.quotes.col.type')) ?></th><th><?= e(__('admin.quotes.col.text')) ?></th><th><?= e(__('admin.quotes.col.reference')) ?></th><th><?= e(__('admin.quotes.col.date')) ?></th><th><?= e(__('admin.qa.col.locales')) ?></th><th><?= e(__('admin.users.col.status')) ?></th><th class="actions"></th></tr></thead>
        <tbody>
            <?php foreach ($quotes as $q): ?>
            <tr>
                <td><?= e(__('admin.quotes.type.' . $q['type'])) ?></td>
                <td><span lang="ar" dir="rtl" class="d-block"><?= e(mb_strimwidth((string) $q['arabic_text'], 0, 80, '…')) ?></span><small class="text-secondary"><?= e(mb_strimwidth((string) ($q['translation'] ?? ''), 0, 90, '…')) ?></small></td>
                <td class="small"><?= e($q['reference']) ?></td>
                <td class="small"><?= $q['display_date'] ? e(format_date((string) $q['display_date'])) : '<span class="text-secondary">' . e(__('admin.quotes.rotation')) . '</span>' ?></td>
                <td class="ltr"><?= e(strtoupper((string) ($q['locales'] ?? '—'))) ?></td>
                <td><span class="df-badge df-badge--<?= (int) $q['is_active'] === 1 ? 'success' : 'secondary' ?>"><?= e((int) $q['is_active'] === 1 ? __('admin.common.active') : __('admin.common.inactive')) ?></span></td>
                <td class="actions"><a class="btn btn-sm btn-outline-primary" href="<?= e(route('admin.daily_quotes.edit', ['id' => $q['id']])) ?>"><i class="bi bi-pencil" aria-hidden="true"></i></a></td>
            </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</div>
<?php endif ?>
<?php $this->endSection(); ?>
