<?php
/**
 * Champ téléphone international (intl-tel-input, +223 par défaut). Sans JavaScript, le numéro
 * est saisi au format international et normalisé côté serveur (PhoneNumber::normalize).
 *
 * @var string $name       Nom du champ (phone, whatsapp)
 * @var string $label
 * @var string|null $value
 * @var string|null $help
 * @var bool $required
 */
$value ??= null;
$help ??= null;
$required ??= false;
$invalid = error_for($name) !== null;
$describedBy = trim(($help !== null ? $name . '-help ' : '') . ($invalid ? $name . '-error' : ''));
?>
<div class="mb-3 df-phone-field">
    <label class="form-label" for="<?= e($name) ?>"><?= e($label) ?><?php if (!$required): ?> <span class="text-secondary fw-normal">(<?= e(__('auth.register.optional')) ?>)</span><?php endif ?></label>
    <input type="tel" class="form-control<?= $invalid ? ' is-invalid' : '' ?>" id="<?= e($name) ?>" name="<?= e($name) ?>"
           value="<?= e(old($name, $value ?? '')) ?>" autocomplete="tel" inputmode="tel" dir="ltr"
           data-phone="<?= e($name) ?>_e164" data-country="<?= e(strtolower((string) (auth()['country'] ?? 'ml'))) ?>"
           <?= $describedBy !== '' ? 'aria-describedby="' . e($describedBy) . '"' : '' ?>>
    <?php if ($help !== null): ?><div class="form-text" id="<?= e($name) ?>-help"><?= e($help) ?></div><?php endif ?>
    <?= $this->partial('partials/field-error', ['name' => $name]) ?>
</div>
