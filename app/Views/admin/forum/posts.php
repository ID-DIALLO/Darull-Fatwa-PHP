<?php
/**
 * Modération du forum : réponses (en attente, visibles, masquées).
 *
 * @var \App\Core\View $this
 * @var string $status
 * @var array{data: list<array<string, mixed>>, total: int, page: int, last_page: int} $result
 * @var array<string, int|string> $counts
 */
$this->extend('layouts/admin');
$this->section('title', __('admin.forum.posts'));
$this->section('subtitle', __('admin.forum.posts_intro'));
?>
<?php $this->section('content'); ?>
<ul class="nav nav-pills df-pills mb-3">
    <?php foreach (['pending', 'visible', 'hidden'] as $s): ?><li class="nav-item"><a class="nav-link<?= $status === $s ? ' active' : '' ?>" href="<?= e(route('admin.forum.posts', ['status' => $s])) ?>"><?= e(__('admin.forum.post_status.' . $s)) ?> <span class="badge text-bg-light"><?= (int) ($counts[$s] ?? 0) ?></span></a></li><?php endforeach ?>
</ul>
<?php if ($result['data'] === []): ?><?= $this->partial('admin/partials/empty') ?><?php else: ?>
<div class="df-table-wrap">
    <table class="table df-table">
        <thead><tr><th><?= e(__('admin.forum.col.author')) ?></th><th><?= e(__('admin.forum.col.message')) ?></th><th><?= e(__('admin.forum.col.topic')) ?></th><th class="actions"></th></tr></thead>
        <tbody>
            <?php foreach ($result['data'] as $p): ?>
            <tr>
                <td dir="auto"><?= e($p['author_name']) ?><br><small class="text-secondary"><?= e(format_date((string) $p['created_at'], true)) ?></small></td>
                <td dir="auto" class="text-wrap"><?= $p['body'] /* HTML échappé à l'enregistrement */ ?><?php if ($p['is_validated']): ?><br><span class="df-badge df-badge--success"><?= e(__('forum.validated_short')) ?></span><?php endif ?></td>
                <td dir="auto"><a href="<?= e(route('forum.topic', ['id' => $p['topic_id'], 'slug' => $p['topic_slug']])) ?>#post-<?= (int) $p['id'] ?>" target="_blank" rel="noopener"><?= e($p['topic_title']) ?></a></td>
                <td class="actions">
                    <?php if ($p['status'] !== 'visible'): ?><?= $this->partial('admin/partials/post-button', ['url' => route('admin.forum.post', ['id' => $p['id']]), 'label' => __('forum.moderation.approve'), 'icon' => 'check2', 'class' => 'btn btn-sm btn-success', 'hidden' => ['action' => 'approve']]) ?><?php endif ?>
                    <?php if ($p['status'] !== 'hidden'): ?><?= $this->partial('admin/partials/post-button', ['url' => route('admin.forum.post', ['id' => $p['id']]), 'label' => __('forum.moderation.hide'), 'icon' => 'eye-slash', 'class' => 'btn btn-sm btn-outline-secondary', 'hidden' => ['action' => 'hide']]) ?><?php endif ?>
                    <?= $this->partial('admin/partials/post-button', ['url' => route('admin.forum.post', ['id' => $p['id']]), 'label' => __('admin.common.delete'), 'icon' => 'trash', 'class' => 'btn btn-sm btn-outline-danger', 'confirm' => __('admin.common.delete_confirm'), 'hidden' => ['action' => 'delete']]) ?>
                </td>
            </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</div>
<div class="mt-3"><?= $this->partial('partials/pagination', ['result' => $result, 'query' => ['status' => $status]]) ?></div>
<?php endif ?>
<?php $this->endSection(); ?>
