<?php
/**
 * Création / modification d'un article (administration) : multilingue, statut, planification, auteur,
 * rubrique, tags, image à la une, audio, mise en avant, commentaires, SEO.
 *
 * @var \App\Core\View $this
 * @var array<string, mixed>|null $article
 * @var list<array<string, mixed>> $rubriques
 * @var list<array<string, mixed>> $authors
 */
$this->extend('layouts/admin');
$editing = $article !== null;
$t = $article['translations'] ?? [];
$this->section('title', $editing ? e(__('admin.articles.edit_title')) : e(__('admin.articles.create')));
if ($editing) {
    $this->section('subtitle', e(__('blog.status.' . $article['status'])) . ($article['author_name'] ? ' · ' . e($article['author_name']) : ''));
}
$this->section('head'); ?><link rel="stylesheet" href="<?= e(asset('vendor/quill/quill.snow.css')) ?>"><?php $this->endSection();
$this->section('scripts'); ?><script src="<?= e(asset('vendor/quill/quill.js')) ?>" defer></script><script src="<?= e(asset('assets/js/voice-recorder.js')) ?>" type="module"></script><?php $this->endSection();
$this->section('actions');
if ($editing && $article['status'] === 'published') {
    foreach ($t as $code => $row) {
        echo '<a class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener" href="' . e(route('blog.show', ['slug' => $row['slug']], $code)) . '"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> ' . e(strtoupper($code)) . '</a>';
    }
}
$this->endSection();
?>
<?php $this->section('content'); ?>
<?= $this->partial('admin/articles/_form', ['article' => $article, 'rubriques' => $rubriques, 'authors' => $authors, 'action' => $editing ? route('admin.articles.update', ['id' => $article['id']]) : route('admin.articles.store'), 'cancel' => route('admin.articles'), 'byScholar' => false]) ?>
<?php $this->endSection(); ?>
