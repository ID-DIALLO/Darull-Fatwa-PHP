<?php
/**
 * Édition d'une page statique (multilingue, éditeur riche, SEO).
 *
 * @var \App\Core\View $this
 * @var array<string, mixed>|null $page
 */
$this->extend('layouts/admin');
$editing = $page !== null;
$t = $page['translations'] ?? [];
$this->section('title', $editing ? e(__('admin.pages.edit_title', ['title' => $t[locale()]['title'] ?? $page['code']])) : e(__('admin.pages.create')));
$this->section('head'); ?><link rel="stylesheet" href="<?= e(asset('vendor/quill/quill.snow.css')) ?>"><?php $this->endSection();
$this->section('scripts'); ?><script src="<?= e(asset('vendor/quill/quill.js')) ?>" defer></script><?php $this->endSection();
$this->section('actions');
if ($editing) {
    foreach ($t as $code => $row) {
        echo '<a class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener" href="' . e(route('pages.show', ['slug' => $row['slug']], $code)) . '"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> ' . e(strtoupper($code)) . '</a>';
    }
}
$this->endSection();
?>
<?php $this->section('content'); ?>
<form method="post" action="<?= e($editing ? route('admin.pages.update', ['id' => $page['id']]) : route('admin.pages.store')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <div class="df-card">
                <?= $this->partial('admin/partials/locale-tabs', ['id' => 'pg', 'filled' => array_map(static fn (array $r): bool => !empty($r['title']), $t), 'render' => function (string $code) use ($t): string {
                    ob_start(); $row = $t[$code] ?? []; $req = $code === \App\Core\Lang::getDefault(); ?>
                    <div class="mb-2"><label class="form-label" for="title_<?= e($code) ?>"><?= e(__('admin.pages.col.title')) ?><?= $req ? ' *' : '' ?></label><input type="text" class="form-control<?= error_for('title.' . $code) ? ' is-invalid' : '' ?>" id="title_<?= e($code) ?>" name="title[<?= e($code) ?>]" maxlength="160" value="<?= e(old('title.' . $code, $row['title'] ?? '')) ?>" <?= $req ? 'required' : '' ?>><?= $this->partial('partials/field-error', ['name' => 'title.' . $code]) ?><?php if (!empty($row['slug'])): ?><div class="form-text">URL : <span class="df-code ltr">/<?= e($code) ?>/page/<?= e($row['slug']) ?></span></div><?php endif ?></div>
                    <div class="mb-2"><label class="form-label" for="content_<?= e($code) ?>"><?= e(__('admin.pages.content')) ?></label><textarea class="form-control" id="content_<?= e($code) ?>" name="content[<?= e($code) ?>]" rows="14" data-rich-editor><?= e($row['content'] ?? '') ?></textarea></div>
                    <div class="row g-2">
                        <div class="col-12 col-md-6"><label class="form-label" for="mt_<?= e($code) ?>"><?= e(__('admin.common.meta_title')) ?></label><input type="text" class="form-control" id="mt_<?= e($code) ?>" name="meta_title[<?= e($code) ?>]" maxlength="160" value="<?= e($row['meta_title'] ?? '') ?>"></div>
                        <div class="col-12 col-md-6"><label class="form-label" for="md_<?= e($code) ?>"><?= e(__('admin.common.meta_description')) ?></label><input type="text" class="form-control" id="md_<?= e($code) ?>" name="meta_description[<?= e($code) ?>]" maxlength="255" value="<?= e($row['meta_description'] ?? '') ?>"></div>
                    </div>
                    <?php return (string) ob_get_clean();
                }]) ?>
            </div>
        </div>
        <div class="col-12 col-xl-4">
            <div class="df-card">
                <?php if (!$editing): ?><div class="mb-3"><label class="form-label" for="code"><?= e(__('admin.rubriques.field.code')) ?> *</label><input type="text" class="form-control<?= error_for('code') ? ' is-invalid' : '' ?>" id="code" name="code" required pattern="[a-z0-9-]{2,40}" value="<?= e(old('code')) ?>" dir="ltr"><?= $this->partial('partials/field-error', ['name' => 'code']) ?></div>
                <?php else: ?><p class="mb-3"><?= e(__('admin.rubriques.field.code')) ?> : <span class="df-code"><?= e($page['code']) ?></span></p><?php endif ?>
                <div class="mb-3"><label class="form-label" for="sort_order"><?= e(__('admin.common.sort_order')) ?></label><input type="number" class="form-control" id="sort_order" name="sort_order" min="0" max="999" value="<?= e(old('sort_order', $page['sort_order'] ?? 0)) ?>"></div>
                <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" role="switch" id="show_in_footer" name="show_in_footer" value="1" <?= (int) old('show_in_footer', $page['show_in_footer'] ?? 1) === 1 ? 'checked' : '' ?>><label class="form-check-label" for="show_in_footer"><?= e(__('admin.pages.col.footer')) ?></label></div>
                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" <?= (int) old('is_active', $page['is_active'] ?? 1) === 1 ? 'checked' : '' ?>><label class="form-check-label" for="is_active"><?= e(__('admin.common.active')) ?></label></div>
            </div>
        </div>
    </div>
    <div class="d-flex gap-2 mt-3">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check2" aria-hidden="true"></i> <?= e(__('common.save')) ?></button>
        <a class="btn btn-outline-secondary" href="<?= e(route('admin.pages')) ?>"><?= e(__('common.cancel')) ?></a>
    </div>
</form>
<?php $this->endSection(); ?>
