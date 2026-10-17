<?php
/**
 * Création / modification d'un utilisateur.
 *
 * @var \App\Core\View $this
 * @var array<string, mixed>|null $user
 */
$this->extend('layouts/admin');
$editing = $user !== null;
$this->section('title', $editing ? e(__('admin.users.edit_title', ['name' => $user['full_name']])) : e(__('admin.users.create')));
$roles = \App\Core\Auth::hasRole('superadmin') ? array_values(\App\Models\User::ROLES) : ['member', 'scholar', 'moderator'];
$countries = (array) config('countries', []);
?>
<?php $this->section('content'); ?>
<form method="post" action="<?= e($editing ? route('admin.users.update', ['id' => $user['id']]) : route('admin.users.store')) ?>" class="df-card df-measure-wide" novalidate>
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-12 col-md-6"><label class="form-label" for="full_name"><?= e(__('admin.users.col.name')) ?> *</label><input type="text" class="form-control<?= error_for('full_name') ? ' is-invalid' : '' ?>" id="full_name" name="full_name" required maxlength="120" value="<?= e(old('full_name', $user['full_name'] ?? '')) ?>" dir="auto"><?= $this->partial('partials/field-error', ['name' => 'full_name']) ?></div>
        <div class="col-12 col-md-6"><label class="form-label" for="display_name"><?= e(__('validation.attributes.display_name')) ?></label><input type="text" class="form-control" id="display_name" name="display_name" maxlength="60" value="<?= e(old('display_name', $user['display_name'] ?? '')) ?>" dir="auto"></div>
        <div class="col-12 col-md-6"><label class="form-label" for="email"><?= e(__('admin.users.col.email')) ?></label><input type="email" class="form-control<?= error_for('email') ? ' is-invalid' : '' ?>" id="email" name="email" maxlength="190" value="<?= e(old('email', $user['email'] ?? '')) ?>" dir="ltr"><?= $this->partial('partials/field-error', ['name' => 'email']) ?></div>
        <div class="col-12 col-md-6"><label class="form-label" for="phone"><?= e(__('admin.users.col.phone')) ?></label><input type="tel" class="form-control<?= error_for('phone') ? ' is-invalid' : '' ?>" id="phone" name="phone" value="<?= e(old('phone', $user['phone'] ?? '')) ?>" placeholder="+223…" dir="ltr"><?= $this->partial('partials/field-error', ['name' => 'phone']) ?><div class="form-text"><?= e(__('admin.users.contact_help')) ?></div></div>
        <div class="col-12 col-md-6"><label class="form-label" for="whatsapp">WhatsApp</label><input type="tel" class="form-control<?= error_for('whatsapp') ? ' is-invalid' : '' ?>" id="whatsapp" name="whatsapp" value="<?= e(old('whatsapp', $user['whatsapp'] ?? '')) ?>" placeholder="+223…" dir="ltr"><?= $this->partial('partials/field-error', ['name' => 'whatsapp']) ?></div>
        <?php if (!$editing): ?>
        <div class="col-12 col-md-6"><label class="form-label" for="role"><?= e(__('admin.users.col.role')) ?> *</label><select class="form-select" id="role" name="role"><?php foreach ($roles as $r): ?><option value="<?= e($r) ?>" <?= old('role', 'member') === $r ? 'selected' : '' ?>><?= e(__('account.roles.' . $r)) ?></option><?php endforeach ?></select></div>
        <?php endif ?>
        <div class="col-6 col-md-3"><label class="form-label" for="locale"><?= e(__('validation.attributes.locale')) ?></label><select class="form-select" id="locale" name="locale"><?php foreach (\App\Core\Lang::locales() as $l): ?><option value="<?= e($l) ?>" <?= old('locale', $user['locale'] ?? 'fr') === $l ? 'selected' : '' ?>><?= e(__('common.lang.' . $l)) ?></option><?php endforeach ?></select></div>
        <div class="col-6 col-md-3"><label class="form-label" for="country"><?= e(__('admin.users.col.country')) ?></label><select class="form-select" id="country" name="country"><option value="">—</option><?php foreach ($countries as $code): ?><option value="<?= e($code) ?>" <?= old('country', $user['country'] ?? '') === $code ? 'selected' : '' ?>><?= e(\Locale::getDisplayRegion('-' . $code, locale()) ?: $code) ?></option><?php endforeach ?></select></div>
        <?php if ($editing): ?><div class="col-12 col-md-6"><label class="form-label" for="city"><?= e(__('validation.attributes.city')) ?></label><input type="text" class="form-control" id="city" name="city" maxlength="80" value="<?= e(old('city', $user['city'] ?? '')) ?>" dir="auto"></div><?php endif ?>
    </div>
    <?php if (!$editing): ?><p class="small text-secondary mt-3 mb-0"><i class="bi bi-info-circle" aria-hidden="true"></i> <?= e(__('admin.users.create_help')) ?></p><?php endif ?>
    <div class="d-flex gap-2 mt-3">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check2" aria-hidden="true"></i> <?= e(__('common.save')) ?></button>
        <a class="btn btn-outline-secondary" href="<?= e($editing ? route('admin.users.show', ['id' => $user['id']]) : route('admin.users')) ?>"><?= e(__('common.cancel')) ?></a>
    </div>
</form>
<?php $this->endSection(); ?>
