<?php
/**
 * Champ mot de passe avec bouton afficher/masquer et, en option, indicateur de robustesse.
 *
 * @var string $name
 * @var string $label
 * @var bool $strength      Afficher l'indicateur de robustesse
 * @var string $autocomplete new-password | current-password
 * @var string|null $help
 */
$strength ??= false;
$autocomplete ??= 'current-password';
$help ??= null;
$invalid = error_for($name) !== null;
$describedBy = trim(($help !== null ? $name . '-help ' : '') . ($invalid ? $name . '-error' : ''));
?>
<div class="mb-3">
    <label class="form-label" for="<?= e($name) ?>"><?= e($label) ?></label>
    <div class="input-group df-password">
        <input type="password" class="form-control<?= $invalid ? ' is-invalid' : '' ?>" id="<?= e($name) ?>" name="<?= e($name) ?>"
               autocomplete="<?= e($autocomplete) ?>" required minlength="8" dir="ltr"
               <?= $strength ? 'data-strength="' . e($name) . '-strength"' : '' ?>
               <?= $describedBy !== '' ? 'aria-describedby="' . e($describedBy) . '"' : '' ?>>
        <button class="btn btn-outline-secondary" type="button" data-toggle-password="<?= e($name) ?>"
                aria-label="<?= e(__('auth.password.show')) ?>" data-label-show="<?= e(__('auth.password.show')) ?>" data-label-hide="<?= e(__('auth.password.hide')) ?>">
            <i class="bi bi-eye" aria-hidden="true"></i>
        </button>
    </div>
    <?php if ($strength): ?>
    <div class="df-strength mt-2" id="<?= e($name) ?>-strength" hidden
         data-labels="<?= e(json_encode([__('auth.password.strength.weak'), __('auth.password.strength.fair'), __('auth.password.strength.good'), __('auth.password.strength.strong')], JSON_UNESCAPED_UNICODE)) ?>">
        <div class="progress" role="progressbar" aria-label="<?= e(__('auth.password.strength.label')) ?>" aria-valuemin="0" aria-valuemax="4" aria-valuenow="0" style="height: 6px">
            <div class="progress-bar" style="width: 0%"></div>
        </div>
        <small class="df-strength__label text-secondary" aria-live="polite"></small>
    </div>
    <?php endif ?>
    <?php if ($help !== null): ?><div class="form-text" id="<?= e($name) ?>-help"><?= e($help) ?></div><?php endif ?>
    <?= $this->partial('partials/field-error', ['name' => $name]) ?>
</div>
