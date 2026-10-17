<?php
/**
 * Profil public du professeur (§4.5).
 *
 * @var \App\Core\View $this
 * @var array<string, mixed>|null $profile
 * @var array<string, array<string, mixed>> $translations
 * @var list<array<string, mixed>> $rubriques
 * @var array<string, mixed> $scholar
 */
$this->extend('layouts/scholar');
$this->section('title', __('scholar.profile.title'));
$this->section('subtitle', __('scholar.profile.intro'));
$languages = array_filter(explode(',', (string) ($profile['languages'] ?? 'fr')));
?>
<?php $this->section('content'); ?>
<?php if ($profile === null || (int) $profile['is_public'] !== 1): ?><div class="alert alert-info" role="status"><?= e(__('scholar.profile.not_public')) ?></div><?php endif ?>
<?php if (!empty($profile['requires_review'])): ?><div class="alert alert-warning" role="status"><i class="bi bi-eye" aria-hidden="true"></i> <?= e(__('scholar.profile.review_required')) ?></div><?php endif ?>
<form method="post" action="<?= e(route('scholar.profile.update')) ?>" enctype="multipart/form-data" class="df-form" novalidate>
    <?= csrf_field() ?>
    <div class="df-card mb-4">
        <div class="d-flex align-items-center gap-3 mb-3">
            <?php if (!empty($profile['photo_path'])): ?><img class="df-avatar df-avatar--lg" src="<?= e($profile['photo_path']) ?>" alt="" width="80" height="80"><?php else: ?><span class="df-avatar df-avatar--lg df-avatar--initials" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) $scholar['full_name'], 0, 1))) ?></span><?php endif ?>
            <div class="flex-grow-1">
                <label class="form-label" for="photo"><?= e(__('scholar.profile.photo')) ?></label>
                <input type="file" class="form-control<?= error_for('photo') !== null ? ' is-invalid' : '' ?>" id="photo" name="photo" accept="image/jpeg,image/png,image/webp">
                <div class="form-text"><?= e(__('scholar.profile.photo_help')) ?></div>
                <?= $this->partial('partials/field-error', ['name' => 'photo']) ?>
                <?php if (!empty($profile['photo_path'])): ?><div class="form-check mt-1"><input class="form-check-input" type="checkbox" id="remove_photo" name="remove_photo" value="1"><label class="form-check-label" for="remove_photo"><?= e(__('scholar.profile.remove_photo')) ?></label></div><?php endif ?>
            </div>
        </div>
        <?php foreach (\App\Core\Lang::locales() as $code): ?>
        <fieldset class="df-fieldset" lang="<?= e($code) ?>" dir="<?= e(text_dir($code)) ?>">
            <legend><?= e(__('scholar.profile.in_lang', ['lang' => __('common.lang.' . $code)])) ?></legend>
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label class="form-label" for="title_<?= e($code) ?>"><?= e(__('scholar.profile.title_field')) ?></label>
                    <input type="text" class="form-control" id="title_<?= e($code) ?>" name="title[<?= e($code) ?>]" value="<?= e($translations[$code]['title'] ?? '') ?>" maxlength="80">
                </div>
                <div class="col-12 col-md-8">
                    <label class="form-label" for="bio_<?= e($code) ?>"><?= e(__('scholar.profile.bio')) ?></label>
                    <textarea class="form-control" id="bio_<?= e($code) ?>" name="bio[<?= e($code) ?>]" rows="4"><?= e($translations[$code]['bio'] ?? '') ?></textarea>
                </div>
            </div>
        </fieldset>
        <?php endforeach ?>
        <fieldset class="mb-3">
            <legend class="form-label"><?= e(__('scholar.profile.languages')) ?></legend>
            <div class="d-flex flex-wrap gap-3">
                <?php foreach (['fr', 'ar', 'en', 'bm', 'other'] as $lang): ?>
                <div class="form-check"><input class="form-check-input" type="checkbox" id="lang_<?= e($lang) ?>" name="languages[]" value="<?= e($lang) ?>" <?= in_array($lang, $languages, true) ? 'checked' : '' ?>><label class="form-check-label" for="lang_<?= e($lang) ?>"><?= e(__('front.scholars.lang.' . $lang)) ?></label></div>
                <?php endforeach ?>
            </div>
        </fieldset>
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" role="switch" id="is_available" name="is_available" value="1" <?= ($profile['is_available'] ?? 1) ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_available"><?= e(__('scholar.profile.available')) ?></label>
        </div>
        <p class="fw-semibold mb-1"><?= e(__('scholar.profile.rubriques')) ?></p>
        <?php if ($rubriques === []): ?><p class="text-secondary small"><?= e(__('scholar.dashboard.no_rubriques')) ?></p><?php else: ?>
        <ul class="df-chips mb-3"><?php foreach ($rubriques as $r): ?><li><span class="df-chip"><img src="<?= e(asset((string) $r['icon'])) ?>" alt="" width="22" height="22"> <?= e($r['name']) ?></span></li><?php endforeach ?></ul>
        <?php endif ?>
        <div class="d-flex flex-wrap gap-2">
            <button type="submit" class="btn btn-primary"><i class="bi bi-check2" aria-hidden="true"></i> <?= e(__('common.save')) ?></button>
            <?php if (!empty($profile['is_public'])): ?><a class="btn btn-outline-primary" href="<?= e(route('scholars.show', ['slug' => \App\Models\ScholarProfile::slug($scholar)])) ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> <?= e(__('scholar.profile.preview')) ?></a><?php endif ?>
        </div>
    </div>
</form>
<?php $this->endSection(); ?>
