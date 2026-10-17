<?php /** Formulaire d'abonnement à la newsletter (double confirmation), §6. */ ?>
<form method="post" action="<?= e(route('newsletter.subscribe')) ?>" class="df-newsletter__form" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="locale" value="<?= e(locale()) ?>">
    <div class="df-honeypot" aria-hidden="true"><label for="nl-website">Website</label><input type="text" id="nl-website" name="website" tabindex="-1" autocomplete="off" value=""></div>
    <label class="visually-hidden" for="newsletter_email"><?= e(__('home.newsletter.placeholder')) ?></label>
    <div class="input-group input-group-lg">
        <input type="email" class="form-control<?= error_for('newsletter_email') !== null ? ' is-invalid' : '' ?>" id="newsletter_email" name="newsletter_email"
               placeholder="<?= e(__('home.newsletter.placeholder')) ?>" value="<?= e(old('newsletter_email')) ?>" required autocomplete="email" inputmode="email" dir="ltr">
        <button class="btn btn-gold" type="submit"><i class="bi bi-send" aria-hidden="true"></i> <?= e(__('home.newsletter.submit')) ?></button>
    </div>
    <?= $this->partial('partials/field-error', ['name' => 'newsletter_email']) ?>
</form>
