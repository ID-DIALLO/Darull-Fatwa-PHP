<?php
/**
 * Mes questions (§4.4) : onglets, filtres, recherche, pagination.
 *
 * @var \App\Core\View $this
 * @var array{data: list<array<string, mixed>>, total: int, page: int, last_page: int} $result
 * @var array{tab: string, rubrique: string, period: string, q: string} $filters
 * @var array<string, int> $counts
 * @var list<array<string, mixed>> $rubriques
 */
$this->extend('layouts/account');
$this->section('title', __('questions.list.title'));
$this->section('subtitle', __('questions.list.subtitle'));
$query = ['statut' => $filters['tab'], 'rubrique' => $filters['rubrique'], 'periode' => $filters['period'], 'q' => $filters['q']];
?>
<?php $this->section('content'); ?>
<ul class="nav nav-pills df-pills mb-3" role="tablist">
    <?php foreach (['all', 'pending', 'answered', 'needs_info'] as $tab): ?>
    <li class="nav-item"><a class="nav-link<?= $filters['tab'] === $tab ? ' active' : '' ?>" href="<?= e(route('account.questions', ['statut' => $tab] + array_diff_key($query, ['statut' => 1]))) ?>"><?= e(__('questions.tabs.' . $tab)) ?> <span class="badge rounded-pill"><?= (int) ($counts[$tab] ?? 0) ?></span></a></li>
    <?php endforeach ?>
</ul>

<form method="get" action="<?= e(route('account.questions')) ?>" class="df-card df-filters mb-3">
    <input type="hidden" name="statut" value="<?= e($filters['tab']) ?>">
    <div class="row g-2 align-items-end">
        <div class="col-12 col-md-4">
            <label class="form-label" for="q"><?= e(__('questions.list.search')) ?></label>
            <input type="search" class="form-control" id="q" name="q" value="<?= e($filters['q']) ?>" dir="auto">
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label" for="rubrique"><?= e(__('questions.list.rubrique')) ?></label>
            <select class="form-select" id="rubrique" name="rubrique">
                <option value=""><?= e(__('questions.list.all_rubriques')) ?></option>
                <?php foreach ($rubriques as $rubrique): ?>
                <option value="<?= (int) $rubrique['id'] ?>" <?= (string) $filters['rubrique'] === (string) $rubrique['id'] ? 'selected' : '' ?>><?= e($rubrique['name']) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label" for="periode"><?= e(__('questions.list.period')) ?></label>
            <select class="form-select" id="periode" name="periode">
                <?php foreach (['', '7', '30', '365'] as $value): ?>
                <option value="<?= e($value) ?>" <?= $filters['period'] === $value ? 'selected' : '' ?>><?= e(__('questions.list.periods.' . $value)) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <div class="col-12 col-md-2 d-grid">
            <button type="submit" class="btn btn-outline-primary"><i class="bi bi-funnel" aria-hidden="true"></i> <?= e(__('questions.list.filter')) ?></button>
        </div>
    </div>
</form>

<?php if ($result['data'] === []): ?>
<div class="df-empty">
    <i class="bi bi-chat-left-dots" aria-hidden="true"></i>
    <p><?= e(($counts['all'] ?? 0) === 0 ? __('questions.list.empty') : __('questions.list.empty_filtered')) ?></p>
    <?php if (can_ask()): ?><a class="btn btn-gold" href="<?= e(route('questions.create')) ?>"><i class="bi bi-mic-fill" aria-hidden="true"></i> <?= e(__('questions.list.ask_first')) ?></a><?php endif ?>
</div>
<?php else: ?>
<p class="text-secondary small"><?= e(trans_choice('questions.list.count', (int) $result['total'])) ?></p>
<ul class="df-question-list">
    <?php foreach ($result['data'] as $q): ?>
    <li class="df-question-item">
        <a class="df-question-item__link" href="<?= e(route('account.questions.show', ['reference' => $q['reference']])) ?>">
            <div class="df-question-item__head">
                <span class="df-question-item__ref" dir="ltr"><?= e($q['reference']) ?></span>
                <?= $this->partial('partials/status-badge', ['status' => $q['status']]) ?>
            </div>
            <p class="df-question-item__title" dir="auto"><i class="bi bi-<?= $q['input_type'] === 'text' ? 'pencil' : 'mic-fill' ?>" aria-hidden="true"></i> <?= e($q['title'] ?: __('questions.list.untitled')) ?></p>
            <p class="df-question-item__meta">
                <span><img src="<?= e(asset((string) $q['rubrique_icon'])) ?>" alt="" width="18" height="18"> <?= e($q['rubrique_name']) ?></span>
                <span><?= e(__('questions.list.asked_on', ['date' => format_date((string) $q['created_at'])])) ?></span>
                <?php if (!empty($q['answered_at'])): ?><span><i class="bi bi-patch-check" aria-hidden="true"></i> <?= e($q['scholar_name'] ? __('questions.list.answered_by', ['name' => trim(($q['scholar_title'] ? $q['scholar_title'] . ' ' : '') . $q['scholar_name']), 'date' => format_date((string) $q['answered_at'])]) : __('questions.list.answered_on', ['date' => format_date((string) $q['answered_at'])])) ?></span>
                <?php elseif (!empty($q['scholar_name'])): ?><span><i class="bi bi-person-badge" aria-hidden="true"></i> <?= e(__('questions.detail.handled_by', ['name' => $q['scholar_name']])) ?></span><?php endif ?>
            </p>
        </a>
    </li>
    <?php endforeach ?>
</ul>
<?= $this->partial('partials/pagination', ['result' => $result, 'query' => $query]) ?>
<?php endif ?>
<?php $this->endSection(); ?>
