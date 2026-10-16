<?php /** Pied de page (vert nuit) avec le logo blanc. Les 4 colonnes complètes arrivent en Phase 2. */ ?>
<footer class="df-footer">
    <div class="container">
        <div class="df-footer__top">
            <img class="df-footer__logo" src="<?= e(asset('assets/img/logo/darul-fatwa-logo-blanc.svg')) ?>" alt="Darul Fatwa – دار الفتوى" width="220" height="66" loading="lazy">
            <p class="df-footer__slogan mb-0"><?= e(__('common.site.slogan')) ?></p>
        </div>
        <div class="df-footer__bottom">
            <p class="mb-0"><?= e(__('common.footer.rights', ['year' => date('Y')])) ?></p>
            <?= $this->partial('partials/lang-switcher') ?>
        </div>
    </div>
</footer>
