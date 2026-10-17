<?php
/**
 * Mes articles (professeur).
 *
 * @var \App\Core\View $this
 * @var array{data: list<array<string, mixed>>, total: int, page: int, last_page: int} $result
 */
$this->extend('layouts/scholar');
$this->section('title', __('blog.manage.my_articles'));
$this->section('subtitle', __('blog.manage.my_intro'));
?>
<?php $this->section('content'); ?>
<p><a class="btn btn-primary" href="<?= e(route('scholar.articles.create')) ?>"><i class="bi bi-plus-lg" aria-hidden="true"></i> <?= e(__('blog.manage.new')) ?></a></p>
<?php if ($result['data'] === []): ?>
<div class="df-empty"><i class="bi bi-journal-text" aria-hidden="true"></i><p class="mb-0"><?= e(__('blog.manage.empty')) ?></p></div>
<?php else: ?>
<ul class="df-question-list">
    <?php foreach ($result['data'] as $a): ?>
    <li class="df-question-item">
        <a class="df-question-item__link" href="<?= e(route('scholar.articles.edit', ['id' => $a['id']])) ?>">
            <div class="df-question-item__head"><span class="df-badge df-badge--<?= ['draft' => 'secondary', 'submitted' => 'warning', 'scheduled' => 'info', 'published' => 'success', 'archived' => 'secondary'][$a['status']] ?>"><?= e(__('blog.status.' . $a['status'])) ?></span><small class="text-secondary"><?= e(format_date($a['published_at'] ?: $a['created_at'])) ?></small></div>
            <p class="df-question-item__title" dir="auto"><?= e($a['title'] ?? __('blog.manage.untitled')) ?></p>
            <p class="df-question-item__meta"><span><?= e(__('admin.common.languages_filled', ['list' => strtoupper((string) ($a['locales'] ?? '—'))])) ?></span><span><i class="bi bi-eye" aria-hidden="true"></i> <?= (int) $a['views'] ?></span></p>
        </a>
    </li>
    <?php endforeach ?>
</ul>
<?= $this->partial('partials/pagination', ['result' => $result]) ?>
<?php endif ?>
<?php $this->endSection(); ?>
