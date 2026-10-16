<?php
/**
 * Page d'erreur traduite : 403, 404, 405, 419, 429, 500, 503.
 *
 * @var \App\Core\View $this
 * @var int $status
 * @var \Throwable|null $exception Détails techniques (mode debug uniquement)
 */
$this->extend('layouts/front');
$this->section('title', __('common.errors.' . $status . '.title'));
$this->section('robots', 'noindex, nofollow');
$icons = [403 => 'shield-lock', 404 => 'signpost-split', 405 => 'slash-circle', 419 => 'hourglass-split', 429 => 'stopwatch', 500 => 'tools', 503 => 'cone-striped'];
?>
<section class="df-section df-pattern">
    <div class="container py-4 py-md-5 text-center df-error" data-status="<?= (int) $status ?>">
        <i class="bi bi-<?= e($icons[$status] ?? 'exclamation-circle') ?> df-error__icon" aria-hidden="true"></i>
        <p class="df-error__code mb-1"><?= e(__('common.errors.code', ['code' => $status])) ?></p>
        <h1 class="df-title mb-3"><?= e(__('common.errors.' . $status . '.title')) ?></h1>
        <p class="df-lead mx-auto df-measure"><?= e(__('common.errors.' . $status . '.message')) ?></p>
        <div class="d-flex flex-wrap justify-content-center gap-2 mt-4">
            <a class="btn btn-primary" href="<?= e(route('home')) ?>"><i class="bi bi-house-door" aria-hidden="true"></i> <?= e(__('common.back_home')) ?></a>
            <?php if (in_array($status, [419, 429, 500, 503], true)): ?>
            <a class="btn btn-outline-primary" href="" data-reload><i class="bi bi-arrow-clockwise" aria-hidden="true"></i> <?= e(__('common.retry')) ?></a>
            <?php endif ?>
        </div>

        <?php if (isset($exception) && $exception !== null): ?>
        <details class="df-card text-start mt-5" dir="ltr" lang="en">
            <summary class="fw-semibold"><?= e(__('common.errors.debug')) ?></summary>
            <p class="mt-3 mb-1"><code><?= e($exception::class) ?></code> : <?= e($exception->getMessage()) ?></p>
            <p class="small text-secondary"><?= e($exception->getFile()) ?>:<?= (int) $exception->getLine() ?></p>
            <pre class="small mb-0"><?= e($exception->getTraceAsString()) ?></pre>
        </details>
        <?php endif ?>
    </div>
</section>
