<?php
/**
 * Page hors-ligne (§13.4) : servie par le service worker quand le réseau est indisponible.
 * Autonome (styles en ligne, logo mis en cache) et trilingue, car la langue du visiteur n'est pas connue hors ligne.
 *
 * @var \App\Core\View $this
 */
$locales = ['fr', 'ar', 'en'];
?>
<!doctype html>
<html lang="<?= e(locale()) ?>" dir="<?= e(text_dir(locale())) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(__('common.offline_page.title')) ?> | <?= e(__('common.site.name')) ?></title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0B5D45">
    <link rel="icon" type="image/svg+xml" href="/assets/img/logo/darul-fatwa-icone.svg">
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #F7F1E1; color: #0B3D2E; font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Noto Sans Arabic", sans-serif; padding: 1rem; box-sizing: border-box; }
        .df-offline { max-width: 560px; width: 100%; background: #fff; border-radius: 16px; padding: 2rem 1.5rem; box-shadow: 0 10px 30px rgba(11, 61, 46, .08); text-align: center; }
        .df-offline img { width: 200px; max-width: 70%; height: auto; margin-bottom: 1rem; }
        .df-offline section { padding: .75rem 0; border-top: 1px solid #EADFC3; }
        .df-offline section:first-of-type { border-top: 0; }
        .df-offline h1, .df-offline h2 { font-size: 1.25rem; margin: 0 0 .25rem; color: #0B5D45; }
        .df-offline p { margin: 0 0 .5rem; line-height: 1.5; }
        .df-offline button { font: inherit; font-weight: 600; color: #0B3D2E; background: #C9A227; border: 0; border-radius: 999px; padding: .6rem 1.4rem; cursor: pointer; min-height: 44px; }
        .df-offline button:focus-visible { outline: 3px solid #0B5D45; outline-offset: 2px; }
    </style>
</head>
<body>
    <main class="df-offline">
        <img src="/assets/img/logo/darul-fatwa-logo.svg" alt="<?= e(__('common.site.name')) ?>" width="200" height="60">
        <?php foreach ($locales as $i => $code): ?>
        <section lang="<?= e($code) ?>" dir="<?= e(text_dir($code)) ?>">
            <?php if ($i === 0): ?><h1><?= e(\App\Core\Lang::get('common.offline_page.title', [], $code)) ?></h1><?php else: ?><h2><?= e(\App\Core\Lang::get('common.offline_page.title', [], $code)) ?></h2><?php endif ?>
            <p><?= e(\App\Core\Lang::get('common.offline_page.message', [], $code)) ?></p>
            <button type="button" data-reload><?= e(\App\Core\Lang::get('common.offline_page.retry', [], $code)) ?></button>
        </section>
        <?php endforeach ?>
    </main>
    <script src="/assets/js/offline.js"></script>
</body>
</html>
