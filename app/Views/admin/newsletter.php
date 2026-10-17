<?php
/**
 * Abonnés newsletter.
 *
 * @var \App\Core\View $this
 * @var string $status
 * @var array{data: list<array<string, mixed>>, total: int, page: int, last_page: int} $result
 * @var array<string, int|string> $counts
 */
$this->extend('layouts/admin');
$this->section('title', __('admin.newsletter.title'));
$this->section('subtitle', trans_choice('admin.newsletter.count', (int) $result['total']));
$this->section('actions');
?>
<div class="btn-group">
    <a class="btn btn-primary" href="<?= e(route('admin.newsletter.export', ['status' => $status ?: 'active', 'format' => 'csv'])) ?>"><i class="bi bi-download" aria-hidden="true"></i> CSV</a>
    <a class="btn btn-outline-primary" href="<?= e(route('admin.newsletter.export', ['status' => $status ?: 'active', 'format' => 'xlsx'])) ?>">Excel</a>
</div>
<?php $this->endSection(); ?>
<?php $this->section('content'); ?>
<ul class="nav nav-pills df-pills mb-3">
    <li class="nav-item"><a class="nav-link<?= $status === '' ? ' active' : '' ?>" href="<?= e(route('admin.newsletter')) ?>"><?= e(__('admin.common.all')) ?></a></li>
    <?php foreach (['active', 'pending', 'unsubscribed'] as $s): ?>
    <li class="nav-item"><a class="nav-link<?= $status === $s ? ' active' : '' ?>" href="<?= e(route('admin.newsletter', ['status' => $s])) ?>"><?= e(__('admin.newsletter.status_' . $s)) ?> <span class="badge text-bg-light"><?= (int) ($counts[$s] ?? 0) ?></span></a></li>
    <?php endforeach ?>
</ul>
<?php if ($result['data'] === []): ?><?= $this->partial('admin/partials/empty') ?><?php else: ?>
<div class="df-table-wrap">
    <table class="table df-table">
        <thead><tr><th><?= e(__('admin.newsletter.email')) ?></th><th><?= e(__('admin.newsletter.locale')) ?></th><th><?= e(__('admin.newsletter.status')) ?></th><th><?= e(__('admin.newsletter.confirmed_at')) ?></th><th><?= e(__('admin.newsletter.created_at')) ?></th><th class="actions"></th></tr></thead>
        <tbody>
            <?php foreach ($result['data'] as $s): ?>
            <tr>
                <td class="ltr"><?= e($s['email']) ?></td>
                <td><?= e(strtoupper((string) $s['locale'])) ?></td>
                <td><span class="df-badge df-badge--<?= ['active' => 'success', 'pending' => 'warning', 'unsubscribed' => 'secondary'][$s['status']] ?>"><?= e(__('admin.newsletter.status_' . $s['status'])) ?></span></td>
                <td class="df-muted-cell"><?= e(format_date($s['confirmed_at'], true) ?: '—') ?></td>
                <td class="df-muted-cell"><?= e(format_date((string) $s['created_at'], true)) ?></td>
                <td class="actions"><?= $this->partial('admin/partials/post-button', ['url' => route('admin.newsletter.destroy', ['id' => $s['id']]), 'label' => __('admin.common.delete'), 'icon' => 'trash', 'class' => 'btn btn-sm btn-outline-danger', 'confirm' => __('admin.common.delete_confirm')]) ?></td>
            </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</div>
<div class="mt-3"><?= $this->partial('partials/pagination', ['result' => $result, 'query' => array_filter(['status' => $status])]) ?></div>
<?php endif ?>
<?php $this->endSection(); ?>
