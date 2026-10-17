<?php
/**
 * Bouton « Signaler » avec formulaire repliable (membre connecté).
 *
 * @var string $type forum_topic|forum_post|comment|idea|idea_comment
 * @var int $id
 */
if (auth() === null) {
    return;
}
$uid = 'report-' . $type . '-' . $id;
?>
<details class="df-report d-inline-block">
    <summary class="btn btn-link btn-sm p-0 text-secondary"><i class="bi bi-flag" aria-hidden="true"></i> <?= e(__('forum.report.button')) ?></summary>
    <form method="post" action="<?= e(route('forum.report')) ?>" class="df-report__form">
        <?= csrf_field() ?>
        <input type="hidden" name="type" value="<?= e($type) ?>"><input type="hidden" name="id" value="<?= (int) $id ?>">
        <label class="form-label small" for="<?= e($uid) ?>-reason"><?= e(__('forum.report.reason')) ?></label>
        <select class="form-select form-select-sm mb-2" id="<?= e($uid) ?>-reason" name="reason"><?php foreach (\App\Services\ForumService::REPORT_REASONS as $r): ?><option value="<?= e($r) ?>"><?= e(__('forum.report.reasons.' . $r)) ?></option><?php endforeach ?></select>
        <input type="text" class="form-control form-control-sm mb-2" name="details" maxlength="500" placeholder="<?= e(__('forum.report.details')) ?>" aria-label="<?= e(__('forum.report.details')) ?>">
        <button type="submit" class="btn btn-sm btn-outline-danger"><?= e(__('forum.report.submit')) ?></button>
    </form>
</details>
