<?php /** Barre supérieure (vert nuit) : dates grégorienne et hégirienne, sélecteur de langue. */ ?>
<div class="df-topbar">
    <div class="container d-flex flex-wrap align-items-center justify-content-between gap-2">
        <p class="df-topbar__dates mb-0">
            <i class="bi bi-calendar3" aria-hidden="true"></i>
            <time datetime="<?= e(date('Y-m-d')) ?>"><?= e(gregorian_date()) ?></time>
            <span class="df-topbar__sep" aria-hidden="true">·</span>
            <span><?= e(hijri_date()) ?></span>
        </p>
        <?= $this->partial('partials/lang-switcher') ?>
    </div>
</div>
