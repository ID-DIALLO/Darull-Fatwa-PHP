<?php
/**
 * Boîte à idées (administration).
 *
 * @var \App\Core\View $this
 * @var array{status: string, category: string} $filters
 * @var array{data: list<array<string, mixed>>, total: int, page: int, last_page: int} $result
 * @var array<string, int|string> $counts
 */
$this->extend('layouts/admin');
$this->section('title', __('admin.ideas.title'));
$this->section('subtitle', __('admin.ideas.intro'));
?>
<?php $this->section('content'); ?>
<ul class="nav nav-pills df-pills mb-3">
    <li class="nav-item"><a class="nav-link<?= $filters['status'] === '' ? ' active' : '' ?>" href="<?= e(route('admin.ideas')) ?>"><?= e(__('admin.common.all')) ?></a></li>
    <?php foreach (\App\Services\IdeaService::STATUSES as $s): ?><li class="nav-item"><a class="nav-link<?= $filters['status'] === $s ? ' active' : '' ?>" href="<?= e(route('admin.ideas', ['status' => $s])) ?>"><?= e(__('ideas.status.' . $s)) ?> <span class="badge text-bg-light"><?= (int) ($counts[$s] ?? 0) ?></span></a></li><?php endforeach ?>
</ul>
<?php if ($result['data'] === []): ?><?= $this->partial('admin/partials/empty') ?><?php else: ?>
<div class="df-table-wrap">
    <table class="table df-table">
        <thead><tr><th><?= e(__('ideas.form.title')) ?></th><th><?= e(__('ideas.form.category')) ?></th><th><?= e(__('admin.forum.col.author')) ?></th><th><?= e(__('admin.qa.col.status')) ?></th><th><?= e(__('admin.ideas.col.votes')) ?></th><th><?= e(__('admin.ideas.col.comments')) ?></th><th class="actions"></th></tr></thead>
        <tbody>
            <?php foreach ($result['data'] as $i): ?>
            <tr>
                <td dir="auto"><a href="<?= e(route('admin.ideas.show', ['id' => $i['id']])) ?>"><?= e($i['title']) ?></a><br><small class="text-secondary"><?= e(format_date((string) $i['created_at'], true)) ?></small></td>
                <td><?= e(__('ideas.category.' . $i['category'])) ?></td>
                <td dir="auto"><?= e($i['author_name']) ?></td>
                <td><span class="df-badge df-badge--<?= ['pending' => 'warning', 'new' => 'info', 'under_review' => 'info', 'accepted' => 'success', 'done' => 'success', 'declined' => 'secondary'][$i['status']] ?>"><?= e(__('ideas.status.' . $i['status'])) ?></span><?php if ($i['admin_response']): ?> <i class="bi bi-patch-check-fill text-success" title="<?= e(__('ideas.official_response')) ?>"></i><?php endif ?></td>
                <td><?= (int) $i['votes_count'] ?></td>
                <td><?= (int) $i['comments_count'] ?><?php if ((int) $i['pending_comments'] > 0): ?> <span class="df-badge df-badge--warning"><?= (int) $i['pending_comments'] ?></span><?php endif ?></td>
                <td class="actions"><a class="btn btn-sm btn-outline-primary" href="<?= e(route('admin.ideas.show', ['id' => $i['id']])) ?>"><i class="bi bi-pencil" aria-hidden="true"></i></a></td>
            </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</div>
<div class="mt-3"><?= $this->partial('partials/pagination', ['result' => $result, 'query' => array_filter($filters)]) ?></div>
<?php endif ?>
<?php $this->endSection(); ?>
