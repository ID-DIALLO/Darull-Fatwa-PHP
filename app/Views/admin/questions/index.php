<?php
/**
 * Tableau des questions (§4.6.2) : filtres, tri, sélection groupée, export.
 *
 * @var \App\Core\View $this
 * @var array<string, string> $filters
 * @var array{data: list<array<string, mixed>>, total: int, page: int, last_page: int} $result
 * @var list<array<string, mixed>> $rubriques
 * @var list<array<string, mixed>> $scholars
 * @var list<string> $countries
 * @var int $lateHours
 */
use App\Models\Question;

$this->extend('layouts/admin');
$this->section('title', __('admin.questions.title'));
$this->section('subtitle', trans_choice('admin.questions.count', (int) $result['total']));
$active = array_filter($filters, static fn (string $v): bool => $v !== '');
$this->section('actions');
?>
<?php if (can('questions.change_status')): ?><a class="btn btn-outline-primary" href="<?= e(route('admin.questions.create')) ?>"><i class="bi bi-whatsapp" aria-hidden="true"></i> <?= e(__('admin.questions.create_from_whatsapp')) ?></a><?php endif ?>
<?php if (can('questions.export')): ?>
<div class="btn-group">
    <a class="btn btn-primary" href="<?= e(route('admin.questions.export', $active + ['format' => 'xlsx'])) ?>"><i class="bi bi-file-earmark-excel" aria-hidden="true"></i> Excel</a>
    <a class="btn btn-outline-primary" href="<?= e(route('admin.questions.export', $active + ['format' => 'csv'])) ?>">CSV</a>
</div>
<?php endif ?>
<?php $this->endSection(); ?>
<?php $this->section('content'); ?>
<form method="get" action="<?= e(route('admin.questions')) ?>" class="df-card df-filters">
    <div class="row g-2 align-items-end">
        <div class="col-12 col-md-4 col-xl-3">
            <label class="form-label" for="q"><?= e(__('admin.common.search')) ?></label>
            <input type="search" class="form-control" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(__('admin.questions.search_placeholder')) ?>">
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <label class="form-label" for="status"><?= e(__('admin.questions.col.status')) ?></label>
            <select class="form-select" id="status" name="status">
                <option value=""><?= e(__('admin.common.all')) ?></option>
                <option value="open" <?= $filters['status'] === 'open' ? 'selected' : '' ?>><?= e(__('admin.questions.filter.open')) ?></option>
                <option value="done" <?= $filters['status'] === 'done' ? 'selected' : '' ?>><?= e(__('admin.questions.filter.done')) ?></option>
                <?php foreach (Question::STATUSES as $s): ?><option value="<?= e($s) ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= e(__('questions.status.' . $s)) ?></option><?php endforeach ?>
            </select>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <label class="form-label" for="rubrique"><?= e(__('admin.questions.col.rubrique')) ?></label>
            <select class="form-select" id="rubrique" name="rubrique"><option value=""><?= e(__('admin.common.all')) ?></option><?php foreach ($rubriques as $r): ?><option value="<?= (int) $r['id'] ?>" <?= $filters['rubrique'] === (string) $r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option><?php endforeach ?></select>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <label class="form-label" for="scholar"><?= e(__('admin.questions.col.scholar')) ?></label>
            <select class="form-select" id="scholar" name="scholar"><option value=""><?= e(__('admin.common.all')) ?></option><option value="none" <?= $filters['scholar'] === 'none' ? 'selected' : '' ?>><?= e(__('admin.questions.filter.unassigned')) ?></option><?php foreach ($scholars as $s): ?><option value="<?= (int) $s['id'] ?>" <?= $filters['scholar'] === (string) $s['id'] ? 'selected' : '' ?>><?= e($s['full_name']) ?></option><?php endforeach ?></select>
        </div>
        <div class="col-6 col-md-4 col-xl-1">
            <label class="form-label" for="language"><?= e(__('admin.questions.col.language')) ?></label>
            <select class="form-select" id="language" name="language"><option value="">—</option><?php foreach (['fr', 'ar', 'en', 'bm', 'other'] as $l): ?><option value="<?= e($l) ?>" <?= $filters['language'] === $l ? 'selected' : '' ?>><?= e(__('questions.languages.' . $l)) ?></option><?php endforeach ?></select>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <label class="form-label" for="sort"><?= e(__('admin.common.sort')) ?></label>
            <select class="form-select" id="sort" name="sort"><?php foreach (\App\Services\Admin\AdminQuestionService::SORTS as $s): ?><option value="<?= e($s) ?>" <?= ($filters['sort'] ?: 'newest') === $s ? 'selected' : '' ?>><?= e(__('admin.questions.sort.' . $s)) ?></option><?php endforeach ?></select>
        </div>
        <div class="col-6 col-md-3 col-xl-2">
            <label class="form-label" for="channel"><?= e(__('admin.questions.col.channel')) ?></label>
            <select class="form-select" id="channel" name="channel"><option value="">—</option><?php foreach (['platform', 'whatsapp', 'both'] as $c): ?><option value="<?= e($c) ?>" <?= $filters['channel'] === $c ? 'selected' : '' ?>><?= e(__('questions.channels.' . $c)) ?></option><?php endforeach ?></select>
        </div>
        <div class="col-6 col-md-3 col-xl-2">
            <label class="form-label" for="type"><?= e(__('admin.questions.col.type')) ?></label>
            <select class="form-select" id="type" name="type"><option value="">—</option><?php foreach (['audio', 'text', 'mixed'] as $t): ?><option value="<?= e($t) ?>" <?= $filters['type'] === $t ? 'selected' : '' ?>><?= e(__('questions.types.' . $t)) ?></option><?php endforeach ?></select>
        </div>
        <div class="col-6 col-md-3 col-xl-2">
            <label class="form-label" for="source"><?= e(__('admin.questions.col.source')) ?></label>
            <select class="form-select" id="source" name="source"><option value="">—</option><?php foreach (['web', 'whatsapp', 'admin'] as $s): ?><option value="<?= e($s) ?>" <?= $filters['source'] === $s ? 'selected' : '' ?>><?= e(__('admin.questions.sources.' . $s)) ?></option><?php endforeach ?></select>
        </div>
        <div class="col-6 col-md-3 col-xl-1">
            <label class="form-label" for="country"><?= e(__('admin.questions.col.country')) ?></label>
            <select class="form-select" id="country" name="country"><option value="">—</option><?php foreach ($countries as $c): ?><option value="<?= e($c) ?>" <?= $filters['country'] === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach ?></select>
        </div>
        <div class="col-6 col-md-3 col-xl-2"><label class="form-label" for="from"><?= e(__('admin.common.from')) ?></label><input type="date" class="form-control" id="from" name="from" value="<?= e($filters['from']) ?>"></div>
        <div class="col-6 col-md-3 col-xl-2"><label class="form-label" for="to"><?= e(__('admin.common.to')) ?></label><input type="date" class="form-control" id="to" name="to" value="<?= e($filters['to']) ?>"></div>
        <div class="col-12 col-xl-3 d-flex flex-wrap gap-3 align-items-center pb-2">
            <div class="form-check"><input class="form-check-input" type="checkbox" id="late" name="late" value="1" <?= $filters['late'] === '1' ? 'checked' : '' ?>><label class="form-check-label" for="late"><?= e(__('admin.questions.filter.late')) ?></label></div>
            <div class="form-check"><input class="form-check-input" type="checkbox" id="review" name="review" value="1" <?= $filters['review'] === '1' ? 'checked' : '' ?>><label class="form-check-label" for="review"><?= e(__('admin.questions.filter.review')) ?></label></div>
            <div class="form-check"><input class="form-check-input" type="checkbox" id="visibility" name="visibility" value="publishable" <?= $filters['visibility'] === 'publishable' ? 'checked' : '' ?>><label class="form-check-label" for="visibility"><?= e(__('admin.questions.filter.publishable')) ?></label></div>
        </div>
        <div class="col-12 col-xl-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-funnel" aria-hidden="true"></i> <?= e(__('admin.common.filter')) ?></button>
            <?php if ($active !== []): ?><a class="btn btn-outline-secondary" href="<?= e(route('admin.questions')) ?>" title="<?= e(__('admin.common.reset')) ?>"><i class="bi bi-x-lg" aria-hidden="true"></i><span class="visually-hidden"><?= e(__('admin.common.reset')) ?></span></a><?php endif ?>
        </div>
    </div>
</form>

<?php if ($result['data'] === []): ?>
<?= $this->partial('admin/partials/empty', ['text' => __('admin.questions.empty')]) ?>
<?php else: ?>
<form method="post" action="<?= e(route('admin.questions.bulk')) ?>" id="bulk-form">
    <?= csrf_field() ?>
    <input type="hidden" name="return" value="<?= e(route('admin.questions', $active)) ?>">
    <div class="df-bulk" data-bulk-bar role="region" aria-label="<?= e(__('admin.questions.bulk.title')) ?>">
        <strong><span data-bulk-count>0</span> <?= e(__('admin.questions.bulk.selected')) ?></strong>
        <select class="form-select form-select-sm" name="action" data-bulk-action aria-label="<?= e(__('admin.questions.bulk.action')) ?>">
            <?php if (can('questions.assign')): ?><option value="assign"><?= e(__('admin.questions.bulk.assign')) ?></option><?php endif ?>
            <?php if (can('questions.change_status')): ?><option value="status"><?= e(__('admin.questions.bulk.status')) ?></option><option value="priority"><?= e(__('admin.questions.bulk.priority')) ?></option><?php endif ?>
            <?php if (can('questions.delete')): ?><option value="delete"><?= e(__('admin.questions.bulk.delete')) ?></option><?php endif ?>
        </select>
        <span data-bulk-panel="assign"><select class="form-select form-select-sm" name="scholar_id" aria-label="<?= e(__('admin.questions.col.scholar')) ?>"><?php foreach ($scholars as $s): ?><option value="<?= (int) $s['id'] ?>"><?= e($s['full_name']) ?> (<?= (int) $s['open_count'] ?>)</option><?php endforeach ?></select></span>
        <span data-bulk-panel="status" hidden><select class="form-select form-select-sm" name="status" aria-label="<?= e(__('admin.questions.col.status')) ?>"><?php foreach (Question::STATUSES as $s): ?><option value="<?= e($s) ?>"><?= e(__('questions.status.' . $s)) ?></option><?php endforeach ?></select></span>
        <span data-bulk-panel="priority" hidden><select class="form-select form-select-sm" name="priority" aria-label="<?= e(__('admin.questions.bulk.priority')) ?>"><option value="high"><?= e(__('admin.questions.priority.high')) ?></option><option value="normal"><?= e(__('admin.questions.priority.normal')) ?></option></select></span>
        <input type="text" class="form-control form-control-sm" name="comment" placeholder="<?= e(__('admin.questions.bulk.comment')) ?>" aria-label="<?= e(__('admin.questions.bulk.comment')) ?>">
        <button type="submit" class="btn btn-sm btn-primary"><?= e(__('admin.common.apply')) ?></button>
    </div>
</form>
<div class="df-table-wrap">
    <table class="table df-table" data-bulk-table>
        <thead>
            <tr>
                <th><input class="form-check-input" type="checkbox" data-bulk-all aria-label="<?= e(__('admin.common.select_all')) ?>"></th>
                <th><?= e(__('admin.questions.col.reference')) ?></th>
                <th><?= e(__('admin.questions.col.date')) ?></th>
                <th><?= e(__('admin.questions.col.member')) ?></th>
                <th><?= e(__('admin.questions.col.rubrique')) ?></th>
                <th><?= e(__('admin.questions.col.type')) ?></th>
                <th><?= e(__('admin.questions.col.language')) ?></th>
                <th><?= e(__('admin.questions.col.channel')) ?></th>
                <th><?= e(__('admin.questions.col.status')) ?></th>
                <th><?= e(__('admin.questions.col.scholar')) ?></th>
                <th><?= e(__('admin.questions.col.delay')) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($result['data'] as $q): $open = in_array($q['status'], ['new', 'assigned', 'in_progress'], true); $late = $open && (int) $q['age_hours'] >= $lateHours; ?>
            <tr class="<?= $late ? 'is-late' : '' ?>">
                <td><input class="form-check-input" type="checkbox" value="<?= (int) $q['id'] ?>" data-bulk-item form="bulk-form" aria-label="<?= e($q['reference']) ?>"></td>
                <td class="ref"><a href="<?= e(route('admin.questions.show', ['id' => $q['id']])) ?>"><?= e($q['reference']) ?></a><?php if ($q['priority'] === 'high'): ?> <i class="bi bi-exclamation-circle-fill text-danger" title="<?= e(__('admin.questions.priority.high')) ?>"></i><?php endif ?><?php if ((int) $q['pending_review'] > 0): ?> <i class="bi bi-eye-fill text-warning" title="<?= e(__('admin.questions.filter.review')) ?>"></i><?php endif ?></td>
                <td><?= e(format_date((string) $q['created_at'], true)) ?><br><small class="text-secondary"><?= e(time_ago((string) $q['created_at'])) ?></small></td>
                <td dir="auto">
                    <?php if (can('users.manage')): ?><a href="<?= e(route('admin.users.show', ['id' => $q['user_id']])) ?>"><?= e($q['member_name']) ?></a><?php else: ?><?= e($q['member_name']) ?><?php endif ?>
                    <br><small class="text-secondary ltr"><?= e($q['member_phone'] ?: ($q['member_email'] ?: '')) ?><?= $q['member_country'] ? ' · ' . e($q['member_country']) : '' ?></small>
                </td>
                <td><?= e($q['rubrique_name']) ?></td>
                <td>
                    <i class="bi bi-<?= $q['input_type'] === 'text' ? 'pencil' : 'mic-fill' ?>" aria-hidden="true"></i> <?= e(__('questions.types.' . $q['input_type'])) ?>
                    <?php if ($q['audio_uuid']): ?><br><audio controls preload="none" src="<?= e(route('media.audio', ['uuid' => $q['audio_uuid']])) ?>" class="df-quick-audio"></audio><?php endif ?>
                </td>
                <td class="ltr"><?= e(__('questions.languages.' . $q['spoken_language'])) ?> → <?= e(__('questions.languages.' . $q['answer_language'])) ?></td>
                <td><?= e(__('questions.channels.' . $q['answer_channel'])) ?><br><small class="text-secondary"><?= e(__('admin.questions.sources.' . $q['source'])) ?></small></td>
                <td><?= $this->partial('partials/status-badge', ['status' => $q['status']]) ?></td>
                <td dir="auto"><?= e($q['scholar_name'] ?? '—') ?></td>
                <td class="<?= $late ? 'df-late' : '' ?>">
                    <?php if ($q['first_response_minutes'] !== null): ?><?= e(__('admin.questions.answered_in', ['hours' => round(((int) $q['first_response_minutes']) / 60, 1)])) ?>
                    <?php elseif ($open): ?><?= e(__('admin.questions.waiting_for', ['hours' => (int) $q['age_hours']])) ?><?php else: ?>—<?php endif ?>
                </td>
            </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</div>
<div class="mt-3"><?= $this->partial('partials/pagination', ['result' => $result, 'query' => $active]) ?></div>
<?php endif ?>
<?php $this->endSection(); ?>
