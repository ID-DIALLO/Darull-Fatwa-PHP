<?php
/**
 * Signalements.
 *
 * @var \App\Core\View $this
 * @var string $status
 * @var array{data: list<array<string, mixed>>, total: int, page: int, last_page: int} $result
 * @var array<string, int|string> $counts
 */
$this->extend('layouts/admin');
$this->section('title', __('admin.reports.title'));
$this->section('subtitle', __('admin.reports.intro'));
$link = static function (array $r): ?string {
    return match ((string) $r['reportable_type']) {
        'forum_topic'  => route('forum.topic', ['id' => $r['reportable_id'], 'slug' => $r['topic_slug'] ?? 'sujet']),
        'forum_post'   => route('forum.topic', ['id' => $r['parent_id'], 'slug' => $r['topic_slug'] ?? 'sujet']) . '#post-' . $r['reportable_id'],
        'idea'         => route('ideas.show', ['id' => $r['reportable_id']]),
        'idea_comment' => route('ideas.show', ['id' => $r['parent_id']]) . '#comment-' . $r['reportable_id'],
        default        => route('admin.comments', ['status' => 'all']),
    };
};
?>
<?php $this->section('content'); ?>
<ul class="nav nav-pills df-pills mb-3">
    <?php foreach (['open', 'resolved', 'dismissed'] as $s): ?><li class="nav-item"><a class="nav-link<?= $status === $s ? ' active' : '' ?>" href="<?= e(route('admin.reports', ['status' => $s])) ?>"><?= e(__('admin.reports.status.' . $s)) ?> <span class="badge text-bg-light"><?= (int) ($counts[$s] ?? 0) ?></span></a></li><?php endforeach ?>
</ul>
<?php if ($result['data'] === []): ?><?= $this->partial('admin/partials/empty') ?><?php else: ?>
<div class="df-table-wrap">
    <table class="table df-table">
        <thead><tr><th><?= e(__('admin.reports.col.date')) ?></th><th><?= e(__('admin.reports.col.target')) ?></th><th><?= e(__('admin.reports.col.reason')) ?></th><th><?= e(__('admin.reports.col.reporter')) ?></th><th class="actions"></th></tr></thead>
        <tbody>
            <?php foreach ($result['data'] as $r): ?>
            <tr>
                <td class="df-muted-cell text-nowrap"><?= e(format_date((string) $r['created_at'], true)) ?></td>
                <td dir="auto"><span class="df-badge df-badge--secondary"><?= e(__('admin.reports.types.' . $r['reportable_type'])) ?></span> <a href="<?= e($link($r)) ?>" target="_blank" rel="noopener"><?= e(excerpt((string) ($r['target_excerpt'] ?? '#' . $r['reportable_id']), 100)) ?></a></td>
                <td><?= e(__('forum.report.reasons.' . $r['reason'])) ?><?php if ($r['details']): ?><br><small class="text-secondary" dir="auto"><?= e($r['details']) ?></small><?php endif ?></td>
                <td dir="auto"><?= e($r['reporter_name']) ?><?php if ($r['handled_by']): ?><br><small class="text-secondary"><?= e(__('admin.reports.handled_by', ['name' => $r['handler_name'] ?? '', 'date' => format_date($r['handled_at'], true)])) ?></small><?php endif ?></td>
                <td class="actions">
                    <?php if ($r['status'] === 'open'): ?>
                    <?= $this->partial('admin/partials/post-button', ['url' => route('admin.reports.handle', ['id' => $r['id']]), 'label' => __('admin.reports.hide_and_resolve'), 'icon' => 'eye-slash', 'class' => 'btn btn-sm btn-danger', 'hidden' => ['action' => 'hide'], 'confirm' => __('admin.reports.hide_confirm')]) ?>
                    <?= $this->partial('admin/partials/post-button', ['url' => route('admin.reports.handle', ['id' => $r['id']]), 'label' => __('admin.reports.resolve'), 'icon' => 'check2', 'class' => 'btn btn-sm btn-outline-success', 'hidden' => ['action' => 'resolve']]) ?>
                    <?= $this->partial('admin/partials/post-button', ['url' => route('admin.reports.handle', ['id' => $r['id']]), 'label' => __('admin.reports.dismiss'), 'icon' => 'x-lg', 'class' => 'btn btn-sm btn-outline-secondary', 'hidden' => ['action' => 'dismiss']]) ?>
                    <?php else: ?><span class="df-badge df-badge--<?= $r['status'] === 'resolved' ? 'success' : 'secondary' ?>"><?= e(__('admin.reports.status.' . $r['status'])) ?></span><?php endif ?>
                </td>
            </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</div>
<div class="mt-3"><?= $this->partial('partials/pagination', ['result' => $result, 'query' => ['status' => $status]]) ?></div>
<?php endif ?>
<?php $this->endSection(); ?>
