<?php
/**
 * Inscription (§4.1) : formulaire court, mobile d'abord. Téléphone ou e-mail obligatoire.
 *
 * @var \App\Core\View $this
 * @var string|null $redirect
 * @var array<string, string> $countries
 */
$this->extend('layouts/auth');
$this->section('title', __('auth.register.title'));
$this->section('subtitle', __('auth.register.subtitle'));
$termsLink = '<a href="' . e(route('home')) . '" target="_blank" rel="noopener">' . e(__('auth.register.terms_label')) . '</a>';
$privacyLink = '<a href="' . e(route('home')) . '" target="_blank" rel="noopener">' . e(__('auth.register.privacy_label')) . '</a>';
$termsText = str_replace([':terms', ':privacy'], [$termsLink, $privacyLink], e(__('auth.register.terms')));
$whatsappSame = old('whatsapp_same', '1') === '1';
?>
<?php $this->section('content'); ?>
<form method="post" action="<?= e(route('auth.register.post')) ?>" novalidate class="df-form">
    <?= csrf_field() ?>
    <?php if ($redirect !== null): ?><input type="hidden" name="redirect" value="<?= e($redirect) ?>"><?php endif ?>

    <div class="mb-3">
        <label class="form-label" for="full_name"><?= e(__('auth.register.full_name')) ?></label>
        <input type="text" class="form-control<?= error_for('full_name') !== null ? ' is-invalid' : '' ?>" id="full_name" name="full_name"
               value="<?= e(old('full_name')) ?>" autocomplete="name" required minlength="2" maxlength="120" dir="auto" autofocus>
        <?= $this->partial('partials/field-error', ['name' => 'full_name']) ?>
    </div>

    <div class="mb-3">
        <label class="form-label" for="display_name"><?= e(__('auth.register.display_name')) ?> <span class="text-secondary fw-normal">(<?= e(__('auth.register.optional')) ?>)</span></label>
        <input type="text" class="form-control<?= error_for('display_name') !== null ? ' is-invalid' : '' ?>" id="display_name" name="display_name"
               value="<?= e(old('display_name')) ?>" autocomplete="nickname" maxlength="60" dir="auto" aria-describedby="display_name-help">
        <div class="form-text" id="display_name-help"><?= e(__('auth.register.display_name_help')) ?></div>
        <?= $this->partial('partials/field-error', ['name' => 'display_name']) ?>
    </div>

    <fieldset class="df-fieldset">
        <legend><?= e(__('auth.register.contact_title')) ?></legend>
        <p class="form-text mt-0"><?= e(__('auth.register.contact_help')) ?></p>

        <?= $this->partial('partials/phone-field', ['name' => 'phone', 'label' => __('auth.register.phone'), 'help' => __('auth.register.phone_help'), 'required' => false]) ?>

        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" id="whatsapp_same" name="whatsapp_same" value="1" <?= $whatsappSame ? 'checked' : '' ?> data-toggle-hidden="whatsapp-wrapper" data-toggle-inverse>
            <label class="form-check-label" for="whatsapp_same"><?= e(__('auth.register.whatsapp_same')) ?></label>
        </div>
        <div id="whatsapp-wrapper" <?= $whatsappSame ? 'hidden' : '' ?>>
            <?= $this->partial('partials/phone-field', ['name' => 'whatsapp', 'label' => __('auth.register.whatsapp'), 'required' => false]) ?>
        </div>

        <div class="mb-3">
            <label class="form-label" for="email"><?= e(__('auth.register.email')) ?></label>
            <input type="email" class="form-control<?= error_for('email') !== null ? ' is-invalid' : '' ?>" id="email" name="email"
                   value="<?= e(old('email')) ?>" autocomplete="email" inputmode="email" maxlength="190" dir="ltr">
            <?= $this->partial('partials/field-error', ['name' => 'email']) ?>
        </div>
    </fieldset>

    <?= $this->partial('partials/password-field', ['name' => 'password', 'label' => __('auth.register.password'), 'autocomplete' => 'new-password', 'strength' => true, 'help' => __('auth.register.password_help')]) ?>
    <?= $this->partial('partials/password-field', ['name' => 'password_confirmation', 'label' => __('auth.register.password_confirmation'), 'autocomplete' => 'new-password']) ?>

    <fieldset class="df-fieldset">
        <legend><?= e(__('auth.register.preferences_title')) ?></legend>
        <div class="row g-3">
            <div class="col-12 col-sm-6">
                <label class="form-label" for="locale"><?= e(__('auth.register.locale')) ?></label>
                <select class="form-select" id="locale" name="locale" autocomplete="language">
                    <?php foreach (\App\Core\Lang::locales() as $code): ?>
                    <option value="<?= e($code) ?>" <?= old('locale', locale()) === $code ? 'selected' : '' ?> lang="<?= e($code) ?>"><?= e(__('common.lang.' . $code)) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-12 col-sm-6">
                <label class="form-label" for="gender"><?= e(__('auth.register.gender')) ?></label>
                <select class="form-select" id="gender" name="gender" aria-describedby="gender-help">
                    <option value="" <?= old('gender') === '' ? 'selected' : '' ?>><?= e(__('auth.register.gender_unspecified')) ?></option>
                    <option value="m" <?= old('gender') === 'm' ? 'selected' : '' ?>><?= e(__('auth.register.gender_m')) ?></option>
                    <option value="f" <?= old('gender') === 'f' ? 'selected' : '' ?>><?= e(__('auth.register.gender_f')) ?></option>
                </select>
                <div class="form-text" id="gender-help"><?= e(__('auth.register.gender_help')) ?></div>
            </div>
            <div class="col-12 col-sm-6">
                <label class="form-label" for="country"><?= e(__('auth.register.country')) ?></label>
                <select class="form-select" id="country" name="country" autocomplete="country">
                    <option value=""><?= e(__('auth.register.gender_unspecified')) ?></option>
                    <?php foreach ($countries as $code => $label): ?>
                    <option value="<?= e($code) ?>" <?= old('country', 'ML') === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-12 col-sm-6">
                <label class="form-label" for="city"><?= e(__('auth.register.city')) ?></label>
                <input type="text" class="form-control" id="city" name="city" value="<?= e(old('city')) ?>" autocomplete="address-level2" maxlength="80" dir="auto">
            </div>
        </div>
    </fieldset>

    <div class="form-check mb-3">
        <input class="form-check-input<?= error_for('terms') !== null ? ' is-invalid' : '' ?>" type="checkbox" id="terms" name="terms" value="1" required <?= old('terms') ? 'checked' : '' ?>>
        <label class="form-check-label" for="terms"><?= $termsText ?></label>
        <?= $this->partial('partials/field-error', ['name' => 'terms']) ?>
    </div>

    <?= $this->partial('partials/turnstile') ?>

    <button type="submit" class="btn btn-gold w-100 btn-lg"><i class="bi bi-person-plus" aria-hidden="true"></i> <?= e(__('auth.register.submit')) ?></button>
</form>
<?php $this->endSection(); ?>

<?php $this->section('aside'); ?>
<p class="mb-0"><?= e(__('auth.register.have_account')) ?>
    <a class="fw-semibold" href="<?= e(route('auth.login', $redirect !== null ? ['redirect' => $redirect] : [])) ?>"><?= e(__('auth.register.login_link')) ?></a>
</p>
<?php $this->endSection(); ?>
