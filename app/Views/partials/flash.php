<?php
/** Messages flash (après une redirection). Pastille + texte : jamais la couleur seule. */
$classes = ['success' => 'success', 'error' => 'danger', 'warning' => 'warning', 'info' => 'info'];
$icons = ['success' => 'check-circle-fill', 'error' => 'exclamation-octagon-fill', 'warning' => 'exclamation-triangle-fill', 'info' => 'info-circle-fill'];
$messages = \App\Core\Flash::all();
if ($messages === []) {
    return;
}
?>
<div class="container pt-3">
    <?php foreach ($messages as $flash): $type = $flash['type'] ?? 'info'; ?>
    <div class="alert alert-<?= e($classes[$type] ?? 'info') ?> d-flex align-items-center gap-2" role="<?= $type === 'error' ? 'alert' : 'status' ?>">
        <i class="bi bi-<?= e($icons[$type] ?? 'info-circle-fill') ?>" aria-hidden="true"></i>
        <span><?= e($flash['message'] ?? '') ?></span>
    </div>
    <?php endforeach ?>
</div>
