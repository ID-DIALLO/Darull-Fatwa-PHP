<?php
/**
 * Professeurs (§4.6.3).
 *
 * @var \App\Core\View $this
 * @var list<array<string, mixed>> $scholars
 */
$this->extend('layouts/admin');
$this->section('title', __('admin.scholars.title'));
$this->section('subtitle', __('admin.scholars.intro'));
?>
<?php $this->section('content'); ?>
<?php if ($scholars === []): ?><?= $this->partial('admin/partials/empty', ['text' => __('admin.scholars.empty')]) ?><?php else: ?>
<div class="df-table-wrap">
    <table class="table df-table">
        <thead><tr><th><?= e(__('admin.users.col.name')) ?></th><th><?= e(__('admin.scholars.col.rubriques')) ?></th><th><?= e(__('admin.scholars.col.languages')) ?></th><th><?= e(__('admin.scholars.col.load')) ?></th><th><?= e(__('admin.scholars.col.answered')) ?></th><th><?= e(__('admin.scholars.col.options')) ?></th><th class="actions"></th></tr></thead>
        <tbody>
            <?php foreach ($scholars as $s): ?>
            <tr>
                <td dir="auto"><div class="d-flex align-items-center gap-2"><?php if ($s['photo_path']): ?><img class="df-thumb df-thumb--square" src="<?= e($s['photo_path']) ?>" alt="" width="56" height="56"><?php endif ?><div><a href="<?= e(route('admin.users.show', ['id' => $s['id']])) ?>"><?= e($s['full_name']) ?></a><br><small class="text-secondary ltr"><?= e($s['email'] ?: $s['phone']) ?></small></div></div></td>
                <td class="small"><?= e($s['rubriques'] ?? '—') ?></td>
                <?php $langs = array_filter(explode(',', (string) ($s['languages'] ?? ''))); ?><td class="small"><?= e(implode(', ', array_map(static fn (string $l): string => __('front.scholars.lang.' . $l), $langs))) ?></td>
                <td><?= (int) $s['open_count'] ?> / <?= (int) ($s['max_open_questions'] ?? 20) ?></td>
                <td><?= (int) $s['answered_count'] ?></td>
                <td class="small">
                    <?php if ((int) ($s['is_public'] ?? 0) === 1): ?><span class="df-badge df-badge--success"><?= e(__('admin.scholars.public')) ?></span><?php endif ?>
                    <?php if ((int) ($s['is_available'] ?? 1) === 0): ?><span class="df-badge df-badge--secondary"><?= e(__('admin.scholars.unavailable')) ?></span><?php endif ?>
                    <?php if ((int) ($s['requires_review'] ?? 0) === 1): ?><span class="df-badge df-badge--warning"><?= e(__('admin.scholars.requires_review')) ?></span><?php endif ?>
                    <?php if ((int) ($s['can_self_assign'] ?? 0) === 1): ?><span class="df-badge df-badge--info"><?= e(__('admin.scholars.self_assign')) ?></span><?php endif ?>
                </td>
                <td class="actions"><a class="btn btn-sm btn-outline-primary" href="<?= e(route('admin.scholars.edit', ['id' => $s['id']])) ?>"><i class="bi bi-pencil" aria-hidden="true"></i> <?= e(__('admin.common.edit')) ?></a></td>
            </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</div>
<p class="small text-secondary mt-3"><i class="bi bi-info-circle" aria-hidden="true"></i> <?= e(__('admin.scholars.add_hint')) ?></p>
<?php endif ?>
<?php $this->endSection(); ?>
