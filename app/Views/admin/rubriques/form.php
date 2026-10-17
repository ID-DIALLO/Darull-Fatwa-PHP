<?php
/**
 * Création / modification d'une rubrique.
 *
 * @var \App\Core\View $this
 * @var array<string, mixed>|null $rubrique
 * @var list<array<string, mixed>> $parents
 * @var list<string> $icons
 */
$this->extend('layouts/admin');
$editing = $rubrique !== null;
$t = $rubrique['translations'] ?? [];
$this->section('title', $editing ? e(__('admin.rubriques.edit_title', ['name' => $t[locale()]['name'] ?? $rubrique['code']])) : e(__('admin.rubriques.create')));
$this->section('head'); ?><link rel="stylesheet" href="<?= e(asset('vendor/quill/quill.snow.css')) ?>"><?php $this->endSection();
$this->section('scripts'); ?><script src="<?= e(asset('vendor/quill/quill.js')) ?>" defer></script><?php $this->endSection();
?>
<?php $this->section('content'); ?>
<form method="post" action="<?= e($editing ? route('admin.rubriques.update', ['id' => $rubrique['id']]) : route('admin.rubriques.store')) ?>" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-12 col-xl-7">
            <div class="df-card mb-3">
                <?= $this->partial('admin/partials/locale-tabs', ['id' => 'rub', 'filled' => array_map(static fn (array $r): bool => !empty($r['name']), $t), 'render' => function (string $code) use ($t): string {
                    ob_start(); $row = $t[$code] ?? []; $req = $code === \App\Core\Lang::getDefault(); ?>
                    <div class="mb-2"><label class="form-label" for="name_<?= e($code) ?>"><?= e(__('admin.rubriques.field.name')) ?><?= $req ? ' *' : '' ?></label><input type="text" class="form-control<?= error_for('name.' . $code) ? ' is-invalid' : '' ?>" id="name_<?= e($code) ?>" name="name[<?= e($code) ?>]" maxlength="120" value="<?= e(old('name.' . $code, $row['name'] ?? '')) ?>" <?= $req ? 'required' : '' ?>><?= $this->partial('partials/field-error', ['name' => 'name.' . $code]) ?><?php if (!empty($row['slug'])): ?><div class="form-text">URL : <span class="df-code ltr"><?= e($row['slug']) ?></span></div><?php endif ?></div>
                    <div class="mb-2"><label class="form-label" for="short_<?= e($code) ?>"><?= e(__('admin.rubriques.field.short_description')) ?></label><input type="text" class="form-control" id="short_<?= e($code) ?>" name="short_description[<?= e($code) ?>]" maxlength="255" value="<?= e($row['short_description'] ?? '') ?>"></div>
                    <div class="mb-2"><label class="form-label" for="desc_<?= e($code) ?>"><?= e(__('admin.rubriques.field.description')) ?></label><textarea class="form-control" id="desc_<?= e($code) ?>" name="description[<?= e($code) ?>]" rows="5" data-rich-editor><?= e($row['description'] ?? '') ?></textarea></div>
                    <div class="row g-2">
                        <div class="col-12 col-md-6"><label class="form-label" for="mt_<?= e($code) ?>"><?= e(__('admin.common.meta_title')) ?></label><input type="text" class="form-control" id="mt_<?= e($code) ?>" name="meta_title[<?= e($code) ?>]" maxlength="160" value="<?= e($row['meta_title'] ?? '') ?>"></div>
                        <div class="col-12 col-md-6"><label class="form-label" for="md_<?= e($code) ?>"><?= e(__('admin.common.meta_description')) ?></label><input type="text" class="form-control" id="md_<?= e($code) ?>" name="meta_description[<?= e($code) ?>]" maxlength="255" value="<?= e($row['meta_description'] ?? '') ?>"></div>
                    </div>
                    <?php return (string) ob_get_clean();
                }]) ?>
            </div>
        </div>
        <div class="col-12 col-xl-5">
            <div class="df-card mb-3">
                <div class="row g-3">
                    <div class="col-12 col-md-6"><label class="form-label" for="code"><?= e(__('admin.rubriques.field.code')) ?> *</label><input type="text" class="form-control<?= error_for('code') ? ' is-invalid' : '' ?>" id="code" name="code" required pattern="[a-z0-9-]{2,40}" value="<?= e(old('code', $rubrique['code'] ?? '')) ?>" dir="ltr"><?= $this->partial('partials/field-error', ['name' => 'code']) ?><div class="form-text"><?= e(__('admin.rubriques.field.code_help')) ?></div></div>
                    <div class="col-12 col-md-6"><label class="form-label" for="parent_id"><?= e(__('admin.rubriques.field.parent')) ?></label><select class="form-select" id="parent_id" name="parent_id"><option value="">—</option><?php foreach ($parents as $p): if ($editing && (int) $p['id'] === (int) $rubrique['id']) continue; ?><option value="<?= (int) $p['id'] ?>" <?= (string) old('parent_id', $rubrique['parent_id'] ?? '') === (string) $p['id'] ? 'selected' : '' ?>><?= e($p['name'] ?? $p['code']) ?></option><?php endforeach ?></select></div>
                    <div class="col-6"><label class="form-label" for="color"><?= e(__('admin.rubriques.field.color')) ?></label><input type="color" class="form-control form-control-color w-100" id="color" name="color" value="<?= e(old('color', $rubrique['color'] ?? '#0B5D45')) ?>"></div>
                    <div class="col-6"><label class="form-label" for="sort_order"><?= e(__('admin.common.sort_order')) ?></label><input type="number" class="form-control" id="sort_order" name="sort_order" min="0" max="999" value="<?= e(old('sort_order', $rubrique['sort_order'] ?? 0)) ?>"></div>
                    <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" <?= (int) old('is_active', $rubrique['is_active'] ?? 1) === 1 ? 'checked' : '' ?>><label class="form-check-label" for="is_active"><?= e(__('admin.common.active')) ?></label></div></div>
                </div>
            </div>
            <div class="df-card mb-3">
                <p class="fw-semibold mb-2"><?= e(__('admin.rubriques.field.icon')) ?></p>
                <div class="df-icon-choice mb-2">
                    <?php foreach ($icons as $i => $icon): ?>
                    <input type="radio" class="visually-hidden" name="icon" id="icon-<?= $i ?>" value="<?= e($icon) ?>" <?= old('icon', $rubrique['icon'] ?? '') === $icon ? 'checked' : '' ?>><label for="icon-<?= $i ?>"><img src="<?= e(asset($icon)) ?>" alt=""><?= e(pathinfo($icon, PATHINFO_FILENAME)) ?></label>
                    <?php endforeach ?>
                </div>
                <div class="form-text"><?= e(__('admin.rubriques.field.icon_help')) ?></div>
            </div>
            <div class="df-card">
                <label class="form-label" for="image"><?= e(__('admin.rubriques.field.image')) ?></label>
                <img id="image-preview" class="df-thumb mb-2 w-100" style="height:120px" src="<?= e($rubrique['image_path'] ?? '') ?>" alt="" <?= empty($rubrique['image_path']) ? 'hidden' : '' ?>>
                <input type="file" class="form-control<?= error_for('image') ? ' is-invalid' : '' ?>" id="image" name="image" accept="image/jpeg,image/png,image/webp" data-preview="#image-preview"><?= $this->partial('partials/field-error', ['name' => 'image']) ?>
                <div class="form-text"><?= e(__('admin.rubriques.field.image_help')) ?></div>
                <?php if (!empty($rubrique['image_path'])): ?><div class="form-check mt-2"><input class="form-check-input" type="checkbox" id="remove_image" name="remove_image" value="1"><label class="form-check-label" for="remove_image"><?= e(__('admin.common.remove_image')) ?></label></div><?php endif ?>
            </div>
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2 mt-3">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check2" aria-hidden="true"></i> <?= e(__('common.save')) ?></button>
        <a class="btn btn-outline-secondary" href="<?= e(route('admin.rubriques')) ?>"><?= e(__('common.cancel')) ?></a>
        <?php if ($editing): ?><span class="ms-auto"><?= $this->partial('admin/partials/post-button', ['url' => route('admin.rubriques.destroy', ['id' => $rubrique['id']]), 'label' => __('admin.common.delete'), 'icon' => 'trash', 'class' => 'btn btn-outline-danger', 'confirm' => __('admin.common.delete_confirm')]) ?></span><?php endif ?>
    </div>
</form>
<?php $this->endSection(); ?>
