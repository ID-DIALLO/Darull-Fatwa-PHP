<?php /** En-tête : logo horizontal (le menu complet arrive en Phase 2). */ ?>
<header class="df-header">
    <div class="container d-flex align-items-center justify-content-between gap-3">
        <a class="df-header__brand" href="<?= e(route('home')) ?>" aria-label="<?= e(__('common.site.name')) ?> — <?= e(__('nav.home')) ?>">
            <img src="<?= e(asset('assets/img/logo/darul-fatwa-logo.svg')) ?>" alt="Darul Fatwa – دار الفتوى – <?= e(__('common.site.slogan')) ?>" width="194" height="58">
        </a>
    </div>
</header>
