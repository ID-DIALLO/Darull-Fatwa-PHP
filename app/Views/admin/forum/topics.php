<?php
/**
 * Modération du forum : sujets.
 *
 * @var \App\Core\View $this
 * @var array{status: string, q: string} $filters
 * @var array{data: list<array<string, mixed>>, total: int, page: int, last_page: int} $result
 * @var array<string, int|string> $counts
 * @var int $pendingPosts
 * @var list<array<string, mixed>> $categories
 */
$this->extend('layouts/admin');
$this->section('title', __('admin.forum.title'));
$this->section('subtitle', trans_choice('admin.forum.topics_count', (int) $result['total']));
$this->section('actions');
?>
<a class="btn btn-outline-secondary" href="<?= e(route('admin.forum.categories')) ?>"><i class="bi bi-folder2" aria-hidden="true"></i> <?= e(__('admin.forum.categories')) ?></a>
<a class="btn btn-outline-secondary" href="<?= e(route('admin.reports')) ?>"><i class="bi bi-flag" aria-hidden="true"></i> <?= e(__('admin.reports.title')) ?></a>
<a class="btn <?= $pendingPosts > 0 ? 'btn-warning' : 'btn-outline-secondary' ?>" href="<?= e(route('admin.forum.posts')) ?>"><i class="bi bi-chat-left-dots" aria-hidden="true"></i> <?= e(__('admin.forum.posts')) ?> <?php if ($pendingPosts > 0): ?><span class="badge text-bg-light"><?= $pendingPosts ?></span><?php endif ?></a>
<?php $this->endSection(); ?>
<?php $this->section('content'); ?>
<ul class="nav nav-pills df-pills mb-3">
    <li class="nav-item"><a class="nav-link<?= $filters['status'] === '' ? ' active' : '' ?>" href="<?= e(route('admin.forum')) ?>"><?= e(__('admin.common.all')) ?></a></li>
    <?php foreach (['pending', 'open', 'locked', 'hidden'] as $s): ?><li class="nav-item"><a class="nav-link<?= $filters['status'] === $s ? ' active' : '' ?>" href="<?= e(route('admin.forum', ['status' => $s])) ?>"><?= e(__('forum.status.' . $s)) ?> <span class="badge text-bg-light"><?= (int) ($counts[$s] ?? 0) ?></span></a></li><?php endforeach ?>
</ul>
<form method="get" action="<?= e(route('admin.forum')) ?>" class="df-card df-filters"><?php if ($filters['status'] !== ''): ?><input type="hidden" name="status" value="<?= e($filters['status']) ?>"><?php endif ?><div class="input-group"><input type="search" class="form-control" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(__('admin.common.search')) ?>" aria-label="<?= e(__('admin.common.search')) ?>"><button type="submit" class="btn btn-primary"><i class="bi bi-search" aria-hidden="true"></i></button></div></form>
<?php if ($result['data'] === []): ?><?= $this->partial('admin/partials/empty') ?><?php else: ?>
<div class="df-table-wrap">
    <table class="table df-table">
        <thead><tr><th><?= e(__('admin.forum.col.topic')) ?></th><th><?= e(__('admin.forum.col.author')) ?></th><th><?= e(__('admin.forum.col.category')) ?></th><th><?= e(__('admin.qa.col.status')) ?></th><th><?= e(__('admin.forum.col.replies')) ?></th><th class="actions"></th></tr></thead>
        <tbody>
            <?php foreach ($result['data'] as $t): ?>
            <tr>
                <td dir="auto"><?php if ($t['is_pinned']): ?><i class="bi bi-pin-angle-fill text-warning" aria-hidden="true"></i> <?php endif ?><a href="<?= e(route('forum.topic', ['id' => $t['id'], 'slug' => $t['slug']])) ?>" target="_blank" rel="noopener"><?= e($t['title']) ?></a><?php if ((int) $t['open_reports'] > 0): ?> <span class="df-badge df-badge--danger"><i class="bi bi-flag-fill" aria-hidden="true"></i> <?= (int) $t['open_reports'] ?></span><?php endif ?><br><small class="text-secondary"><?= e(excerpt((string) $t['body'], 100)) ?></small></td>
                <td dir="auto"><?php if (can('users.manage')): ?><a href="<?= e(route('admin.users.show', ['id' => $t['user_id']])) ?>"><?= e($t['author_name']) ?></a><?php else: ?><?= e($t['author_name']) ?><?php endif ?><br><small class="text-secondary"><?= e(format_date((string) $t['created_at'], true)) ?></small></td>
                <td><?= e($t['category_name'] ?? '—') ?></td>
                <td><span class="df-badge df-badge--<?= ['pending' => 'warning', 'open' => 'success', 'locked' => 'secondary', 'hidden' => 'danger'][$t['status']] ?>"><?= e(__('forum.status.' . $t['status'])) ?></span></td>
                <td><?= (int) $t['replies_count'] ?></td>
                <td class="actions">
                    <?php foreach (array_filter(['approve' => in_array($t['status'], ['pending', 'hidden'], true), 'hide' => $t['status'] !== 'hidden', 'pin' => !$t['is_pinned'], 'unpin' => (bool) $t['is_pinned'], 'lock' => $t['status'] === 'open', 'unlock' => $t['status'] === 'locked']) as $action => $_): ?>
                    <?= $this->partial('admin/partials/post-button', ['url' => route('admin.forum.topic', ['id' => $t['id']]), 'label' => __('forum.moderation.' . $action), 'class' => 'btn btn-sm ' . ($action === 'approve' ? 'btn-success' : 'btn-outline-secondary'), 'hidden' => ['action' => $action]]) ?>
                    <?php endforeach ?>
                    <?= $this->partial('admin/partials/post-button', ['url' => route('admin.forum.topic', ['id' => $t['id']]), 'label' => __('admin.common.delete'), 'icon' => 'trash', 'class' => 'btn btn-sm btn-outline-danger', 'confirm' => __('admin.common.delete_confirm'), 'hidden' => ['action' => 'delete']]) ?>
                </td>
            </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</div>
<div class="mt-3"><?= $this->partial('partials/pagination', ['result' => $result, 'query' => array_filter($filters)]) ?></div>
<?php endif ?>
<?php $this->endSection(); ?>
