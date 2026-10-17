<?php
/**
 * Création / modification d'une bannière.
 *
 * @var \App\Core\View $this
 * @var array<string, mixed>|null $banner
 * @var list<string> $routes
 */
$this->extend('layouts/admin');
$editing = $banner !== null;
$t = $banner['translations'] ?? [];
$this->section('title', $editing ? e(__('admin.banners.edit_title')) : e(__('admin.banners.create')));
$dt = static fn (?string $utc): string => $utc ? (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone((string) config('app.timezone', 'UTC')))->format('Y-m-d\TH:i') : '';
?>
<?php $this->section('content'); ?>
<form method="post" action="<?= e($editing ? route('admin.banners.update', ['id' => $banner['id']]) : route('admin.banners.store')) ?>" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-12 col-xl-7">
            <div class="df-card mb-3">
                <?= $this->partial('admin/partials/locale-tabs', ['id' => 'ban', 'filled' => array_map(static fn (array $r): bool => !empty($r['title']), $t), 'render' => function (string $code) use ($t): string {
                    ob_start(); $row = $t[$code] ?? []; $req = $code === \App\Core\Lang::getDefault(); ?>
                    <div class="mb-2"><label class="form-label" for="title_<?= e($code) ?>"><?= e(__('admin.banners.field.title')) ?><?= $req ? ' *' : '' ?></label><input type="text" class="form-control<?= error_for('title.' . $code) ? ' is-invalid' : '' ?>" id="title_<?= e($code) ?>" name="title[<?= e($code) ?>]" maxlength="160" value="<?= e(old('title.' . $code, $row['title'] ?? '')) ?>" <?= $req ? 'required' : '' ?>><?= $this->partial('partials/field-error', ['name' => 'title.' . $code]) ?></div>
                    <div class="mb-2"><label class="form-label" for="subtitle_<?= e($code) ?>"><?= e(__('admin.banners.field.subtitle')) ?></label><textarea class="form-control" id="subtitle_<?= e($code) ?>" name="subtitle[<?= e($code) ?>]" rows="2" maxlength="300"><?= e($row['subtitle'] ?? '') ?></textarea></div>
                    <div class="row g-2">
                        <div class="col-6"><label class="form-label" for="b1_<?= e($code) ?>"><?= e(__('admin.banners.field.button_label')) ?></label><input type="text" class="form-control" id="b1_<?= e($code) ?>" name="button_label[<?= e($code) ?>]" maxlength="60" value="<?= e($row['button_label'] ?? '') ?>"></div>
                        <div class="col-6"><label class="form-label" for="b2_<?= e($code) ?>"><?= e(__('admin.banners.field.button2_label')) ?></label><input type="text" class="form-control" id="b2_<?= e($code) ?>" name="button2_label[<?= e($code) ?>]" maxlength="60" value="<?= e($row['button2_label'] ?? '') ?>"></div>
                        <div class="col-12"><label class="form-label" for="alt_<?= e($code) ?>"><?= e(__('admin.banners.field.image_alt')) ?></label><input type="text" class="form-control" id="alt_<?= e($code) ?>" name="image_alt[<?= e($code) ?>]" maxlength="160" value="<?= e($row['image_alt'] ?? '') ?>"></div>
                    </div>
                    <?php return (string) ob_get_clean();
                }]) ?>
            </div>
            <div class="df-card">
                <div class="row g-3">
                    <?php foreach (['link_url' => 'button_label', 'link2_url' => 'button2_label'] as $field => $_): ?>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="<?= e($field) ?>"><?= e(__('admin.banners.field.' . $field)) ?></label>
                        <input type="text" class="form-control" id="<?= e($field) ?>" name="<?= e($field) ?>" list="routes-list" value="<?= e(old($field, $banner[$field] ?? '')) ?>" dir="ltr" placeholder="route:questions.create">
                    </div>
                    <?php endforeach ?>
                    <datalist id="routes-list"><?php foreach ($routes as $r): ?><option value="route:<?= e($r) ?>"></option><?php endforeach ?></datalist>
                    <div class="col-12"><div class="form-text"><?= e(__('admin.banners.field.link_help')) ?></div></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-5">
            <div class="df-card mb-3">
                <label class="form-label" for="image_desktop"><?= e(__('admin.banners.field.image_desktop')) ?><?= $editing ? '' : ' *' ?></label>
                <img id="desktop-preview" class="df-thumb w-100 mb-2" style="height:160px" src="<?= e($banner['image_desktop'] ?? '') ?>" alt="" <?= empty($banner['image_desktop']) ? 'hidden' : '' ?>>
                <input type="file" class="form-control<?= error_for('image_desktop') ? ' is-invalid' : '' ?>" id="image_desktop" name="image_desktop" accept="image/jpeg,image/png,image/webp" data-preview="#desktop-preview"><?= $this->partial('partials/field-error', ['name' => 'image_desktop']) ?>
                <div class="form-text"><?= e(__('admin.banners.field.image_desktop_help')) ?></div>
                <hr>
                <label class="form-label" for="image_mobile"><?= e(__('admin.banners.field.image_mobile')) ?></label>
                <img id="mobile-preview" class="df-thumb mb-2" style="width:90px;height:120px" src="<?= e($banner['image_mobile'] ?? '') ?>" alt="" <?= empty($banner['image_mobile']) ? 'hidden' : '' ?>>
                <input type="file" class="form-control<?= error_for('image_mobile') ? ' is-invalid' : '' ?>" id="image_mobile" name="image_mobile" accept="image/jpeg,image/png,image/webp" data-preview="#mobile-preview"><?= $this->partial('partials/field-error', ['name' => 'image_mobile']) ?>
                <?php if (!empty($banner['image_mobile'])): ?><div class="form-check mt-2"><input class="form-check-input" type="checkbox" id="remove_image_mobile" name="remove_image_mobile" value="1"><label class="form-check-label" for="remove_image_mobile"><?= e(__('admin.common.remove_image')) ?></label></div><?php endif ?>
                <p class="small text-secondary mt-2 mb-0"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> <?= e(__('admin.banners.no_figurative')) ?></p>
            </div>
            <div class="df-card">
                <div class="row g-3">
                    <div class="col-6"><label class="form-label" for="starts_at"><?= e(__('admin.banners.field.starts_at')) ?></label><input type="datetime-local" class="form-control" id="starts_at" name="starts_at" value="<?= e(old('starts_at', $dt($banner['starts_at'] ?? null))) ?>"></div>
                    <div class="col-6"><label class="form-label" for="ends_at"><?= e(__('admin.banners.field.ends_at')) ?></label><input type="datetime-local" class="form-control" id="ends_at" name="ends_at" value="<?= e(old('ends_at', $dt($banner['ends_at'] ?? null))) ?>"></div>
                    <div class="col-6"><label class="form-label" for="sort_order"><?= e(__('admin.common.sort_order')) ?></label><input type="number" class="form-control" id="sort_order" name="sort_order" min="0" max="999" value="<?= e(old('sort_order', $banner['sort_order'] ?? 0)) ?>"></div>
                    <div class="col-6 d-flex align-items-end"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" <?= (int) old('is_active', $banner['is_active'] ?? 1) === 1 ? 'checked' : '' ?>><label class="form-check-label" for="is_active"><?= e(__('admin.common.active')) ?></label></div></div>
                </div>
            </div>
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2 mt-3">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check2" aria-hidden="true"></i> <?= e(__('common.save')) ?></button>
        <a class="btn btn-outline-secondary" href="<?= e(route('admin.banners')) ?>"><?= e(__('common.cancel')) ?></a>
        <?php if ($editing): ?><span class="ms-auto"><?= $this->partial('admin/partials/post-button', ['url' => route('admin.banners.destroy', ['id' => $banner['id']]), 'label' => __('admin.common.delete'), 'icon' => 'trash', 'class' => 'btn btn-outline-danger', 'confirm' => __('admin.common.delete_confirm')]) ?></span><?php endif ?>
    </div>
</form>
<?php $this->endSection(); ?>
