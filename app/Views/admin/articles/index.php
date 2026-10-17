<?php
/**
 * Articles (administration).
 *
 * @var \App\Core\View $this
 * @var array<string, string> $filters
 * @var array{data: list<array<string, mixed>>, total: int, page: int, last_page: int} $result
 * @var list<array<string, mixed>> $rubriques
 * @var array<string, int|string> $counts
 */
$this->extend('layouts/admin');
$this->section('title', __('admin.articles.title'));
$this->section('subtitle', trans_choice('admin.articles.count', (int) $result['total']));
$active = array_filter($filters);
$this->section('actions');
?>
<a class="btn btn-outline-secondary" href="<?= e(route('admin.tags')) ?>"><i class="bi bi-tags" aria-hidden="true"></i> <?= e(__('admin.tags.title')) ?></a>
<a class="btn btn-outline-secondary" href="<?= e(route('admin.comments')) ?>"><i class="bi bi-chat-left-text" aria-hidden="true"></i> <?= e(__('admin.comments.title')) ?></a>
<a class="btn btn-primary" href="<?= e(route('admin.articles.create')) ?>"><i class="bi bi-plus-lg" aria-hidden="true"></i> <?= e(__('admin.articles.create')) ?></a>
<?php $this->endSection(); ?>
<?php $this->section('content'); ?>
<ul class="nav nav-pills df-pills mb-3">
    <li class="nav-item"><a class="nav-link<?= $filters['status'] === '' ? ' active' : '' ?>" href="<?= e(route('admin.articles')) ?>"><?= e(__('admin.common.all')) ?></a></li>
    <?php foreach (\App\Services\ArticleService::STATUSES as $s): ?><li class="nav-item"><a class="nav-link<?= $filters['status'] === $s ? ' active' : '' ?>" href="<?= e(route('admin.articles', ['status' => $s])) ?>"><?= e(__('blog.status.' . $s)) ?> <span class="badge text-bg-light"><?= (int) ($counts[$s] ?? 0) ?></span></a></li><?php endforeach ?>
</ul>
<form method="get" action="<?= e(route('admin.articles')) ?>" class="df-card df-filters">
    <?php if ($filters['status'] !== ''): ?><input type="hidden" name="status" value="<?= e($filters['status']) ?>"><?php endif ?>
    <div class="row g-2 align-items-end">
        <div class="col-12 col-md-6"><label class="form-label" for="q"><?= e(__('admin.common.search')) ?></label><input type="search" class="form-control" id="q" name="q" value="<?= e($filters['q']) ?>"></div>
        <div class="col-8 col-md-4"><label class="form-label" for="rubrique"><?= e(__('admin.questions.col.rubrique')) ?></label><select class="form-select" id="rubrique" name="rubrique"><option value=""><?= e(__('admin.common.all')) ?></option><?php foreach ($rubriques as $r): ?><option value="<?= (int) $r['id'] ?>" <?= $filters['rubrique'] === (string) $r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option><?php endforeach ?></select></div>
        <div class="col-4 col-md-2 d-grid"><button type="submit" class="btn btn-primary"><i class="bi bi-funnel" aria-hidden="true"></i></button></div>
    </div>
</form>
<?php if ($result['data'] === []): ?><?= $this->partial('admin/partials/empty') ?><?php else: ?>
<div class="df-table-wrap">
    <table class="table df-table">
        <thead><tr><th><?= e(__('admin.articles.col.title')) ?></th><th><?= e(__('admin.articles.col.author')) ?></th><th><?= e(__('admin.questions.col.rubrique')) ?></th><th><?= e(__('admin.qa.col.locales')) ?></th><th><?= e(__('admin.qa.col.status')) ?></th><th><?= e(__('admin.articles.col.published_at')) ?></th><th><?= e(__('admin.qa.col.views')) ?></th><th class="actions"></th></tr></thead>
        <tbody>
            <?php foreach ($result['data'] as $a): ?>
            <tr>
                <td dir="auto"><?php if ($a['is_featured']): ?><i class="bi bi-star-fill text-warning" title="<?= e(__('blog.index.featured')) ?>"></i> <?php endif ?><a href="<?= e(route('admin.articles.edit', ['id' => $a['id']])) ?>"><?= e($a['title'] ?? '—') ?></a><?php if ((int) $a['pending_comments'] > 0): ?> <span class="df-badge df-badge--warning"><?= (int) $a['pending_comments'] ?> <i class="bi bi-chat-left-dots" aria-hidden="true"></i></span><?php endif ?></td>
                <td dir="auto"><?= e($a['author_name'] ?? '—') ?></td>
                <td><?= e($a['rubrique_name'] ?? '—') ?></td>
                <td class="ltr"><?= e(strtoupper((string) ($a['locales'] ?? '—'))) ?></td>
                <td><span class="df-badge df-badge--<?= ['draft' => 'secondary', 'submitted' => 'warning', 'scheduled' => 'info', 'published' => 'success', 'archived' => 'secondary'][$a['status']] ?>"><?= e(__('blog.status.' . $a['status'])) ?></span></td>
                <td class="df-muted-cell"><?= e(format_date($a['published_at'], true)) ?></td>
                <td><?= (int) $a['views'] ?></td>
                <td class="actions"><a class="btn btn-sm btn-outline-primary" href="<?= e(route('admin.articles.edit', ['id' => $a['id']])) ?>"><i class="bi bi-pencil" aria-hidden="true"></i></a></td>
            </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</div>
<div class="mt-3"><?= $this->partial('partials/pagination', ['result' => $result, 'query' => $active]) ?></div>
<?php endif ?>
<?php $this->endSection(); ?>
