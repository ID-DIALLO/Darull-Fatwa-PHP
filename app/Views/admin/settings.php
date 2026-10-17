<?php
/**
 * Paramètres par groupe (§4.6.3) ; secrets chiffrés jamais réaffichés.
 *
 * @var \App\Core\View $this
 * @var array<string, array<string, mixed>> $groups
 * @var string $current
 * @var array<string, string> $values
 * @var array<string, bool> $secrets
 * @var bool $maintenanceFlag
 */
$this->extend('layouts/admin');
$this->section('title', __('admin.settings.title'));
$this->section('subtitle', __('admin.settings.intro'));
$group = $groups[$current];
$this->section('actions');
?>
<?= $this->partial('admin/partials/post-button', ['url' => route('admin.cache.clear'), 'label' => __('admin.settings.clear_cache'), 'icon' => 'arrow-clockwise', 'class' => 'btn btn-outline-secondary']) ?>
<?php $this->endSection(); ?>
<?php $this->section('content'); ?>
<div class="row g-3">
    <div class="col-12 col-lg-3">
        <nav class="df-card p-2" aria-label="<?= e(__('admin.settings.groups')) ?>">
            <ul class="nav flex-column df-account__nav">
                <?php foreach ($groups as $key => $g): ?>
                <li class="nav-item"><a class="nav-link<?= $key === $current ? ' active' : '' ?>" href="<?= e(route('admin.settings', ['group' => $key])) ?>" <?= $key === $current ? 'aria-current="page"' : '' ?>><i class="bi bi-<?= e($g['icon']) ?>" aria-hidden="true"></i> <span><?= e(__('admin.settings.group.' . $key)) ?></span><?php if (!empty($g['superadmin'])): ?> <i class="bi bi-lock-fill small ms-auto" title="<?= e(__('account.roles.superadmin')) ?>"></i><?php endif ?></a></li>
                <?php endforeach ?>
            </ul>
        </nav>
    </div>
    <div class="col-12 col-lg-9">
        <?php if ($maintenanceFlag): ?><div class="alert alert-warning"><i class="bi bi-cone-striped" aria-hidden="true"></i> <?= e(__('admin.settings.maintenance_flag')) ?></div><?php endif ?>
        <form method="post" action="<?= e(route('admin.settings.update', ['group' => $current])) ?>" class="df-card" novalidate>
            <?= csrf_field() ?>
            <h2 class="df-card__title h6"><i class="bi bi-<?= e($group['icon']) ?>" aria-hidden="true"></i> <?= e(__('admin.settings.group.' . $current)) ?></h2>
            <?php if (\App\Core\Lang::has('admin.settings.group_help.' . $current)): ?><p class="small text-secondary"><?= e(__('admin.settings.group_help.' . $current)) ?></p><?php endif ?>
            <div class="row g-3">
                <?php foreach ($group['fields'] as $key => $field): $type = $field['type']; $label = __('admin.settings.field.' . $key); $help = \App\Core\Lang::has('admin.settings.help.' . $key) ? __('admin.settings.help.' . $key) : null; $invalid = error_for($key) !== null; ?>
                <div class="col-12 <?= in_array($type, ['textarea'], true) ? '' : 'col-md-6' ?>">
                    <?php if ($type === 'bool'): ?>
                    <div class="form-check form-switch mt-md-4"><input class="form-check-input" type="checkbox" role="switch" id="<?= e($key) ?>" name="<?= e($key) ?>" value="1" <?= ($values[$key] ?? '0') === '1' ? 'checked' : '' ?>><label class="form-check-label" for="<?= e($key) ?>"><?= e($label) ?></label><?php if ($help): ?><div class="form-text"><?= e($help) ?></div><?php endif ?></div>
                    <?php elseif ($type === 'secret'): ?>
                    <label class="form-label" for="<?= e($key) ?>"><?= e($label) ?> <i class="bi bi-lock-fill text-secondary small" aria-hidden="true"></i></label>
                    <input type="password" class="form-control" id="<?= e($key) ?>" name="<?= e($key) ?>" autocomplete="new-password" placeholder="<?= e(!empty($secrets[$key]) ? __('admin.settings.secret_set') : __('admin.settings.secret_unset')) ?>" dir="ltr">
                    <div class="d-flex justify-content-between"><div class="form-text"><?= e($help ?? __('admin.settings.secret_help')) ?></div><?php if (!empty($secrets[$key])): ?><div class="form-check form-check-inline mt-1"><input class="form-check-input" type="checkbox" id="clear_<?= e($key) ?>" name="clear_<?= e($key) ?>" value="1"><label class="form-check-label small" for="clear_<?= e($key) ?>"><?= e(__('admin.settings.secret_clear')) ?></label></div><?php endif ?></div>
                    <?php elseif (str_starts_with($type, 'select:')): ?>
                    <label class="form-label" for="<?= e($key) ?>"><?= e($label) ?></label>
                    <select class="form-select" id="<?= e($key) ?>" name="<?= e($key) ?>"><?php foreach (explode(',', substr($type, 7)) as $opt): ?><option value="<?= e($opt) ?>" <?= ($values[$key] ?? '') === $opt ? 'selected' : '' ?>><?= e(\App\Core\Lang::has('admin.settings.option.' . $key . '.' . $opt) ? __('admin.settings.option.' . $key . '.' . $opt) : $opt) ?></option><?php endforeach ?></select>
                    <?php if ($help): ?><div class="form-text"><?= e($help) ?></div><?php endif ?>
                    <?php else: ?>
                    <label class="form-label" for="<?= e($key) ?>"><?= e($label) ?></label>
                    <input type="<?= $type === 'int' ? 'number' : ($type === 'email' ? 'email' : ($type === 'url' ? 'url' : 'text')) ?>" class="form-control<?= $invalid ? ' is-invalid' : '' ?>" id="<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e(old($key, $values[$key] ?? '')) ?>" <?= isset($field['dir']) ? 'dir="' . e($field['dir']) . '"' : ($type !== 'text' ? 'dir="ltr"' : 'dir="auto"') ?>>
                    <?= $this->partial('partials/field-error', ['name' => $key]) ?>
                    <?php if ($help): ?><div class="form-text"><?= e($help) ?></div><?php endif ?>
                    <?php endif ?>
                </div>
                <?php endforeach ?>
            </div>
            <div class="mt-3"><button type="submit" class="btn btn-primary"><i class="bi bi-check2" aria-hidden="true"></i> <?= e(__('common.save')) ?></button></div>
        </form>
    </div>
</div>
<?php $this->endSection(); ?>
