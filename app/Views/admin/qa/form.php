<?php
/**
 * Édition d'une Q/R publique anonymisée.
 *
 * @var \App\Core\View $this
 * @var array<string, mixed> $publication
 * @var list<array<string, mixed>> $rubriques
 */
$this->extend('layouts/admin');
$t = $publication['translations'];
$this->section('title', e(__('admin.qa.edit_title')));
$this->section('subtitle', $publication['reference'] ? e(__('admin.qa.from_question', ['reference' => $publication['reference']])) : '');
$this->section('head'); ?><link rel="stylesheet" href="<?= e(asset('vendor/quill/quill.snow.css')) ?>"><?php $this->endSection();
$this->section('scripts'); ?><script src="<?= e(asset('vendor/quill/quill.js')) ?>" defer></script><?php $this->endSection();
$this->section('actions');
if ($publication['status'] === 'published' && route_exists('qa.show')) {
    foreach ($t as $code => $row) {
        echo '<a class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener" href="' . e(route('qa.show', ['slug' => $row['slug']], $code)) . '"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> ' . e(strtoupper($code)) . '</a>';
    }
}
$this->endSection();
?>
<?php $this->section('content'); ?>
<form method="post" action="<?= e(route('admin.qa.update', ['id' => $publication['id']])) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <div class="df-card">
                <p class="small text-secondary"><i class="bi bi-shield-check" aria-hidden="true"></i> <?= e(__('admin.qa.anonymity_reminder')) ?></p>
                <?= $this->partial('admin/partials/locale-tabs', ['id' => 'qa', 'filled' => array_map(static fn (array $r): bool => !empty($r['title']), $t), 'render' => function (string $code) use ($t): string {
                    ob_start(); $row = $t[$code] ?? []; ?>
                    <div class="mb-2"><label class="form-label" for="title_<?= e($code) ?>"><?= e(__('admin.qa.col.title')) ?></label><input type="text" class="form-control" id="title_<?= e($code) ?>" name="title[<?= e($code) ?>]" maxlength="200" value="<?= e($row['title'] ?? '') ?>"><?php if (!empty($row['slug'])): ?><div class="form-text">URL : <span class="df-code ltr"><?= e($row['slug']) ?></span></div><?php endif ?></div>
                    <div class="mb-2"><label class="form-label" for="qt_<?= e($code) ?>"><?= e(__('admin.questions.publish.question_text')) ?></label><textarea class="form-control" id="qt_<?= e($code) ?>" name="question_text[<?= e($code) ?>]" rows="4"><?= e(strip_tags(str_replace(['</p>', '<br>'], ["\n\n", "\n"], (string) ($row['question_text'] ?? '')))) ?></textarea></div>
                    <div class="mb-2"><label class="form-label" for="ah_<?= e($code) ?>"><?= e(__('admin.questions.publish.answer_html')) ?></label><textarea class="form-control" id="ah_<?= e($code) ?>" name="answer_html[<?= e($code) ?>]" rows="10" data-rich-editor><?= e($row['answer_html'] ?? '') ?></textarea></div>
                    <div><label class="form-label" for="md_<?= e($code) ?>"><?= e(__('admin.common.meta_description')) ?></label><input type="text" class="form-control" id="md_<?= e($code) ?>" name="meta_description[<?= e($code) ?>]" maxlength="255" value="<?= e($row['meta_description'] ?? '') ?>"></div>
                    <?php return (string) ob_get_clean();
                }]) ?>
            </div>
        </div>
        <div class="col-12 col-xl-4">
            <div class="df-card mb-3">
                <div class="mb-3"><label class="form-label" for="status"><?= e(__('admin.qa.col.status')) ?></label><select class="form-select" id="status" name="status"><?php foreach (['draft', 'published', 'archived'] as $s): ?><option value="<?= e($s) ?>" <?= $publication['status'] === $s ? 'selected' : '' ?>><?= e(__('admin.qa.status.' . $s)) ?></option><?php endforeach ?></select></div>
                <div class="mb-3"><label class="form-label" for="rubrique_id"><?= e(__('admin.questions.col.rubrique')) ?></label><select class="form-select" id="rubrique_id" name="rubrique_id"><?php foreach ($rubriques as $r): ?><option value="<?= (int) $r['id'] ?>" <?= (int) $publication['rubrique_id'] === (int) $r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option><?php endforeach ?></select></div>
                <?php if ($publication['audio_media_id']): ?><div class="form-check"><input class="form-check-input" type="checkbox" id="keep_audio" name="keep_audio" value="1" checked><label class="form-check-label" for="keep_audio"><?= e(__('admin.qa.keep_audio')) ?></label></div><?php endif ?>
                <dl class="df-dl df-dl--compact mt-3 mb-0">
                    <dt><?= e(__('admin.questions.col.scholar')) ?></dt><dd><?= e($publication['scholar_name'] ?? '—') ?></dd>
                    <dt><?= e(__('admin.qa.col.views')) ?></dt><dd><?= (int) $publication['views'] ?></dd>
                    <dt><?= e(__('admin.qa.col.published_at')) ?></dt><dd><?= e(format_date($publication['published_at'], true) ?: '—') ?></dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2 mt-3">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check2" aria-hidden="true"></i> <?= e(__('common.save')) ?></button>
        <a class="btn btn-outline-secondary" href="<?= e(route('admin.qa')) ?>"><?= e(__('common.cancel')) ?></a>
        <span class="ms-auto"><?= $this->partial('admin/partials/post-button', ['url' => route('admin.qa.destroy', ['id' => $publication['id']]), 'label' => __('admin.common.delete'), 'icon' => 'trash', 'class' => 'btn btn-outline-danger', 'confirm' => __('admin.common.delete_confirm')]) ?></span>
    </div>
</form>
<?php $this->endSection(); ?>
