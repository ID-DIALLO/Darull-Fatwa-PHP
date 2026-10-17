<?php
/**
 * Page CMS (À propos, CGU, confidentialité, FAQ, charte du forum). Le contenu HTML est purifié à l'enregistrement.
 *
 * @var \App\Core\View $this
 * @var array<string, mixed> $page
 * @var string|null $fallbackLocale
 */
$this->extend('layouts/front');
$this->section('title', $page['meta_title'] ?: $page['title']);
$this->section('description', $page['meta_description'] ?: excerpt((string) $page['content'], 160));
?>
<?php $this->section('content'); ?>
<?= $this->partial('partials/breadcrumb', ['items' => [['label' => $page['title']]]]) ?>
<?= $this->partial('partials/content-fallback', ['fallbackLocale' => $fallbackLocale]) ?>
<article class="df-section">
    <div class="container">
        <header class="df-page__header">
            <h1 class="df-title"><?= e($page['title']) ?></h1>
            <?php if (!empty($page['updated_at'])): ?><p class="text-secondary small"><?= e(__('front.pages.updated', ['date' => format_date((string) $page['updated_at'])])) ?></p><?php endif ?>
        </header>
        <div class="df-prose df-measure-wide"><?= $page['content'] ?></div>
    </div>
</article>
<?php $this->endSection(); ?>
