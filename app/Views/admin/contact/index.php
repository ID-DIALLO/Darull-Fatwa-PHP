<?php
/**
 * Messages de contact (§4.11).
 *
 * @var \App\Core\View $this
 * @var array{status: string, q: string} $filters
 * @var array{data: list<array<string, mixed>>, total: int, page: int, last_page: int} $result
 * @var array<string, int|string> $counts
 */
$this->extend('layouts/admin');
$this->section('title', __('admin.contact.title'));
$this->section('subtitle', trans_choice('admin.contact.count', (int) $result['total']));
?>
<?php $this->section('content'); ?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <ul class="nav nav-pills df-pills">
        <li class="nav-item"><a class="nav-link<?= $filters['status'] === '' ? ' active' : '' ?>" href="<?= e(route('admin.contact')) ?>"><?= e(__('admin.common.all')) ?></a></li>
        <?php foreach (['new', 'read', 'replied', 'archived'] as $s): ?>
        <li class="nav-item"><a class="nav-link<?= $filters['status'] === $s ? ' active' : '' ?>" href="<?= e(route('admin.contact', ['status' => $s])) ?>"><?= e(__('admin.contact.status.' . $s)) ?> <span class="badge text-bg-light"><?= (int) ($counts[$s] ?? 0) ?></span></a></li>
        <?php endforeach ?>
    </ul>
    <form method="get" action="<?= e(route('admin.contact')) ?>" class="ms-auto d-flex gap-2">
        <?php if ($filters['status'] !== ''): ?><input type="hidden" name="status" value="<?= e($filters['status']) ?>"><?php endif ?>
        <input type="search" class="form-control form-control-sm" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(__('admin.common.search')) ?>" aria-label="<?= e(__('admin.common.search')) ?>">
        <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-search" aria-hidden="true"></i></button>
    </form>
</div>
<?php if ($result['data'] === []): ?><?= $this->partial('admin/partials/empty') ?><?php else: ?>
<div class="df-table-wrap">
    <table class="table df-table">
        <thead><tr><th><?= e(__('admin.contact.col.date')) ?></th><th><?= e(__('admin.contact.col.from')) ?></th><th><?= e(__('admin.contact.col.subject')) ?></th><th><?= e(__('admin.contact.col.message')) ?></th><th><?= e(__('admin.users.col.status')) ?></th></tr></thead>
        <tbody>
            <?php foreach ($result['data'] as $m): ?>
            <tr class="<?= $m['status'] === 'new' ? 'fw-semibold' : '' ?>">
                <td class="df-muted-cell"><?= e(format_date((string) $m['created_at'], true)) ?></td>
                <td dir="auto"><a href="<?= e(route('admin.contact.show', ['id' => $m['id']])) ?>"><?= e($m['name']) ?></a><br><small class="text-secondary ltr fw-normal"><?= e($m['email']) ?></small></td>
                <td><?= e(__('front.contact.subjects.' . $m['subject'])) ?></td>
                <td class="fw-normal" dir="auto"><?= e(mb_strimwidth((string) $m['message'], 0, 90, '…')) ?></td>
                <td><span class="df-badge df-badge--<?= ['new' => 'warning', 'read' => 'info', 'replied' => 'success', 'archived' => 'secondary'][$m['status']] ?>"><?= e(__('admin.contact.status.' . $m['status'])) ?></span></td>
            </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</div>
<div class="mt-3"><?= $this->partial('partials/pagination', ['result' => $result, 'query' => array_filter($filters)]) ?></div>
<?php endif ?>
<?php $this->endSection(); ?>
