<?php
/**
 * Modification du profil (§4.4).
 *
 * @var \App\Core\View $this
 * @var array<string, mixed> $user
 * @var array<string, string> $countries
 */
$this->extend('layouts/account');
$this->section('title', __('account.profile.title'));
$this->section('subtitle', __('account.profile.subtitle'));
$whatsappSame = old('whatsapp_same', ($user['whatsapp'] ?? null) === ($user['phone'] ?? null) || empty($user['whatsapp']) ? '1' : '0') === '1';
?>
<?php $this->section('content'); ?>
<form method="post" action="<?= e(route('account.profile.update')) ?>" enctype="multipart/form-data" novalidate class="df-form">
    <?= csrf_field() ?>

    <div class="df-card mb-4">
        <h2 class="df-card__title"><i class="bi bi-person-badge" aria-hidden="true"></i> <?= e(__('account.profile.identity')) ?></h2>
        <div class="d-flex align-items-center gap-3 mb-3">
            <?php if (!empty($user['avatar_path'])): ?>
            <img class="df-avatar df-avatar--lg" src="<?= e($user['avatar_path']) ?>" alt="" width="80" height="80">
            <?php else: ?>
            <span class="df-avatar df-avatar--lg df-avatar--initials" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) $user['full_name'], 0, 1))) ?></span>
            <?php endif ?>
            <div class="flex-grow-1">
                <label class="form-label" for="avatar"><?= e(__('account.profile.photo')) ?></label>
                <input type="file" class="form-control<?= error_for('avatar') !== null ? ' is-invalid' : '' ?>" id="avatar" name="avatar" accept="image/jpeg,image/png,image/webp" aria-describedby="avatar-help">
                <div class="form-text" id="avatar-help"><?= e(__('account.profile.photo_help')) ?></div>
                <?= $this->partial('partials/field-error', ['name' => 'avatar']) ?>
                <?php if (!empty($user['avatar_path'])): ?>
                <div class="form-check mt-1">
                    <input class="form-check-input" type="checkbox" id="remove_avatar" name="remove_avatar" value="1">
                    <label class="form-check-label" for="remove_avatar"><?= e(__('account.profile.remove_photo')) ?></label>
                </div>
                <?php endif ?>
            </div>
        </div>
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label class="form-label" for="full_name"><?= e(__('auth.register.full_name')) ?></label>
                <input type="text" class="form-control<?= error_for('full_name') !== null ? ' is-invalid' : '' ?>" id="full_name" name="full_name" value="<?= e(old('full_name', $user['full_name'])) ?>" required minlength="2" maxlength="120" dir="auto" autocomplete="name">
                <?= $this->partial('partials/field-error', ['name' => 'full_name']) ?>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="display_name"><?= e(__('auth.register.display_name')) ?></label>
                <input type="text" class="form-control<?= error_for('display_name') !== null ? ' is-invalid' : '' ?>" id="display_name" name="display_name" value="<?= e(old('display_name', $user['display_name'] ?? '')) ?>" maxlength="60" dir="auto" autocomplete="nickname" aria-describedby="display_name-help">
                <div class="form-text" id="display_name-help"><?= e(__('auth.register.display_name_help')) ?></div>
                <?= $this->partial('partials/field-error', ['name' => 'display_name']) ?>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="gender"><?= e(__('auth.register.gender')) ?></label>
                <select class="form-select" id="gender" name="gender">
                    <?php foreach (['' => 'gender_unspecified', 'm' => 'gender_m', 'f' => 'gender_f'] as $value => $key): ?>
                    <option value="<?= e($value) ?>" <?= (string) old('gender', $user['gender'] ?? '') === (string) $value ? 'selected' : '' ?>><?= e(__('auth.register.' . $key)) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="country"><?= e(__('auth.register.country')) ?></label>
                <select class="form-select" id="country" name="country" autocomplete="country">
                    <option value=""><?= e(__('auth.register.gender_unspecified')) ?></option>
                    <?php foreach ($countries as $code => $label): ?>
                    <option value="<?= e($code) ?>" <?= old('country', $user['country'] ?? '') === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="city"><?= e(__('auth.register.city')) ?></label>
                <input type="text" class="form-control" id="city" name="city" value="<?= e(old('city', $user['city'] ?? '')) ?>" maxlength="80" dir="auto" autocomplete="address-level2">
            </div>
        </div>
    </div>

    <div class="df-card mb-4">
        <h2 class="df-card__title"><i class="bi bi-telephone" aria-hidden="true"></i> <?= e(__('account.profile.contact')) ?></h2>
        <p class="form-text mt-0"><?= e(__('auth.register.contact_help')) ?></p>
        <?= $this->partial('partials/phone-field', ['name' => 'phone', 'label' => __('auth.register.phone'), 'value' => $user['phone'] ?? '', 'required' => false]) ?>
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" id="whatsapp_same" name="whatsapp_same" value="1" <?= $whatsappSame ? 'checked' : '' ?> data-toggle-hidden="whatsapp-wrapper" data-toggle-inverse>
            <label class="form-check-label" for="whatsapp_same"><?= e(__('auth.register.whatsapp_same')) ?></label>
        </div>
        <div id="whatsapp-wrapper" <?= $whatsappSame ? 'hidden' : '' ?>>
            <?= $this->partial('partials/phone-field', ['name' => 'whatsapp', 'label' => __('auth.register.whatsapp'), 'value' => $user['whatsapp'] ?? '', 'required' => false]) ?>
        </div>
        <div class="mb-1">
            <label class="form-label" for="email"><?= e(__('auth.register.email')) ?>
                <?php if (!empty($user['email'])): ?>
                <span class="df-status df-status--<?= $user['email_verified_at'] ? 'success' : 'warning' ?> ms-2"><i class="bi bi-<?= $user['email_verified_at'] ? 'patch-check-fill' : 'exclamation-circle' ?>" aria-hidden="true"></i> <?= e(__($user['email_verified_at'] ? 'account.status.verified' : 'account.status.not_verified')) ?></span>
                <?php endif ?>
            </label>
            <input type="email" class="form-control<?= error_for('email') !== null ? ' is-invalid' : '' ?>" id="email" name="email" value="<?= e(old('email', $user['email'] ?? '')) ?>" maxlength="190" dir="ltr" autocomplete="email" inputmode="email">
            <?= $this->partial('partials/field-error', ['name' => 'email']) ?>
        </div>
    </div>

    <div class="df-card mb-4">
        <h2 class="df-card__title"><i class="bi bi-sliders" aria-hidden="true"></i> <?= e(__('account.profile.preferences')) ?></h2>
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label class="form-label" for="locale"><?= e(__('auth.register.locale')) ?></label>
                <select class="form-select" id="locale" name="locale">
                    <?php foreach (\App\Core\Lang::locales() as $code): ?>
                    <option value="<?= e($code) ?>" <?= old('locale', $user['locale']) === $code ? 'selected' : '' ?> lang="<?= e($code) ?>"><?= e(__('common.lang.' . $code)) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="preferred_channel"><?= e(__('account.profile.preferred_channel')) ?></label>
                <select class="form-select" id="preferred_channel" name="preferred_channel">
                    <?php foreach (['platform', 'whatsapp', 'both'] as $channel): ?>
                    <option value="<?= e($channel) ?>" <?= old('preferred_channel', $user['preferred_channel']) === $channel ? 'selected' : '' ?>><?= e(__('account.profile.channel_' . $channel)) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-12">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="notify_email" name="notify_email" value="1" <?= old('notify_email', (string) $user['notify_email']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="notify_email"><?= e(__('account.profile.notify_email')) ?></label>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="notify_whatsapp" name="notify_whatsapp" value="1" <?= old('notify_whatsapp', (string) $user['notify_whatsapp']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="notify_whatsapp"><?= e(__('account.profile.notify_whatsapp')) ?></label>
                </div>
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-check2" aria-hidden="true"></i> <?= e(__('account.profile.save')) ?></button>
</form>
<?php $this->endSection(); ?>
