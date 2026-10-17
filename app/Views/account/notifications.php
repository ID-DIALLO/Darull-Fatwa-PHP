<?php
/**
 * Notifications du membre (§4.4).
 *
 * @var \App\Core\View $this
 * @var list<array<string, mixed>> $items
 * @var int $page
 * @var int $unread
 */
$this->extend('layouts/account');
$this->section('title', __('questions.notifications.title'));
$this->section('subtitle', __('questions.notifications.subtitle'));
?>
<?php $this->section('content'); ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <span class="text-secondary"><?= e(trans_choice('questions.notifications.unread', $unread)) ?></span>
    <?php if ($unread > 0): ?>
    <form method="post" action="<?= e(route('account.notifications.read_all')) ?>">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-check2-all" aria-hidden="true"></i> <?= e(__('questions.notifications.mark_all')) ?></button>
    </form>
    <?php endif ?>
</div>
<?php if ($items === []): ?>
<div class="df-empty"><i class="bi bi-bell-slash" aria-hidden="true"></i><p class="mb-0"><?= e(__('questions.notifications.empty')) ?></p></div>
<?php else: ?>
<ul class="df-notif-list">
    <?php foreach ($items as $item): ?>
    <li class="df-notif<?= $item['read_at'] === null ? ' is-unread' : '' ?>">
        <a href="<?= e(route('account.notifications.open', ['id' => $item['id']])) ?>">
            <i class="bi bi-<?= e($item['rendered']['icon']) ?> df-notif__icon" aria-hidden="true"></i>
            <span class="df-notif__body">
                <strong dir="auto"><?= e($item['rendered']['title']) ?></strong>
                <?php if ($item['rendered']['message'] !== ''): ?><span dir="auto"><?= e($item['rendered']['message']) ?></span><?php endif ?>
                <small class="text-secondary"><?= e(time_ago((string) $item['created_at'])) ?></small>
            </span>
        </a>
    </li>
    <?php endforeach ?>
</ul>
<nav class="d-flex justify-content-between mt-3" aria-label="Pagination">
    <?php if ($page > 1): ?><a class="btn btn-outline-primary btn-sm" href="<?= e(route('account.notifications', ['page' => $page - 1])) ?>"><?= e(__('questions.create.previous')) ?></a><?php else: ?><span></span><?php endif ?>
    <?php if (count($items) === 20): ?><a class="btn btn-outline-primary btn-sm" href="<?= e(route('account.notifications', ['page' => $page + 1])) ?>"><?= e(__('questions.create.next')) ?></a><?php endif ?>
</nav>
<?php endif ?>
<?php $this->endSection(); ?>
