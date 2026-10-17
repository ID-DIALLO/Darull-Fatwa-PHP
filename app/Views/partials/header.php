<?php /** En-tête : logo horizontal et accès au compte (le menu complet arrive en Phase 2). */
$user = auth();
?>
<header class="df-header">
    <div class="container d-flex align-items-center justify-content-between gap-3">
        <a class="df-header__brand" href="<?= e(route('home')) ?>" aria-label="<?= e(__('common.site.name')) ?> — <?= e(__('nav.home')) ?>">
            <img src="<?= e(asset('assets/img/logo/darul-fatwa-logo.svg')) ?>" alt="Darul Fatwa – دار الفتوى – <?= e(__('common.site.slogan')) ?>" width="194" height="58">
        </a>
        <nav class="df-header__account d-flex align-items-center gap-2" aria-label="<?= e(__('nav.my_account')) ?>">
            <?php if ($user !== null): ?>
            <a class="btn btn-outline-primary btn-sm" href="<?= e(route('account.dashboard')) ?>"><i class="bi bi-person-circle" aria-hidden="true"></i> <span class="d-none d-sm-inline"><?= e(__('nav.my_account')) ?></span></a>
            <form method="post" action="<?= e(route('auth.logout')) ?>" class="d-none d-md-block">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-link btn-sm text-decoration-none"><?= e(__('nav.logout')) ?></button>
            </form>
            <?php else: ?>
            <a class="btn btn-outline-primary btn-sm" href="<?= e(route('auth.login')) ?>"><i class="bi bi-box-arrow-in-right icon-dir" aria-hidden="true"></i> <?= e(__('nav.login')) ?></a>
            <a class="btn btn-gold btn-sm d-none d-sm-inline-flex" href="<?= e(route('auth.register')) ?>"><?= e(__('nav.register')) ?></a>
            <?php endif ?>
        </nav>
    </div>
</header>
