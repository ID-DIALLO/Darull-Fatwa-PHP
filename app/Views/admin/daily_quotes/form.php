<?php
/**
 * Création / modification d'un verset ou hadith du jour.
 *
 * @var \App\Core\View $this
 * @var array<string, mixed>|null $quote
 */
$this->extend('layouts/admin');
$editing = $quote !== null;
$t = $quote['translations'] ?? [];
$this->section('title', $editing ? e(__('admin.quotes.edit_title')) : e(__('admin.quotes.create')));
?>
<?php $this->section('content'); ?>
<form method="post" action="<?= e($editing ? route('admin.daily_quotes.update', ['id' => $quote['id']]) : route('admin.daily_quotes.store')) ?>" class="df-measure-wide" novalidate>
    <?= csrf_field() ?>
    <div class="df-card mb-3">
        <div class="row g-3">
            <div class="col-6 col-md-3"><label class="form-label" for="type"><?= e(__('admin.quotes.col.type')) ?></label><select class="form-select" id="type" name="type"><?php foreach (['ayah', 'hadith'] as $type): ?><option value="<?= e($type) ?>" <?= old('type', $quote['type'] ?? 'ayah') === $type ? 'selected' : '' ?>><?= e(__('admin.quotes.type.' . $type)) ?></option><?php endforeach ?></select></div>
            <div class="col-6 col-md-5"><label class="form-label" for="reference"><?= e(__('admin.quotes.col.reference')) ?> *</label><input type="text" class="form-control<?= error_for('reference') ? ' is-invalid' : '' ?>" id="reference" name="reference" maxlength="160" required value="<?= e(old('reference', $quote['reference'] ?? '')) ?>" placeholder="<?= e(__('admin.quotes.reference_placeholder')) ?>"><?= $this->partial('partials/field-error', ['name' => 'reference']) ?></div>
            <div class="col-6 col-md-3"><label class="form-label" for="display_date"><?= e(__('admin.quotes.col.date')) ?></label><input type="date" class="form-control" id="display_date" name="display_date" value="<?= e(old('display_date', $quote['display_date'] ?? '')) ?>"><div class="form-text"><?= e(__('admin.quotes.date_help')) ?></div></div>
            <div class="col-6 col-md-1 d-flex align-items-end"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" <?= (int) old('is_active', $quote['is_active'] ?? 1) === 1 ? 'checked' : '' ?>><label class="form-check-label" for="is_active"><?= e(__('admin.common.active')) ?></label></div></div>
            <div class="col-12"><label class="form-label" for="arabic_text"><?= e(__('admin.quotes.arabic_text')) ?> *</label><textarea class="form-control df-verse__text<?= error_for('arabic_text') ? ' is-invalid' : '' ?>" id="arabic_text" name="arabic_text" rows="3" lang="ar" dir="rtl" required><?= e(old('arabic_text', $quote['arabic_text'] ?? '')) ?></textarea><?= $this->partial('partials/field-error', ['name' => 'arabic_text']) ?><div class="form-text"><?= e(__('admin.quotes.arabic_help')) ?></div></div>
            <?php foreach (\App\Core\Lang::locales() as $code): if ($code === 'ar') continue; ?>
            <div class="col-12 col-md-6"><label class="form-label" for="tr_<?= e($code) ?>"><?= e(__('admin.quotes.translation', ['lang' => __('common.lang.' . $code)])) ?></label><textarea class="form-control" id="tr_<?= e($code) ?>" name="translation[<?= e($code) ?>]" rows="3" lang="<?= e($code) ?>" dir="ltr"><?= e($t[$code]['translation'] ?? '') ?></textarea></div>
            <?php endforeach ?>
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check2" aria-hidden="true"></i> <?= e(__('common.save')) ?></button>
        <a class="btn btn-outline-secondary" href="<?= e(route('admin.daily_quotes')) ?>"><?= e(__('common.cancel')) ?></a>
        <?php if ($editing): ?><span class="ms-auto"><?= $this->partial('admin/partials/post-button', ['url' => route('admin.daily_quotes.destroy', ['id' => $quote['id']]), 'label' => __('admin.common.delete'), 'icon' => 'trash', 'class' => 'btn btn-outline-danger', 'confirm' => __('admin.common.delete_confirm')]) ?></span><?php endif ?>
    </div>
</form>
<?php $this->endSection(); ?>
