<?php
/**
 * Q/R publiques (§4.8).
 *
 * @var \App\Core\View $this
 * @var array<string, string> $filters
 * @var array{data: list<array<string, mixed>>, total: int, page: int, last_page: int} $result
 * @var list<array<string, mixed>> $rubriques
 */
$this->extend('layouts/admin');
$this->section('title', __('admin.qa.title'));
$this->section('subtitle', __('admin.qa.intro'));
$active = array_filter($filters, static fn (string $v): bool => $v !== '');
?>
<?php $this->section('content'); ?>
<form method="get" action="<?= e(route('admin.qa')) ?>" class="df-card df-filters">
    <div class="row g-2 align-items-end">
        <div class="col-12 col-md-5"><label class="form-label" for="q"><?= e(__('admin.common.search')) ?></label><input type="search" class="form-control" id="q" name="q" value="<?= e($filters['q']) ?>"></div>
        <div class="col-6 col-md-3"><label class="form-label" for="status"><?= e(__('admin.qa.col.status')) ?></label><select class="form-select" id="status" name="status"><option value=""><?= e(__('admin.common.all')) ?></option><?php foreach (['draft', 'published', 'archived'] as $s): ?><option value="<?= e($s) ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= e(__('admin.qa.status.' . $s)) ?></option><?php endforeach ?></select></div>
        <div class="col-6 col-md-3"><label class="form-label" for="rubrique"><?= e(__('admin.questions.col.rubrique')) ?></label><select class="form-select" id="rubrique" name="rubrique"><option value=""><?= e(__('admin.common.all')) ?></option><?php foreach ($rubriques as $r): ?><option value="<?= (int) $r['id'] ?>" <?= $filters['rubrique'] === (string) $r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option><?php endforeach ?></select></div>
        <div class="col-12 col-md-1 d-grid"><button type="submit" class="btn btn-primary"><i class="bi bi-funnel" aria-hidden="true"></i></button></div>
    </div>
</form>
<?php if ($result['data'] === []): ?><?= $this->partial('admin/partials/empty', ['text' => __('admin.qa.empty')]) ?><?php else: ?>
<div class="df-table-wrap">
    <table class="table df-table">
        <thead><tr><th><?= e(__('admin.qa.col.title')) ?></th><th><?= e(__('admin.questions.col.rubrique')) ?></th><th><?= e(__('admin.questions.col.scholar')) ?></th><th><?= e(__('admin.qa.col.locales')) ?></th><th><?= e(__('admin.qa.col.status')) ?></th><th><?= e(__('admin.qa.col.views')) ?></th><th><?= e(__('admin.qa.col.published_at')) ?></th><th class="actions"></th></tr></thead>
        <tbody>
            <?php foreach ($result['data'] as $p): ?>
            <tr>
                <td dir="auto"><a href="<?= e(route('admin.qa.edit', ['id' => $p['id']])) ?>"><?= e($p['title'] ?? '—') ?></a><?php if ($p['reference']): ?><br><small class="text-secondary ltr"><?= e($p['reference']) ?></small><?php endif ?></td>
                <td><?= e($p['rubrique_name']) ?></td>
                <td dir="auto"><?= e($p['scholar_name'] ?? '—') ?></td>
                <td class="ltr"><?= e(strtoupper((string) ($p['locales'] ?? ''))) ?></td>
                <td><span class="df-badge df-badge--<?= $p['status'] === 'published' ? 'success' : ($p['status'] === 'draft' ? 'warning' : 'secondary') ?>"><?= e(__('admin.qa.status.' . $p['status'])) ?></span></td>
                <td><?= (int) $p['views'] ?></td>
                <td class="df-muted-cell"><?= e(format_date($p['published_at'])) ?></td>
                <td class="actions"><a class="btn btn-sm btn-outline-primary" href="<?= e(route('admin.qa.edit', ['id' => $p['id']])) ?>"><i class="bi bi-pencil" aria-hidden="true"></i></a></td>
            </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</div>
<div class="mt-3"><?= $this->partial('partials/pagination', ['result' => $result, 'query' => $active]) ?></div>
<?php endif ?>
<?php $this->endSection(); ?>
