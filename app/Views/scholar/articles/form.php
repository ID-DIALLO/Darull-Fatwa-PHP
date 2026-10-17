<?php
/**
 * Rédaction d'un article par un professeur (brouillon ou soumission à la validation).
 *
 * @var \App\Core\View $this
 * @var array<string, mixed>|null $article
 * @var list<array<string, mixed>> $rubriques
 */
$this->extend('layouts/scholar');
$editing = $article !== null;
$this->section('title', $editing ? __('blog.manage.edit') : __('blog.manage.new'));
$this->section('subtitle', __('blog.manage.scholar_intro'));
$this->section('head'); ?><link rel="stylesheet" href="<?= e(asset('vendor/quill/quill.snow.css')) ?>"><link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>"><?php $this->endSection();
$this->section('scripts'); ?><script src="<?= e(asset('vendor/quill/quill.js')) ?>" defer></script><script src="<?= e(asset('assets/js/voice-recorder.js')) ?>" type="module"></script><script src="<?= e(asset('assets/js/admin.js')) ?>" type="module"></script><?php $this->endSection();
?>
<?php $this->section('content'); ?>

<?= $this->partial('admin/articles/_form', ['article' => $article, 'rubriques' => $rubriques, 'authors' => [], 'action' => $editing ? route('scholar.articles.update', ['id' => $article['id']]) : route('scholar.articles.store'), 'cancel' => route('scholar.articles'), 'byScholar' => true]) ?>

<?php $this->endSection(); ?>
