<?php
/**
 * Phase 0 — page de test du noyau : logo, dates grégorienne et hégirienne, RTL sur /ar, polices.
 * Sera remplacée par la vraie page d'accueil en Phase 2.
 *
 * @var \App\Core\View $this
 * @var list<array{label: string, ok: bool, detail: string}> $checks
 */
$this->extend('layouts/front');
$rtl = text_dir() === 'rtl';
?>

<section class="df-section df-section--cream df-pattern">
    <div class="container text-center py-4 py-md-5">
        <span class="badge rounded-pill df-badge-gold mb-3"><?= e(__('common.test.badge')) ?></span>
        <img class="df-test-logo mx-auto d-block mb-4" src="<?= e(asset('assets/img/logo/darul-fatwa-logo-vertical.svg')) ?>" alt="Darul Fatwa – دار الفتوى" width="300" height="271">
        <h1 class="df-title mb-2"><?= e(__('common.test.welcome')) ?></h1>
        <p class="df-lead mb-0"><?= e(__('common.site.slogan')) ?></p>
        <p class="text-secondary mt-2 mb-0 mx-auto df-measure"><?= e(__('common.test.intro')) ?></p>
    </div>
</section>

<section class="df-section">
    <div class="container">
        <div class="row g-4">
            <div class="col-12 col-md-6">
                <article class="df-card h-100">
                    <h2 class="df-card__title"><i class="bi bi-calendar-heart" aria-hidden="true"></i> <?= e(__('common.test.today')) ?></h2>
                    <dl class="df-dl mb-0">
                        <dt><?= e(__('common.date.gregorian')) ?></dt>
                        <dd data-testid="gregorian-date"><?= e(gregorian_date()) ?></dd>
                        <dt><?= e(__('common.date.hijri')) ?></dt>
                        <dd class="df-hijri" data-testid="hijri-date"><?= e(hijri_date()) ?></dd>
                    </dl>
                </article>
            </div>
            <div class="col-12 col-md-6">
                <article class="df-card h-100">
                    <h2 class="df-card__title"><i class="bi bi-translate" aria-hidden="true"></i> <?= e(__('common.test.direction')) ?></h2>
                    <p class="mb-2">
                        <strong data-testid="direction"><?= e(text_dir()) ?></strong> —
                        <?= e($rtl ? __('common.test.direction_rtl') : __('common.test.direction_ltr')) ?>
                        <i class="bi bi-arrow-right icon-dir" aria-hidden="true"></i>
                    </p>
                    <p class="mb-0 text-secondary">
                        <?= e(__('common.test.fonts')) ?> : <?= e(__('common.test.fonts_list')) ?>
                    </p>
                </article>
            </div>
            <div class="col-12">
                <figure class="df-verse mb-0">
                    <figcaption class="df-verse__label"><?= e(__('common.test.verse_label')) ?></figcaption>
                    <blockquote class="mb-2">
                        <p lang="ar" dir="rtl" class="df-verse__text">﴿ وَقُل رَّبِّ زِدْنِي عِلْمًا ﴾</p>
                    </blockquote>
                    <?php if (__('common.test.verse_translation') !== ''): ?>
                    <p class="df-verse__translation mb-1"><?= e(__('common.test.verse_translation')) ?></p>
                    <?php endif ?>
                    <p class="df-verse__ref mb-0"><?= e(__('common.test.verse_ref')) ?></p>
                </figure>
            </div>
        </div>
    </div>
</section>

<section class="df-section df-section--cream">
    <div class="container">
        <h2 class="df-section__title"><?= e(__('common.test.logos')) ?></h2>
        <div class="row g-3 align-items-stretch">
            <div class="col-12 col-lg-6">
                <div class="df-logo-tile">
                    <img src="<?= e(asset('assets/img/logo/darul-fatwa-logo.svg')) ?>" alt="Logo horizontal" width="320" height="96" loading="lazy">
                </div>
            </div>
            <div class="col-12 col-lg-6">
                <div class="df-logo-tile df-logo-tile--dark">
                    <img src="<?= e(asset('assets/img/logo/darul-fatwa-logo-blanc.svg')) ?>" alt="Logo blanc" width="320" height="96" loading="lazy">
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="df-logo-tile">
                    <img src="<?= e(asset('assets/img/logo/darul-fatwa-logo-vertical.svg')) ?>" alt="Logo vertical" width="160" height="145" loading="lazy">
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="df-logo-tile">
                    <img src="<?= e(asset('assets/img/logo/darul-fatwa-icone.svg')) ?>" alt="Icône" width="120" height="120" loading="lazy">
                </div>
            </div>
            <div class="col-12 col-lg-6">
                <div class="df-card h-100">
                    <h3 class="df-card__title h5"><?= e(__('common.test.buttons')) ?></h3>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-primary"><?= e(__('common.test.btn_primary')) ?></button>
                        <button type="button" class="btn btn-gold"><i class="bi bi-mic-fill" aria-hidden="true"></i> <?= e(__('common.test.btn_ask')) ?></button>
                        <button type="button" class="btn btn-outline-primary"><?= e(__('nav.blog')) ?></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if ($checks !== []): ?>
<section class="df-section">
    <div class="container">
        <h2 class="df-section__title"><?= e(__('common.test.checks')) ?></h2>
        <div class="table-responsive df-card p-0">
            <table class="table table-sm align-middle mb-0">
                <tbody>
                <?php foreach ($checks as $check): ?>
                    <tr>
                        <th scope="row" class="ps-3"><?= e($check['label']) ?></th>
                        <td>
                            <span class="df-status df-status--<?= $check['ok'] ? 'success' : 'danger' ?>">
                                <i class="bi bi-<?= $check['ok'] ? 'check-circle-fill' : 'x-circle-fill' ?>" aria-hidden="true"></i>
                                <?= e($check['ok'] ? __('common.yes') : __('common.no')) ?>
                            </span>
                        </td>
                        <td class="text-secondary small pe-3"><?= e($check['detail']) ?></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?php endif ?>
