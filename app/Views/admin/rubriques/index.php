<?php
/**
 * Rubriques (§3).
 *
 * @var \App\Core\View $this
 * @var list<array<string, mixed>> $rubriques
 */
$this->extend('layouts/admin');
$this->section('title', __('admin.rubriques.title'));
$this->section('subtitle', __('admin.rubriques.intro'));
$this->section('actions');
?>
<a class="btn btn-primary" href="<?= e(route('admin.rubriques.create')) ?>"><i class="bi bi-plus-lg" aria-hidden="true"></i> <?= e(__('admin.rubriques.create')) ?></a>
<?php $this->endSection(); ?>
<?php $this->section('content'); ?>
<?php if ($rubriques === []): ?><?= $this->partial('admin/partials/empty') ?><?php else: ?>
<form method="post" action="<?= e(route('admin.rubriques.reorder')) ?>">
    <?= csrf_field() ?>
    <ul class="df-sortable list-unstyled" data-sortable>
        <?php foreach ($rubriques as $r): ?>
        <li class="<?= $r['parent_id'] ? 'ms-4' : '' ?>">
            <input type="hidden" name="order[]" value="<?= (int) $r['id'] ?>" data-order-input>
            <span class="handle"><i class="bi bi-grip-vertical" aria-hidden="true"></i></span>
            <?php if ($r['icon']): ?><img src="<?= e(asset((string) $r['icon'])) ?>" alt="" width="28" height="28"><?php endif ?>
            <span class="flex-grow-1" dir="auto">
                <strong><?= e($r['name'] ?? $r['code']) ?></strong> <span class="df-code"><?= e($r['code']) ?></span>
                <?php if ($r['parent_id']): ?><small class="text-secondary">↳ <?= e($r['parent_code']) ?></small><?php endif ?>
                <br><small class="text-secondary"><?= e(trans_choice('admin.questions.count', (int) $r['questions_count'])) ?> · <?= e(__('admin.common.languages_filled', ['list' => strtoupper((string) ($r['locales'] ?? '—'))])) ?><?php if ((int) $r['is_active'] !== 1): ?> · <span class="text-danger"><?= e(__('admin.common.inactive')) ?></span><?php endif ?></small>
            </span>
            <?php if ($r['color']): ?><span class="rounded-circle d-inline-block border" style="width:16px;height:16px;background:<?= e($r['color']) ?>" aria-hidden="true"></span><?php endif ?>
            <span class="btn-group btn-group-sm" role="group" aria-label="<?= e(__('admin.common.reorder')) ?>">
                <button type="button" class="btn btn-outline-secondary" data-move="up" aria-label="<?= e(__('admin.common.move_up')) ?>"><i class="bi bi-chevron-up" aria-hidden="true"></i></button>
                <button type="button" class="btn btn-outline-secondary" data-move="down" aria-label="<?= e(__('admin.common.move_down')) ?>"><i class="bi bi-chevron-down" aria-hidden="true"></i></button>
            </span>
            <a class="btn btn-sm btn-outline-primary" href="<?= e(route('admin.rubriques.edit', ['id' => $r['id']])) ?>"><i class="bi bi-pencil" aria-hidden="true"></i><span class="visually-hidden"><?= e(__('admin.common.edit')) ?></span></a>
        </li>
        <?php endforeach ?>
    </ul>
    <button type="submit" class="btn btn-outline-primary btn-sm"><i class="bi bi-sort-numeric-down" aria-hidden="true"></i> <?= e(__('admin.common.save_order')) ?></button>
</form>
<?php endif ?>
<?php $this->endSection(); ?>
