<?php
/**
 * Bouton d'action POST (CSRF) avec confirmation facultative.
 *
 * @var string $url
 * @var string $label
 * @var string|null $icon
 * @var string|null $class
 * @var string|null $confirm Message de confirmation (data-confirm)
 * @var array<string, scalar> $hidden
 */
$icon ??= null;
$class ??= 'btn btn-sm btn-outline-secondary';
$confirm ??= null;
$hidden ??= [];
?>
<form method="post" action="<?= e($url) ?>" class="d-inline" <?= $confirm !== null ? 'data-confirm="' . e($confirm) . '"' : '' ?>>
    <?= csrf_field() ?>
    <?php foreach ($hidden as $name => $value): ?><input type="hidden" name="<?= e($name) ?>" value="<?= e((string) $value) ?>"><?php endforeach ?>
    <button type="submit" class="<?= e($class) ?>"><?php if ($icon): ?><i class="bi bi-<?= e($icon) ?>" aria-hidden="true"></i> <?php endif ?><?= e($label) ?></button>
</form>
