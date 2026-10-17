<?php
/**
 * Hero — bannière défilante Swiper (§6, section 3) : fondu, 6 s, pause au survol, flèches + puces,
 * première image prioritaire, RTL, respect de prefers-reduced-motion (géré dans hero-slider.js).
 *
 * @var list<array<string, mixed>> $banners
 */
use App\Models\Banner;

if ($banners === []) {
    return;
}
$single = count($banners) === 1;
?>
<section class="df-hero" aria-roledescription="carousel" aria-label="<?= e(__('home.hero.label')) ?>">
    <div class="swiper df-hero__swiper" data-hero-slider dir="<?= e(text_dir()) ?>">
        <div class="swiper-wrapper">
            <?php foreach ($banners as $index => $banner):
                $link1 = Banner::resolveLink($banner['link_url'] ?? null);
                $link2 = Banner::resolveLink($banner['link2_url'] ?? null);
                // Pour l'équipe, le bouton « Poser une question » devient l'accès à son espace
                $toSpace = !can_ask() && Banner::isAskLink($banner['link_url'] ?? null);
                $label1 = $toSpace ? __(\App\Core\Auth::hasRole('scholar') ? 'nav.scholar_space' : 'nav.admin') : (string) ($banner['button_label'] ?? '');
                $icon1 = $toSpace ? 'mortarboard' : 'mic-fill';
                $mobile = $banner['image_mobile'] ?: $banner['image_desktop'];
            ?>
            <article class="swiper-slide df-hero__slide" role="group" aria-roledescription="slide" aria-label="<?= $index + 1 ?> / <?= count($banners) ?>">
                <picture class="df-hero__picture">
                    <source media="(max-width: 767.98px)" srcset="<?= e(asset((string) $mobile)) ?>">
                    <img src="<?= e(asset((string) $banner['image_desktop'])) ?>" alt="<?= e($banner['image_alt'] ?? '') ?>" width="1920" height="800"
                         <?= $index === 0 ? 'fetchpriority="high" decoding="async"' : 'loading="lazy" decoding="async"' ?>>
                </picture>
                <div class="df-hero__overlay"></div>
                <div class="container df-hero__content">
                    <div class="df-hero__text">
                        <?php if ($index === 0): ?><h1 class="df-hero__title"><?= e($banner['title']) ?></h1><?php else: ?><p class="df-hero__title h1"><?= e($banner['title']) ?></p><?php endif ?>
                        <?php if (!empty($banner['subtitle'])): ?><p class="df-hero__subtitle"><?= e($banner['subtitle']) ?></p><?php endif ?>
                        <div class="df-hero__actions">
                            <?php if ($link1 !== null && $label1 !== ''): ?>
                            <a class="btn btn-gold btn-lg" href="<?= e($link1) ?>"><i class="bi bi-<?= e($icon1) ?>" aria-hidden="true"></i> <?= e($label1) ?></a>
                            <?php endif ?>
                            <?php if ($link2 !== null && !empty($banner['button2_label'])): ?>
                            <a class="btn btn-outline-light btn-lg" href="<?= e($link2) ?>"><?= e($banner['button2_label']) ?></a>
                            <?php endif ?>
                        </div>
                    </div>
                </div>
            </article>
            <?php endforeach ?>
        </div>
        <?php if (!$single): ?>
        <div class="df-hero__controls container">
            <button type="button" class="df-hero__arrow df-hero__prev" aria-label="<?= e(__('home.hero.prev')) ?>"><i class="bi bi-chevron-left icon-dir" aria-hidden="true"></i></button>
            <div class="swiper-pagination df-hero__dots"></div>
            <button type="button" class="df-hero__arrow df-hero__next" aria-label="<?= e(__('home.hero.next')) ?>"><i class="bi bi-chevron-right icon-dir" aria-hidden="true"></i></button>
            <button type="button" class="df-hero__arrow df-hero__toggle" data-hero-toggle aria-pressed="false" aria-label="<?= e(__('home.hero.pause')) ?>" data-label-pause="<?= e(__('home.hero.pause')) ?>" data-label-play="<?= e(__('home.hero.play')) ?>"><i class="bi bi-pause-fill" aria-hidden="true"></i></button>
        </div>
        <?php endif ?>
    </div>
</section>
