<?php
/**
 * Catégories du forum (noms traduits, rubrique liée, ordre, activation) + création.
 *
 * @var \App\Core\View $this
 * @var list<array<string, mixed>> $categories
 * @var list<array<string, mixed>> $rubriques
 */
$this->extend('layouts/admin');
$this->section('title', __('admin.forum.categories'));
$this->section('subtitle', __('admin.forum.categories_intro'));
$form = function (?array $c) use ($rubriques): void { $t = $c['translations'] ?? []; $id = $c['id'] ?? 'new'; ?>
    <form method="post" action="<?= e($c === null ? route('admin.forum.categories.store') : route('admin.forum.categories.update', ['id' => $c['id']])) ?>" class="df-card mb-3">
        <?= csrf_field() ?>
        <div class="row g-2 align-items-end">
            <?php foreach (\App\Core\Lang::locales() as $code): ?>
            <div class="col-12 col-md-4"><label class="form-label" for="name-<?= e($id) ?>-<?= e($code) ?>"><?= e(__('admin.rubriques.field.name')) ?> (<?= e(strtoupper($code)) ?>)<?= $code === \App\Core\Lang::getDefault() ? ' *' : '' ?></label><input type="text" class="form-control form-control-sm" id="name-<?= e($id) ?>-<?= e($code) ?>" name="name[<?= e($code) ?>]" maxlength="120" value="<?= e($t[$code]['name'] ?? '') ?>" lang="<?= e($code) ?>" dir="<?= e(text_dir($code)) ?>"></div>
            <?php endforeach ?>
            <?php foreach (\App\Core\Lang::locales() as $code): ?>
            <div class="col-12 col-md-4"><label class="form-label" for="desc-<?= e($id) ?>-<?= e($code) ?>"><?= e(__('admin.rubriques.field.short_description')) ?> (<?= e(strtoupper($code)) ?>)</label><input type="text" class="form-control form-control-sm" id="desc-<?= e($id) ?>-<?= e($code) ?>" name="description[<?= e($code) ?>]" maxlength="255" value="<?= e($t[$code]['description'] ?? '') ?>" lang="<?= e($code) ?>" dir="<?= e(text_dir($code)) ?>"></div>
            <?php endforeach ?>
            <div class="col-6 col-md-3"><label class="form-label" for="rub-<?= e($id) ?>"><?= e(__('admin.forum.col.rubrique')) ?></label><select class="form-select form-select-sm" id="rub-<?= e($id) ?>" name="rubrique_id"><option value="">—</option><?php foreach ($rubriques as $r): ?><option value="<?= (int) $r['id'] ?>" <?= (int) ($c['rubrique_id'] ?? 0) === (int) $r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option><?php endforeach ?></select></div>
            <div class="col-6 col-md-3"><label class="form-label" for="icon-<?= e($id) ?>"><?= e(__('admin.rubriques.field.icon')) ?></label><input type="text" class="form-control form-control-sm" id="icon-<?= e($id) ?>" name="icon" value="<?= e($c['icon'] ?? 'bi-chat-dots') ?>" dir="ltr"></div>
            <div class="col-4 col-md-2"><label class="form-label" for="order-<?= e($id) ?>"><?= e(__('admin.common.sort_order')) ?></label><input type="number" class="form-control form-control-sm" id="order-<?= e($id) ?>" name="sort_order" min="0" max="999" value="<?= (int) ($c['sort_order'] ?? 0) ?>"></div>
            <div class="col-4 col-md-2"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="active-<?= e($id) ?>" name="is_active" value="1" <?= (int) ($c['is_active'] ?? 1) === 1 ? 'checked' : '' ?>><label class="form-check-label" for="active-<?= e($id) ?>"><?= e(__('admin.common.active')) ?></label></div></div>
            <div class="col-4 col-md-2 d-grid"><button type="submit" class="btn btn-sm btn-primary"><?= e($c === null ? __('admin.forum.add_category') : __('common.save')) ?></button></div>
        </div>
        <?php if ($c !== null): ?><p class="small text-secondary mt-2 mb-0"><?= e(trans_choice('admin.forum.topics_count', (int) $c['topics_count'])) ?> · <?= e(__('admin.common.languages_filled', ['list' => strtoupper((string) ($c['locales'] ?? '—'))])) ?></p><?php endif ?>
    </form>
<?php };
?>
<?php $this->section('content'); ?>
<?php foreach ($categories as $c) { $form($c); } ?>
<h2 class="h5 mt-4"><?= e(__('admin.forum.add_category')) ?></h2>
<?php $form(null) ?>
<?php $this->endSection(); ?>
