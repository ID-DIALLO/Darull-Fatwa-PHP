<?php
/**
 * Gabarit principal du site public.
 * Sections : title (texte), description (texte), content, robots (texte), head (HTML), scripts (HTML),
 * og_image (URL), body_class (texte).
 *
 * @var \App\Core\View $this
 * @var string $locale
 * @var string $dir
 */
$locale ??= locale();
$dir ??= text_dir($locale);
$noindex = $this->hasSection('robots') && str_contains($this->yield('robots'), 'noindex');
// Pas de canonical ni de hreflang sur les pages d'erreur ou non indexables
$alternates = $noindex ? [] : alternate_urls();
$title = $this->hasSection('title') ? $this->yield('title') . ' | ' . e(__('common.site.name')) : e(__('common.site.title'));
$description = $this->hasSection('description') ? $this->yield('description') : e(__('common.site.description'));
$ogImage = $this->hasSection('og_image') ? $this->yield('og_image') : e(url('/assets/img/logo/darul-fatwa-logo-vertical.png'));
$intlLocales = ['fr' => 'fr_FR', 'ar' => 'ar_AR', 'en' => 'en_GB'];
$whatsapp = whatsapp_link(__('front.whatsapp.message'));
?>
<!doctype html>
<html lang="<?= e($locale) ?>" dir="<?= e($dir) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= $title ?></title>
    <meta name="description" content="<?= $description ?>">
    <?php if ($this->hasSection('robots')): ?><meta name="robots" content="<?= $this->yield('robots') ?>"><?php endif ?>

    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="theme-color" content="#0B5D45">
    <meta name="application-name" content="<?= e(__('common.site.name')) ?>">
    <meta name="apple-mobile-web-app-title" content="<?= e(__('common.site.name')) ?>">

    <?php if (isset($alternates[$locale])): ?>
    <link rel="canonical" href="<?= e(url($alternates[$locale])) ?>">
    <?php endif ?>
    <?php foreach ($alternates as $code => $path): ?>
    <link rel="alternate" hreflang="<?= e($code) ?>" href="<?= e(url($path)) ?>">
    <?php endforeach ?>
    <?php if (isset($alternates['fr'])): ?>
    <link rel="alternate" hreflang="x-default" href="<?= e(url($alternates['fr'])) ?>">
    <?php endif ?>

    <meta property="og:site_name" content="<?= e(__('common.site.name')) ?>">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= $title ?>">
    <meta property="og:description" content="<?= $description ?>">
    <meta property="og:locale" content="<?= e($intlLocales[$locale] ?? 'fr_FR') ?>">
    <meta property="og:image" content="<?= $ogImage ?>">
    <?php if (isset($alternates[$locale])): ?><meta property="og:url" content="<?= e(url($alternates[$locale])) ?>"><?php endif ?>
    <meta name="twitter:card" content="summary_large_image">

    <link rel="icon" href="/assets/img/favicons/favicon.ico" sizes="32x32">
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('assets/img/logo/darul-fatwa-icone.svg')) ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= e(asset('assets/img/favicons/favicon-32x32.png')) ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= e(asset('assets/img/favicons/favicon-16x16.png')) ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= e(asset('assets/img/favicons/apple-touch-icon.png')) ?>">
    <link rel="manifest" href="/manifest.webmanifest">

    <link rel="stylesheet" href="<?= e(asset($dir === 'rtl' ? 'vendor/bootstrap/css/bootstrap.rtl.min.css' : 'vendor/bootstrap/css/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/fonts.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/variables.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
    <?php if ($dir === 'rtl'): ?>
    <link rel="stylesheet" href="<?= e(asset('assets/css/rtl.css')) ?>">
    <?php endif ?>
    <?= $this->yield('head') ?>
</head>
<body class="df-body df-locale-<?= e($locale) ?> <?= $this->yield('body_class') ?>">
    <a class="visually-hidden-focusable df-skip-link" href="#main"><?= e(__('common.skip_to_content')) ?></a>

    <?= $this->partial('partials/topbar') ?>
    <?= $this->partial('partials/header') ?>

    <main id="main" tabindex="-1">
        <?= $this->partial('partials/flash') ?>
        <?= $this->yield('content') ?>
    </main>

    <?= $this->partial('partials/footer') ?>

    <?php if ($whatsapp !== null): ?>
    <a class="df-float df-float--whatsapp" href="<?= e($whatsapp) ?>" target="_blank" rel="noopener" aria-label="<?= e(__('front.whatsapp.float')) ?>" title="<?= e(__('front.whatsapp.float')) ?>">
        <i class="bi bi-whatsapp" aria-hidden="true"></i>
    </a>
    <?php endif ?>
    <?php if (route_exists('questions.create') && $this->yield('hide_float_mic') === ''): ?>
    <a class="df-float df-float--mic d-lg-none" href="<?= e(route('questions.create', ['mode' => 'voice'])) ?>" aria-label="<?= e(__('nav.ask')) ?>" title="<?= e(__('nav.ask')) ?>">
        <i class="bi bi-mic-fill" aria-hidden="true"></i>
    </a>
    <?php endif ?>
    <button type="button" class="df-float df-float--top" data-back-to-top aria-label="<?= e(__('nav.back_to_top')) ?>" title="<?= e(__('nav.back_to_top')) ?>" hidden>
        <i class="bi bi-arrow-up" aria-hidden="true"></i>
    </button>

    <script src="<?= e(asset('vendor/bootstrap/js/bootstrap.bundle.min.js')) ?>" defer></script>
    <script src="<?= e(asset('assets/js/app.js')) ?>" type="module"></script>
    <?= $this->yield('scripts') ?>
</body>
</html>
