<?php
/**
 * Contact (§4.11) : formulaire + coordonnées + WhatsApp + réseaux sociaux.
 *
 * @var \App\Core\View $this
 * @var list<string> $subjects
 */
$this->extend('layouts/front');
$this->section('title', __('front.contact.title'));
$this->section('description', __('front.contact.intro'));
$phone = setting('contact_phone');
$phone = $phone !== null && !str_contains($phone, 'X') ? $phone : null;
$email = setting('contact_email');
$address = setting('contact_address');
$hours = setting('opening_hours');
$whatsapp = whatsapp_link(__('front.whatsapp.message'));
$askUrl = ask_url();
$socials = [];
foreach (['facebook' => 'facebook', 'youtube' => 'youtube', 'instagram' => 'instagram', 'telegram' => 'telegram', 'x' => 'twitter-x', 'tiktok' => 'tiktok'] as $key => $icon) {
    $url = setting('social_' . $key);
    if ($url !== null && $url !== '') {
        $socials[$key] = ['url' => $url, 'icon' => $icon];
    }
}
$this->section('head'); ?>
<link rel="stylesheet" href="<?= e(asset('vendor/intl-tel-input/css/intlTelInput.min.css')) ?>">
<?php $this->endSection(); $this->section('scripts'); ?>
<script src="<?= e(asset('vendor/intl-tel-input/js/intlTelInputWithUtils.min.js')) ?>" defer></script>
<script src="<?= e(asset('assets/js/auth.js')) ?>" type="module"></script>
<?php $this->endSection(); ?>
<?php $this->section('content'); ?>
<?= $this->partial('partials/breadcrumb', ['items' => [['label' => __('front.contact.title')]]]) ?>
<section class="df-section">
    <div class="container">
        <div class="df-section__head">
            <h1 class="df-section__title"><?= e(__('front.contact.title')) ?></h1>
            <p class="df-section__subtitle df-measure"><?= e(__('front.contact.intro')) ?></p>
        </div>
        <div class="row g-4">
            <div class="col-12 col-lg-7">
                <div class="alert alert-warning d-flex gap-2 align-items-start" role="note">
                    <i class="bi bi-mic-fill mt-1" aria-hidden="true"></i>
                    <div><?= e(__('front.contact.reminder')) ?> <?php if ($askUrl !== null): ?><a class="alert-link" href="<?= e($askUrl) ?>"><?= e(__('nav.ask')) ?></a><?php endif ?></div>
                </div>
                <form method="post" action="<?= e(route('contact.send')) ?>" class="df-card df-form" novalidate>
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="name"><?= e(__('front.contact.name')) ?></label>
                        <input type="text" class="form-control<?= error_for('name') !== null ? ' is-invalid' : '' ?>" id="name" name="name" value="<?= e(old('name', auth()['full_name'] ?? '')) ?>" required minlength="2" maxlength="120" autocomplete="name" dir="auto">
                        <?= $this->partial('partials/field-error', ['name' => 'name']) ?>
                    </div>
                    <p class="form-text"><?= e(__('front.contact.contact_help')) ?></p>
                    <div class="row">
                        <div class="col-12 col-md-6">
                            <div class="mb-3">
                                <label class="form-label" for="email"><?= e(__('front.contact.email')) ?></label>
                                <input type="email" class="form-control<?= error_for('email') !== null ? ' is-invalid' : '' ?>" id="email" name="email" value="<?= e(old('email', auth()['email'] ?? '')) ?>" maxlength="190" autocomplete="email" inputmode="email" dir="ltr">
                                <?= $this->partial('partials/field-error', ['name' => 'email']) ?>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <?= $this->partial('partials/phone-field', ['name' => 'phone', 'label' => __('front.contact.phone'), 'value' => auth()['phone'] ?? '', 'required' => true]) ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="subject"><?= e(__('front.contact.subject')) ?></label>
                        <select class="form-select<?= error_for('subject') !== null ? ' is-invalid' : '' ?>" id="subject" name="subject" required>
                            <?php foreach ($subjects as $subject): ?>
                            <option value="<?= e($subject) ?>" <?= old('subject', 'general') === $subject ? 'selected' : '' ?>><?= e(__('front.contact.subjects.' . $subject)) ?></option>
                            <?php endforeach ?>
                        </select>
                        <?= $this->partial('partials/field-error', ['name' => 'subject']) ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="message"><?= e(__('front.contact.message')) ?></label>
                        <textarea class="form-control<?= error_for('message') !== null ? ' is-invalid' : '' ?>" id="message" name="message" rows="6" required minlength="10" maxlength="5000" dir="auto"><?= e(old('message')) ?></textarea>
                        <?= $this->partial('partials/field-error', ['name' => 'message']) ?>
                    </div>
                    <?= $this->partial('partials/turnstile') ?>
                    <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-send" aria-hidden="true"></i> <?= e(__('front.contact.send')) ?></button>
                </form>
            </div>
            <div class="col-12 col-lg-5">
                <div class="df-card df-contact-details">
                    <h2 class="df-card__title"><i class="bi bi-geo-alt" aria-hidden="true"></i> <?= e(__('front.contact.details')) ?></h2>
                    <dl class="df-dl mb-3">
                        <?php if ($address): ?><dt><?= e(__('front.contact.address')) ?></dt><dd><?= e($address) ?></dd><?php endif ?>
                        <?php if ($phone !== null): ?><dt><?= e(__('front.contact.phone')) ?></dt><dd><a class="ltr" href="tel:<?= e($phone) ?>"><?= e(\App\Services\PhoneNumber::format($phone)) ?></a></dd><?php endif ?>
                        <?php if ($email): ?><dt><?= e(__('front.contact.email')) ?></dt><dd><a class="ltr" href="mailto:<?= e($email) ?>"><?= e($email) ?></a></dd><?php endif ?>
                        <?php if ($hours): ?><dt><?= e(__('front.contact.hours')) ?></dt><dd><?= e($hours) ?></dd><?php endif ?>
                    </dl>
                    <?php if ($whatsapp !== null): ?>
                    <a class="btn btn-success w-100 mb-3" href="<?= e($whatsapp) ?>" target="_blank" rel="noopener"><i class="bi bi-whatsapp" aria-hidden="true"></i> <?= e(__('front.contact.whatsapp')) ?></a>
                    <?php endif ?>
                    <?php if ($socials !== []): ?>
                    <p class="fw-semibold mb-2"><?= e(__('front.contact.social')) ?></p>
                    <ul class="list-inline mb-0 df-social-list">
                        <?php foreach ($socials as $key => $social): ?>
                        <li class="list-inline-item"><a class="btn btn-outline-primary btn-sm" href="<?= e($social['url']) ?>" target="_blank" rel="noopener" aria-label="<?= e(__('front.social.' . $key)) ?>"><i class="bi bi-<?= e($social['icon']) ?>" aria-hidden="true"></i></a></li>
                        <?php endforeach ?>
                    </ul>
                    <?php endif ?>
                </div>
            </div>
        </div>
    </div>
</section>
<?php $this->endSection(); ?>
