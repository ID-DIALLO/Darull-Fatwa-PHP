<?php
/**
 * File de travail du professeur : « Mes questions » / « À prendre » (§4.5).
 *
 * @var \App\Core\View $this
 * @var string $file mine | available
 * @var array{data: list<array<string, mixed>>, total: int, page: int, last_page: int} $result
 * @var array{sort: string, rubrique: string, language: string, type: string, status: string} $filters
 * @var list<array<string, mixed>> $rubriques
 * @var int $lateHours
 */
$this->extend('layouts/scholar');
$this->section('title', __($file === 'available' ? 'scholar.queue.title_available' : 'scholar.queue.title_mine'));
$this->section('subtitle', __($file === 'available' ? 'scholar.queue.intro_available' : 'scholar.queue.intro_mine'));
$query = ['file' => $file === 'available' ? 'a-prendre' : null, 'tri' => $filters['sort'], 'rubrique' => $filters['rubrique'], 'langue' => $filters['language'], 'type' => $filters['type'], 'statut' => $filters['status']];
?>
<?php $this->section('content'); ?>
<form method="get" action="<?= e(route('scholar.questions')) ?>" class="df-card df-filters mb-3">
    <?php if ($file === 'available'): ?><input type="hidden" name="file" value="a-prendre"><?php endif ?>
    <div class="row g-2 align-items-end">
        <div class="col-6 col-md-3">
            <label class="form-label" for="tri"><?= e(__('scholar.queue.sort')) ?></label>
            <select class="form-select" id="tri" name="tri">
                <?php foreach (['oldest', 'newest', 'rubrique', 'language', 'type'] as $sort): ?>
                <option value="<?= e($sort) ?>" <?= $filters['sort'] === $sort ? 'selected' : '' ?>><?= e(__('scholar.queue.sorts.' . $sort)) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <?php if ($file !== 'available'): ?>
        <div class="col-6 col-md-3">
            <label class="form-label" for="statut"><?= e(__('scholar.queue.status')) ?></label>
            <select class="form-select" id="statut" name="statut">
                <?php foreach (['open', 'assigned', 'in_progress', 'needs_info', 'done'] as $status): ?>
                <option value="<?= e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= e(__('scholar.queue.statuses.' . $status)) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <?php endif ?>
        <div class="col-6 col-md-2">
            <label class="form-label" for="rubrique"><?= e(__('questions.list.rubrique')) ?></label>
            <select class="form-select" id="rubrique" name="rubrique">
                <option value=""><?= e(__('scholar.queue.all')) ?></option>
                <?php foreach ($rubriques as $r): ?><option value="<?= (int) $r['id'] ?>" <?= (string) $filters['rubrique'] === (string) $r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option><?php endforeach ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label" for="langue"><?= e(__('scholar.queue.language')) ?></label>
            <select class="form-select" id="langue" name="langue">
                <option value=""><?= e(__('scholar.queue.all')) ?></option>
                <?php foreach (['fr', 'ar', 'en', 'bm', 'other'] as $lang): ?><option value="<?= e($lang) ?>" <?= $filters['language'] === $lang ? 'selected' : '' ?>><?= e(__('questions.languages.' . $lang)) ?></option><?php endforeach ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label" for="type"><?= e(__('scholar.queue.type')) ?></label>
            <select class="form-select" id="type" name="type">
                <option value=""><?= e(__('scholar.queue.all')) ?></option>
                <?php foreach (['audio', 'text', 'mixed'] as $type): ?><option value="<?= e($type) ?>" <?= $filters['type'] === $type ? 'selected' : '' ?>><?= e(__('questions.types.' . $type)) ?></option><?php endforeach ?>
            </select>
        </div>
        <div class="col-6 col-md-2 d-grid"><button type="submit" class="btn btn-outline-primary"><i class="bi bi-funnel" aria-hidden="true"></i> <?= e(__('questions.list.filter')) ?></button></div>
    </div>
</form>

<?php if ($result['data'] === []): ?>
<div class="df-empty"><i class="bi bi-<?= $file === 'available' ? 'hand-index-thumb' : 'inbox' ?>" aria-hidden="true"></i><p class="mb-0"><?= e(__($file === 'available' ? 'scholar.queue.empty_available' : 'scholar.queue.empty_mine')) ?></p></div>
<?php else: ?>
<p class="text-secondary small"><?= e(trans_choice('scholar.queue.count', (int) $result['total'])) ?></p>
<ul class="df-question-list">
    <?php foreach ($result['data'] as $q): $late = (int) $q['age_hours'] >= $lateHours && !in_array($q['status'], ['answered', 'published', 'closed', 'rejected'], true); ?>
    <li class="df-question-item">
        <div class="df-question-item__link df-question-item__link--static">
            <div class="df-question-item__head">
                <span class="df-question-item__ref" dir="ltr"><?= e($q['reference']) ?></span>
                <span class="d-flex flex-wrap gap-1">
                    <?php if ($late): ?><span class="df-badge df-badge--danger"><i class="bi bi-alarm" aria-hidden="true"></i> <?= e(__('scholar.queue.late_badge')) ?></span><?php endif ?>
                    <?php if ((int) $q['unread_follow_ups'] > 0): ?><span class="df-badge df-badge--info"><i class="bi bi-chat-left-dots" aria-hidden="true"></i> <?= e(__('scholar.queue.new_follow_up')) ?></span><?php endif ?>
                    <?= $this->partial('partials/status-badge', ['status' => $q['status']]) ?>
                </span>
            </div>
            <p class="df-question-item__title" dir="auto"><i class="bi bi-<?= $q['input_type'] === 'text' ? 'pencil' : 'mic-fill' ?>" aria-hidden="true"></i> <?= e($q['title'] ?: __('questions.list.untitled')) ?></p>
            <p class="df-question-item__meta">
                <span><img src="<?= e(asset((string) $q['rubrique_icon'])) ?>" alt="" width="18" height="18"> <?= e($q['rubrique_name']) ?></span>
                <span><i class="bi bi-translate" aria-hidden="true"></i> <?= e(__('questions.languages.' . $q['spoken_language'])) ?> → <?= e(__('questions.languages.' . $q['answer_language'])) ?></span>
                <span><i class="bi bi-person" aria-hidden="true"></i> <?= e($q['member_display_name'] ?: $q['member_name']) ?></span>
                <span><i class="bi bi-<?= $q['source'] === 'whatsapp' ? 'whatsapp' : 'globe2' ?>" aria-hidden="true"></i> <?= e(__('scholar.question.source_' . $q['source'])) ?></span>
                <?php if ((int) ($q['requested_scholar_id'] ?? 0) === (int) auth()['id']): ?><span class="df-badge df-badge--gold"><i class="bi bi-star-fill" aria-hidden="true"></i> <?= e(__('scholar.question.requested_you')) ?></span><?php endif ?>
                <span><i class="bi bi-clock" aria-hidden="true"></i> <?= e(__('scholar.queue.age', ['ago' => time_ago((string) $q['created_at'])])) ?></span>
            </p>
            <div class="d-flex flex-wrap gap-2 mt-2">
                <a class="btn btn-sm btn-primary" href="<?= e(route('scholar.questions.show', ['reference' => $q['reference']])) ?>"><i class="bi bi-eye" aria-hidden="true"></i> <?= e(__('scholar.queue.open')) ?></a>
                <?php if ($file === 'available'): ?>
                <form method="post" action="<?= e(route('scholar.questions.take', ['reference' => $q['reference']])) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm btn-gold"><i class="bi bi-hand-index-thumb" aria-hidden="true"></i> <?= e(__('scholar.queue.take')) ?></button>
                </form>
                <?php endif ?>
            </div>
        </div>
    </li>
    <?php endforeach ?>
</ul>
<?= $this->partial('partials/pagination', ['result' => $result, 'query' => array_filter($query, static fn ($v) => $v !== null)]) ?>
<?php endif ?>
<?php $this->endSection(); ?>
