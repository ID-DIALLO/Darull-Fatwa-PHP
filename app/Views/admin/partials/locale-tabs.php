<?php
/**
 * Onglets par langue pour les formulaires multilingues. Le contenu de chaque onglet est fourni par
 * $render(string $locale): string.
 *
 * @var string $id Préfixe unique
 * @var callable $render
 * @var array<string, bool> $filled Langues déjà renseignées (pastille)
 */
$filled ??= [];
$locales = \App\Core\Lang::locales();
?>
<ul class="nav nav-tabs df-admin__tabs" role="tablist">
    <?php foreach ($locales as $i => $code): ?>
    <li class="nav-item" role="presentation">
        <button class="nav-link<?= $i === 0 ? ' active' : '' ?>" id="<?= e($id) ?>-tab-<?= e($code) ?>" data-bs-toggle="tab" data-bs-target="#<?= e($id) ?>-<?= e($code) ?>" type="button" role="tab" aria-controls="<?= e($id) ?>-<?= e($code) ?>" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>">
            <?= e(__('common.lang.' . $code)) ?> <?php if (!empty($filled[$code])): ?><i class="bi bi-check-circle-fill text-success small" aria-label="<?= e(__('admin.common.filled')) ?>"></i><?php endif ?>
        </button>
    </li>
    <?php endforeach ?>
</ul>
<div class="tab-content df-admin__tab-content">
    <?php foreach ($locales as $i => $code): ?>
    <div class="tab-pane fade<?= $i === 0 ? ' show active' : '' ?>" id="<?= e($id) ?>-<?= e($code) ?>" role="tabpanel" aria-labelledby="<?= e($id) ?>-tab-<?= e($code) ?>" lang="<?= e($code) ?>" dir="<?= e(text_dir($code)) ?>">
        <?= $render($code) ?>
    </div>
    <?php endforeach ?>
</div>
