<?php
/**
 * Fiche professeur : rubriques, langues, charge, options, photo, titre et biographie traduits.
 *
 * @var \App\Core\View $this
 * @var array<string, mixed> $scholar
 * @var list<array<string, mixed>> $rubriques
 */
$this->extend('layouts/admin');
$this->section('title', e(__('admin.scholars.edit_title', ['name' => $scholar['full_name']])));
$profile = $scholar['profile'];
$languages = array_filter(explode(',', (string) ($profile['languages'] ?? 'fr')));
$t = $scholar['translations'];
?>
<?php $this->section('content'); ?>
<form method="post" action="<?= e(route('admin.scholars.update', ['id' => $scholar['id']])) ?>" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-12 col-xl-7">
            <div class="df-card mb-3">
                <h2 class="df-card__title h6"><?= e(__('admin.scholars.section_assignment')) ?></h2>
                <p class="fw-semibold mb-1"><?= e(__('admin.scholars.col.rubriques')) ?></p>
                <div class="row g-1 mb-3">
                    <?php foreach ($rubriques as $r): ?>
                    <div class="col-12 col-md-6"><div class="form-check<?= $r['depth'] > 0 ? ' ms-4' : '' ?>"><input class="form-check-input" type="checkbox" name="rubriques[]" id="rub-<?= (int) $r['id'] ?>" value="<?= (int) $r['id'] ?>" <?= in_array((int) $r['id'], $scholar['rubrique_ids'], true) ? 'checked' : '' ?>><label class="form-check-label" for="rub-<?= (int) $r['id'] ?>"><?= e($r['name']) ?></label></div></div>
                    <?php endforeach ?>
                </div>
                <p class="fw-semibold mb-1"><?= e(__('admin.scholars.col.languages')) ?></p>
                <div class="d-flex flex-wrap gap-3 mb-3">
                    <?php foreach (['fr', 'ar', 'en', 'bm', 'other'] as $l): ?>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="languages[]" id="lang-<?= e($l) ?>" value="<?= e($l) ?>" <?= in_array($l, $languages, true) ? 'checked' : '' ?>><label class="form-check-label" for="lang-<?= e($l) ?>"><?= e(__('front.scholars.lang.' . $l)) ?></label></div>
                    <?php endforeach ?>
                </div>
                <div class="row g-3">
                    <div class="col-6 col-md-4"><label class="form-label" for="max_open_questions"><?= e(__('admin.scholars.max_open')) ?></label><input type="number" class="form-control" id="max_open_questions" name="max_open_questions" min="1" max="500" value="<?= e(old('max_open_questions', $profile['max_open_questions'] ?? 20)) ?>"></div>
                    <div class="col-6 col-md-4"><label class="form-label" for="sort_order"><?= e(__('admin.common.sort_order')) ?></label><input type="number" class="form-control" id="sort_order" name="sort_order" min="0" max="999" value="<?= e(old('sort_order', $profile['sort_order'] ?? 0)) ?>"></div>
                </div>
                <div class="row g-2 mt-2">
                    <?php foreach (['can_self_assign' => 0, 'requires_review' => 0, 'is_public' => 1, 'is_available' => 1] as $key => $default): ?>
                    <div class="col-12 col-md-6"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="<?= e($key) ?>" name="<?= e($key) ?>" value="1" <?= (int) ($profile[$key] ?? $default) === 1 ? 'checked' : '' ?>><label class="form-check-label" for="<?= e($key) ?>"><?= e(__('admin.scholars.opt.' . $key)) ?></label><div class="form-text"><?= e(__('admin.scholars.opt_help.' . $key)) ?></div></div></div>
                    <?php endforeach ?>
                </div>
            </div>
            <div class="df-card mb-3">
                <h2 class="df-card__title h6"><?= e(__('admin.scholars.section_public')) ?></h2>
                <?= $this->partial('admin/partials/locale-tabs', ['id' => 'bio', 'filled' => array_map(static fn (array $r): bool => !empty($r['title']) || !empty($r['bio']), $t), 'render' => function (string $code) use ($t): string {
                    ob_start(); ?>
                    <div class="mb-2"><label class="form-label" for="title_<?= e($code) ?>"><?= e(__('scholar.profile.title_field')) ?></label><input type="text" class="form-control" id="title_<?= e($code) ?>" name="title[<?= e($code) ?>]" maxlength="80" value="<?= e($t[$code]['title'] ?? '') ?>"></div>
                    <div><label class="form-label" for="bio_<?= e($code) ?>"><?= e(__('scholar.profile.bio')) ?></label><textarea class="form-control" id="bio_<?= e($code) ?>" name="bio[<?= e($code) ?>]" rows="5"><?= e($t[$code]['bio'] ?? '') ?></textarea></div>
                    <?php return (string) ob_get_clean();
                }]) ?>
            </div>
        </div>
        <div class="col-12 col-xl-5">
            <div class="df-card mb-3">
                <h2 class="df-card__title h6"><?= e(__('scholar.profile.photo')) ?></h2>
                <img class="df-avatar df-avatar--xl mb-2" id="photo-preview" src="<?= e($profile['photo_path'] ?? '') ?>" alt="" width="120" height="120" <?= empty($profile['photo_path']) ? 'hidden' : '' ?>>
                <input type="file" class="form-control<?= error_for('photo') ? ' is-invalid' : '' ?>" name="photo" accept="image/jpeg,image/png,image/webp" data-preview="#photo-preview" aria-label="<?= e(__('scholar.profile.photo')) ?>">
                <?= $this->partial('partials/field-error', ['name' => 'photo']) ?>
                <div class="form-text"><?= e(__('scholar.profile.photo_help')) ?></div>
                <?php if (!empty($profile['photo_path'])): ?><div class="form-check mt-2"><input class="form-check-input" type="checkbox" id="remove_photo" name="remove_photo" value="1"><label class="form-check-label" for="remove_photo"><?= e(__('scholar.profile.remove_photo')) ?></label></div><?php endif ?>
            </div>
            <div class="df-card">
                <dl class="df-dl df-dl--compact mb-0">
                    <dt><?= e(__('admin.users.col.email')) ?></dt><dd class="ltr"><?= e($scholar['email'] ?? '—') ?></dd>
                    <dt><?= e(__('admin.users.col.phone')) ?></dt><dd class="ltr"><?= e($scholar['phone'] ?? '—') ?></dd>
                    <dt><?= e(__('admin.users.col.status')) ?></dt><dd><?= e(__('admin.users.status.' . $scholar['status'])) ?></dd>
                </dl>
                <a class="small" href="<?= e(route('admin.users.show', ['id' => $scholar['id']])) ?>"><?= e(__('admin.scholars.user_sheet')) ?></a>
            </div>
        </div>
    </div>
    <div class="d-flex gap-2 mt-3">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check2" aria-hidden="true"></i> <?= e(__('common.save')) ?></button>
        <a class="btn btn-outline-secondary" href="<?= e(route('admin.scholars')) ?>"><?= e(__('common.cancel')) ?></a>
    </div>
</form>
<?php $this->endSection(); ?>
