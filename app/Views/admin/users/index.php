<?php
/**
 * Utilisateurs (§4.6.3).
 *
 * @var \App\Core\View $this
 * @var array<string, string> $filters
 * @var array{data: list<array<string, mixed>>, total: int, page: int, last_page: int} $result
 */
$this->extend('layouts/admin');
$this->section('title', __('admin.users.title'));
$this->section('subtitle', trans_choice('admin.users.count', (int) $result['total']));
$active = array_filter($filters, static fn (string $v): bool => $v !== '');
$this->section('actions');
?>
<a class="btn btn-primary" href="<?= e(route('admin.users.create')) ?>"><i class="bi bi-person-plus" aria-hidden="true"></i> <?= e(__('admin.users.create')) ?></a>
<?php $this->endSection(); ?>
<?php $this->section('content'); ?>
<form method="get" action="<?= e(route('admin.users')) ?>" class="df-card df-filters">
    <div class="row g-2 align-items-end">
        <div class="col-12 col-md-4"><label class="form-label" for="q"><?= e(__('admin.common.search')) ?></label><input type="search" class="form-control" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(__('admin.users.search_placeholder')) ?>"></div>
        <div class="col-6 col-md-2"><label class="form-label" for="role"><?= e(__('admin.users.col.role')) ?></label><select class="form-select" id="role" name="role"><option value=""><?= e(__('admin.common.all')) ?></option><?php foreach (\App\Models\User::ROLES as $slug): ?><option value="<?= e($slug) ?>" <?= $filters['role'] === $slug ? 'selected' : '' ?>><?= e(__('account.roles.' . $slug)) ?></option><?php endforeach ?></select></div>
        <div class="col-6 col-md-2"><label class="form-label" for="status"><?= e(__('admin.users.col.status')) ?></label><select class="form-select" id="status" name="status"><option value=""><?= e(__('admin.common.all')) ?></option><?php foreach (['active', 'pending', 'suspended', 'banned'] as $s): ?><option value="<?= e($s) ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= e(__('admin.users.status.' . $s)) ?></option><?php endforeach ?></select></div>
        <div class="col-6 col-md-2"><label class="form-label" for="country"><?= e(__('admin.users.col.country')) ?></label><input type="text" class="form-control" id="country" name="country" value="<?= e($filters['country']) ?>" maxlength="2" placeholder="ML" dir="ltr"></div>
        <div class="col-6 col-md-2 d-flex gap-2"><button type="submit" class="btn btn-primary flex-grow-1"><?= e(__('admin.common.filter')) ?></button><?php if ($active !== []): ?><a class="btn btn-outline-secondary" href="<?= e(route('admin.users')) ?>" aria-label="<?= e(__('admin.common.reset')) ?>"><i class="bi bi-x-lg" aria-hidden="true"></i></a><?php endif ?></div>
    </div>
</form>
<?php if ($result['data'] === []): ?><?= $this->partial('admin/partials/empty') ?><?php else: ?>
<div class="df-table-wrap">
    <table class="table df-table">
        <thead><tr><th><?= e(__('admin.users.col.name')) ?></th><th><?= e(__('admin.users.col.contact')) ?></th><th><?= e(__('admin.users.col.role')) ?></th><th><?= e(__('admin.users.col.country')) ?></th><th><?= e(__('admin.users.col.status')) ?></th><th><?= e(__('admin.users.col.questions')) ?></th><th><?= e(__('admin.users.col.registered')) ?></th><th><?= e(__('admin.users.col.last_login')) ?></th></tr></thead>
        <tbody>
            <?php foreach ($result['data'] as $u): ?>
            <tr>
                <td dir="auto"><a href="<?= e(route('admin.users.show', ['id' => $u['id']])) ?>"><?= e($u['full_name']) ?></a><?php if ($u['display_name']): ?><br><small class="text-secondary"><?= e($u['display_name']) ?></small><?php endif ?></td>
                <td class="ltr small"><?= e($u['email'] ?? '') ?><?php if ($u['email'] && !$u['email_verified_at']): ?> <i class="bi bi-envelope-exclamation text-warning" title="<?= e(__('admin.users.unverified')) ?>"></i><?php endif ?><?php if ($u['phone']): ?><br><?= e($u['phone']) ?><?php endif ?></td>
                <td><?= e(__('account.roles.' . $u['role'])) ?></td>
                <td><?= e($u['country'] ?? '—') ?></td>
                <td><span class="df-badge df-badge--<?= $u['status'] === 'active' ? 'success' : ($u['status'] === 'pending' ? 'warning' : 'danger') ?>"><?= e(__('admin.users.status.' . $u['status'])) ?></span></td>
                <td><?= (int) $u['questions_count'] ?></td>
                <td class="df-muted-cell"><?= e(format_date((string) $u['created_at'])) ?></td>
                <td class="df-muted-cell"><?= $u['last_login_at'] ? e(time_ago((string) $u['last_login_at'])) : '—' ?></td>
            </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</div>
<div class="mt-3"><?= $this->partial('partials/pagination', ['result' => $result, 'query' => $active]) ?></div>
<?php endif ?>
<?php $this->endSection(); ?>
