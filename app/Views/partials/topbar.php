<?php
/** Barre supérieure (vert nuit) : dates grégorienne et hégirienne, téléphone, e-mail, réseaux sociaux, langues (§6). */
$phone = setting('contact_phone');
$email = setting('contact_email');
$phone = $phone !== null && !str_contains($phone, 'X') ? $phone : null;
$socials = [];
foreach (['facebook' => 'facebook', 'youtube' => 'youtube', 'instagram' => 'instagram', 'telegram' => 'telegram', 'x' => 'twitter-x', 'tiktok' => 'tiktok'] as $key => $icon) {
    $url = setting('social_' . $key);
    if ($url !== null && $url !== '') {
        $socials[$key] = ['url' => $url, 'icon' => $icon];
    }
}
?>
<div class="df-topbar">
    <div class="container d-flex flex-wrap align-items-center justify-content-between gap-2">
        <p class="df-topbar__dates mb-0">
            <i class="bi bi-calendar3" aria-hidden="true"></i>
            <time datetime="<?= e(date('Y-m-d')) ?>"><?= e(gregorian_date()) ?></time>
            <span class="df-topbar__sep" aria-hidden="true">·</span>
            <span><?= e(hijri_date()) ?></span>
        </p>
        <div class="d-flex align-items-center gap-3">
            <ul class="df-topbar__contact list-inline mb-0 d-none d-md-flex">
                <?php if ($phone !== null): ?>
                <li class="list-inline-item"><a href="tel:<?= e(preg_replace('/\s+/', '', $phone)) ?>" class="ltr"><i class="bi bi-telephone" aria-hidden="true"></i> <?= e(\App\Services\PhoneNumber::format($phone)) ?></a></li>
                <?php endif ?>
                <?php if ($email !== null && $email !== ''): ?>
                <li class="list-inline-item"><a href="mailto:<?= e($email) ?>" class="ltr"><i class="bi bi-envelope" aria-hidden="true"></i> <?= e($email) ?></a></li>
                <?php endif ?>
                <?php foreach ($socials as $key => $social): ?>
                <li class="list-inline-item"><a href="<?= e($social['url']) ?>" target="_blank" rel="noopener" aria-label="<?= e(__('front.social.' . $key)) ?>"><i class="bi bi-<?= e($social['icon']) ?>" aria-hidden="true"></i></a></li>
                <?php endforeach ?>
            </ul>
            <?= $this->partial('partials/lang-switcher') ?>
        </div>
    </div>
</div>
