<?php
/**
 * Pagination accessible. $result : ['page', 'last_page', 'total'] ; $query : paramètres à conserver.
 *
 * @var array{page: int, last_page: int, total: int} $result
 * @var array<string, scalar> $query
 */
$query ??= [];
if (($result['last_page'] ?? 1) <= 1) {
    return;
}
$page = (int) $result['page'];
$last = (int) $result['last_page'];
$link = static fn (int $p): string => '?' . http_build_query(array_filter($query, static fn ($v) => $v !== '' && $v !== null) + ['page' => $p]);
$window = range(max(1, $page - 2), min($last, $page + 2));
?>
<nav class="df-pagination" aria-label="Pagination">
    <ul class="pagination justify-content-center mb-0">
        <li class="page-item<?= $page <= 1 ? ' disabled' : '' ?>"><a class="page-link" href="<?= e($link(max(1, $page - 1))) ?>" aria-label="<?= e(__('questions.create.previous')) ?>"><i class="bi bi-chevron-left icon-dir" aria-hidden="true"></i></a></li>
        <?php if ($window[0] > 1): ?><li class="page-item"><a class="page-link" href="<?= e($link(1)) ?>">1</a></li><?php if ($window[0] > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif ?><?php endif ?>
        <?php foreach ($window as $p): ?>
        <li class="page-item<?= $p === $page ? ' active' : '' ?>" <?= $p === $page ? 'aria-current="page"' : '' ?>><a class="page-link" href="<?= e($link($p)) ?>"><?= $p ?></a></li>
        <?php endforeach ?>
        <?php if (end($window) < $last): ?><?php if (end($window) < $last - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif ?><li class="page-item"><a class="page-link" href="<?= e($link($last)) ?>"><?= $last ?></a></li><?php endif ?>
        <li class="page-item<?= $page >= $last ? ' disabled' : '' ?>"><a class="page-link" href="<?= e($link(min($last, $page + 1))) ?>" aria-label="<?= e(__('questions.create.next')) ?>"><i class="bi bi-chevron-right icon-dir" aria-hidden="true"></i></a></li>
    </ul>
</nav>
