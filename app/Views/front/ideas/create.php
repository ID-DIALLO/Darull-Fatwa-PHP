<?php
/** Proposer une idée. @var \App\Core\View $this */
$this->extend('layouts/front');
$this->section('title', e(__('ideas.propose')));
$this->section('robots', 'noindex, nofollow');
?>
<?php $this->section('content'); ?>
<?= $this->partial('partials/breadcrumb', ['items' => [['label' => __('ideas.title'), 'url' => route('ideas.index')], ['label' => __('ideas.propose')]]]) ?>
<section class="df-section">
    <div class="container">
        <div class="df-measure-wide mx-auto">
            <h1 class="df-title"><?= e(__('ideas.propose')) ?></h1>
            <p class="df-lead"><?= e(__('ideas.propose_intro')) ?></p>
            <form method="post" action="<?= e(route('ideas.store')) ?>" class="df-card" novalidate>
                <?= csrf_field() ?>
                <div class="mb-3"><label class="form-label" for="category"><?= e(__('ideas.form.category')) ?></label><select class="form-select" id="category" name="category"><?php foreach (\App\Services\IdeaService::CATEGORIES as $c): ?><option value="<?= e($c) ?>" <?= old('category') === $c ? 'selected' : '' ?>><?= e(__('ideas.category.' . $c)) ?></option><?php endforeach ?></select></div>
                <div class="mb-3"><label class="form-label" for="title"><?= e(__('ideas.form.title')) ?></label><input type="text" class="form-control<?= error_for('title') ? ' is-invalid' : '' ?>" id="title" name="title" required minlength="5" maxlength="200" value="<?= e(old('title')) ?>" dir="auto"><?= $this->partial('partials/field-error', ['name' => 'title']) ?></div>
                <div class="mb-3"><label class="form-label" for="description"><?= e(__('ideas.form.description')) ?></label><textarea class="form-control<?= error_for('description') ? ' is-invalid' : '' ?>" id="description" name="description" rows="7" required minlength="20" maxlength="5000" dir="auto"><?= e(old('description')) ?></textarea><?= $this->partial('partials/field-error', ['name' => 'description']) ?><div class="form-text"><?= e(__('ideas.form.description_help')) ?></div></div>
                <div class="d-flex gap-2"><button type="submit" class="btn btn-primary"><i class="bi bi-send" aria-hidden="true"></i> <?= e(__('ideas.form.submit')) ?></button><a class="btn btn-outline-secondary" href="<?= e(route('ideas.index')) ?>"><?= e(__('common.cancel')) ?></a></div>
            </form>
        </div>
    </div>
</section>
<?php $this->endSection(); ?>
