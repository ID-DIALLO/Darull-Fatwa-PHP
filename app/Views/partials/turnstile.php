<?php
/** Widget anti-robot Cloudflare Turnstile + champ piège (honeypot), §4.1. */
use App\Services\Turnstile;
?>
<div class="df-honeypot" aria-hidden="true">
    <label for="<?= e(Turnstile::HONEYPOT) ?>">Website</label>
    <input type="text" id="<?= e(Turnstile::HONEYPOT) ?>" name="<?= e(Turnstile::HONEYPOT) ?>" tabindex="-1" autocomplete="off" value="">
</div>
<?php if (Turnstile::enabled()): ?>
<div class="cf-turnstile mb-3" data-sitekey="<?= e(Turnstile::siteKey()) ?>" data-language="<?= e(locale()) ?>" data-theme="light"></div>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<?php endif ?>
