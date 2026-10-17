<?php
/**
 * Sections de l'accueil (§6) : ordre, activation, chiffres clés manuels.
 *
 * @var \App\Core\View $this
 * @var list<array<string, mixed>> $sections
 * @var array<string, string|null> $stats
 */
$this->extend('layouts/admin');
$this->section('title', __('admin.home.title'));
$this->section('subtitle', __('admin.home.intro'));
?>
<?php $this->section('content'); ?>
<form method="post" action="<?= e(route('admin.home_sections.update')) ?>">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-12 col-xl-7">
            <div class="df-card">
                <h2 class="df-card__title h6"><?= e(__('admin.home.sections')) ?></h2>
                <ul class="df-sortable list-unstyled" data-sortable>
                    <?php foreach ($sections as $s): ?>
                    <li>
                        <input type="hidden" name="order[]" value="<?= e($s['code']) ?>" data-order-input>
                        <span class="handle"><i class="bi bi-grip-vertical" aria-hidden="true"></i></span>
                        <div class="form-check form-switch flex-grow-1 mb-0">
                            <input class="form-check-input" type="checkbox" role="switch" name="active[]" id="sec-<?= e($s['code']) ?>" value="<?= e($s['code']) ?>" <?= (int) $s['is_active'] === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="sec-<?= e($s['code']) ?>"><strong><?= e(__('admin.home.section.' . $s['code'])) ?></strong> <span class="df-code"><?= e($s['code']) ?></span></label>
                        </div>
                        <span class="btn-group btn-group-sm" role="group" aria-label="<?= e(__('admin.common.reorder')) ?>">
                            <button type="button" class="btn btn-outline-secondary" data-move="up" aria-label="<?= e(__('admin.common.move_up')) ?>"><i class="bi bi-chevron-up" aria-hidden="true"></i></button>
                            <button type="button" class="btn btn-outline-secondary" data-move="down" aria-label="<?= e(__('admin.common.move_down')) ?>"><i class="bi bi-chevron-down" aria-hidden="true"></i></button>
                        </span>
                    </li>
                    <?php endforeach ?>
                </ul>
                <p class="small text-secondary mb-0"><?= e(__('admin.home.sections_help')) ?></p>
            </div>
        </div>
        <div class="col-12 col-xl-5">
            <div class="df-card">
                <h2 class="df-card__title h6"><?= e(__('admin.home.stats')) ?></h2>
                <p class="small text-secondary"><?= e(__('admin.home.stats_help')) ?></p>
                <?php foreach ($stats as $key => $value): ?>
                <div class="mb-2"><label class="form-label" for="<?= e($key) ?>"><?= e(__('admin.home.stat.' . $key)) ?></label><input type="number" class="form-control" id="<?= e($key) ?>" name="<?= e($key) ?>" min="0" value="<?= e((string) $value) ?>" placeholder="<?= e(__('admin.home.auto')) ?>"></div>
                <?php endforeach ?>
            </div>
        </div>
    </div>
    <div class="mt-3"><button type="submit" class="btn btn-primary"><i class="bi bi-check2" aria-hidden="true"></i> <?= e(__('common.save')) ?></button></div>
</form>
<?php $this->endSection(); ?>
