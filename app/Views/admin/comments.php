<?php
/**
 * Modération des commentaires.
 *
 * @var \App\Core\View $this
 * @var array{status: string, type: string} $filters
 * @var array{data: list<array<string, mixed>>, total: int, page: int, last_page: int} $result
 * @var array<string, int> $counts
 */
$this->extend('layouts/admin');
$this->section('title', __('admin.comments.title'));
$this->section('subtitle', __('admin.comments.intro'));
?>
<?php $this->section('content'); ?>
<ul class="nav nav-pills df-pills mb-3">
    <li class="nav-item"><a class="nav-link<?= $filters['status'] === 'all' ? ' active' : '' ?>" href="<?= e(route('admin.comments', ['status' => 'all'])) ?>"><?= e(__('admin.common.all')) ?></a></li>
    <?php foreach (\App\Services\CommentService::STATUSES as $s): ?><li class="nav-item"><a class="nav-link<?= $filters['status'] === $s ? ' active' : '' ?>" href="<?= e(route('admin.comments', ['status' => $s])) ?>"><?= e(__('admin.comments.status.' . $s)) ?> <span class="badge text-bg-light"><?= (int) ($counts[$s] ?? 0) ?></span></a></li><?php endforeach ?>
</ul>
<?php if ($result['data'] === []): ?><?= $this->partial('admin/partials/empty') ?><?php else: ?>
<div class="df-table-wrap">
    <table class="table df-table">
        <thead><tr><th><?= e(__('admin.comments.col.author')) ?></th><th><?= e(__('admin.comments.col.body')) ?></th><th><?= e(__('admin.comments.col.target')) ?></th><th><?= e(__('admin.qa.col.status')) ?></th><th class="actions"></th></tr></thead>
        <tbody>
            <?php foreach ($result['data'] as $c): ?>
            <tr>
                <td dir="auto"><?php if (can('users.manage')): ?><a href="<?= e(route('admin.users.show', ['id' => $c['user_id']])) ?>"><?= e($c['full_name']) ?></a><?php else: ?><?= e($c['full_name']) ?><?php endif ?><br><small class="text-secondary"><?= e(format_date((string) $c['created_at'], true)) ?></small></td>
                <td dir="auto" class="text-wrap"><?= nl2br(e($c['body'])) ?></td>
                <td class="small"><?= e(__('admin.comments.type.' . $c['commentable_type'])) ?><br><?= e($c['commentable_type'] === 'article' ? $c['article_title'] : $c['qa_title']) ?></td>
                <td><span class="df-badge df-badge--<?= ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger', 'spam' => 'secondary'][$c['status']] ?>"><?= e(__('admin.comments.status.' . $c['status'])) ?></span></td>
                <td class="actions">
                    <?php if ($c['status'] !== 'approved'): ?><?= $this->partial('admin/partials/post-button', ['url' => route('admin.comments.moderate', ['id' => $c['id']]), 'label' => __('admin.comments.approve'), 'icon' => 'check2', 'class' => 'btn btn-sm btn-success', 'hidden' => ['status' => 'approved']]) ?><?php endif ?>
                    <?php if ($c['status'] !== 'rejected'): ?><?= $this->partial('admin/partials/post-button', ['url' => route('admin.comments.moderate', ['id' => $c['id']]), 'label' => __('admin.comments.reject'), 'icon' => 'x-lg', 'class' => 'btn btn-sm btn-outline-danger', 'hidden' => ['status' => 'rejected']]) ?><?php endif ?>
                    <?php if ($c['status'] !== 'spam'): ?><?= $this->partial('admin/partials/post-button', ['url' => route('admin.comments.moderate', ['id' => $c['id']]), 'label' => __('admin.comments.spam'), 'icon' => 'shield-x', 'class' => 'btn btn-sm btn-outline-secondary', 'hidden' => ['status' => 'spam']]) ?><?php endif ?>
                    <?= $this->partial('admin/partials/post-button', ['url' => route('admin.comments.destroy', ['id' => $c['id']]), 'label' => __('admin.common.delete'), 'icon' => 'trash', 'class' => 'btn btn-sm btn-outline-danger', 'confirm' => __('admin.common.delete_confirm')]) ?>
                </td>
            </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</div>
<div class="mt-3"><?= $this->partial('partials/pagination', ['result' => $result, 'query' => array_filter($filters)]) ?></div>
<?php endif ?>
<?php $this->endSection(); ?>
