<?php
/**
 * Message d'erreur d'un champ de formulaire (associé via aria-describedby="{name}-error").
 *
 * @var string $name
 */
$message = error_for($name);
if ($message === null) {
    return;
}
?>
<div class="invalid-feedback d-block" id="<?= e($name) ?>-error"><?= e($message) ?></div>
