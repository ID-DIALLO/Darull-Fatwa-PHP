<?php
/**
 * Journal d'activité (§4.6.3).
 *
 * @var \App\Core\View $this
 * @var array<string, string> $filters
 * @var array{data: list<array<string, mixed>>, total: int, page: int, last_page: int} $result
 * @var list<string> $actions
 * @var list<string> $subjects
 */
$this->extend('layouts/admin');
$this->section('title', __('admin.activity.title'));
$this->section('subtitle', trans_choice('admin.activity.count', (int) $result['total']));
$active = array_filter($filters);
?>
<?php $this->section('content'); ?>
<form method="get" action="<?= e(route('admin.activity_log')) ?>" class="df-card df-filters">
    <div class="row g-2 align-items-end">
        <div class="col-12 col-md-3"><label class="form-label" for="action"><?= e(__('admin.activity.col.action')) ?></label><input type="text" class="form-control" id="action" name="action" list="actions-list" value="<?= e($filters['action']) ?>" dir="ltr"><datalist id="actions-list"><?php foreach ($actions as $a): ?><option value="<?= e($a) ?>"></option><?php endforeach ?></datalist></div>
        <div class="col-6 col-md-2"><label class="form-label" for="subject"><?= e(__('admin.activity.col.subject')) ?></label><select class="form-select" id="subject" name="subject"><option value=""><?= e(__('admin.common.all')) ?></option><?php foreach ($subjects as $s): ?><option value="<?= e($s) ?>" <?= $filters['subject'] === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach ?></select></div>
        <div class="col-6 col-md-2"><label class="form-label" for="user"><?= e(__('admin.activity.col.user')) ?></label><input type="text" class="form-control" id="user" name="user" value="<?= e($filters['user']) ?>"></div>
        <div class="col-6 col-md-2"><label class="form-label" for="from"><?= e(__('admin.common.from')) ?></label><input type="date" class="form-control" id="from" name="from" value="<?= e($filters['from']) ?>"></div>
        <div class="col-6 col-md-2"><label class="form-label" for="to"><?= e(__('admin.common.to')) ?></label><input type="date" class="form-control" id="to" name="to" value="<?= e($filters['to']) ?>"></div>
        <div class="col-12 col-md-1 d-flex gap-1"><button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-funnel" aria-hidden="true"></i></button><?php if ($active !== []): ?><a class="btn btn-outline-secondary" href="<?= e(route('admin.activity_log')) ?>" aria-label="<?= e(__('admin.common.reset')) ?>"><i class="bi bi-x-lg" aria-hidden="true"></i></a><?php endif ?></div>
    </div>
</form>
<?php if ($result['data'] === []): ?><?= $this->partial('admin/partials/empty') ?><?php else: ?>
<div class="df-table-wrap">
    <table class="table df-table">
        <thead><tr><th><?= e(__('admin.activity.col.date')) ?></th><th><?= e(__('admin.activity.col.user')) ?></th><th><?= e(__('admin.activity.col.action')) ?></th><th><?= e(__('admin.activity.col.subject')) ?></th><th><?= e(__('admin.activity.col.details')) ?></th><th><?= e(__('admin.activity.col.ip')) ?></th></tr></thead>
        <tbody>
            <?php foreach ($result['data'] as $a): $details = json_decode((string) ($a['details'] ?? ''), true); ?>
            <tr>
                <td class="df-muted-cell text-nowrap"><?= e(format_date((string) $a['created_at'], true)) ?></td>
                <td dir="auto"><?php if ($a['user_id'] && can('users.manage')): ?><a href="<?= e(route('admin.users.show', ['id' => $a['user_id']])) ?>"><?= e($a['user_name'] ?? ('#' . $a['user_id'])) ?></a><?php else: ?><?= e($a['user_name'] ?? '—') ?><?php endif ?></td>
                <td><span class="df-code"><?= e($a['action']) ?></span><?php if (\App\Core\Lang::has('admin.activity.actions.' . $a['action'])): ?><br><small class="text-secondary"><?= e(__('admin.activity.actions.' . $a['action'])) ?></small><?php endif ?></td>
                <td class="small"><?php if ($a['subject_type']): ?><?= e($a['subject_type']) ?><?php if ($a['subject_id']): ?> #<?= (int) $a['subject_id'] ?><?php if ($a['subject_type'] === 'question' && can('questions.view_all')): ?> <a href="<?= e(route('admin.questions.show', ['id' => $a['subject_id']])) ?>" aria-label="<?= e(__('admin.common.open')) ?>"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i></a><?php endif ?><?php endif ?><?php else: ?>—<?php endif ?></td>
                <td class="small"><?php if (is_array($details) && $details !== []): ?><code class="small text-wrap"><?= e(json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></code><?php endif ?></td>
                <td class="df-muted-cell ltr"><?= e($a['ip_address'] ?? '') ?></td>
            </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</div>
<div class="mt-3"><?= $this->partial('partials/pagination', ['result' => $result, 'query' => $active]) ?></div>
<?php endif ?>
<?php $this->endSection(); ?>
