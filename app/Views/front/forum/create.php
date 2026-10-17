<?php
/**
 * Nouveau sujet (texte + note vocale facultative, charte à accepter à la première participation).
 *
 * @var \App\Core\View $this
 * @var list<array<string, mixed>> $categories
 * @var string $selected
 * @var bool $rulesAccepted
 * @var bool $moderated
 * @var int $maxSeconds
 */
$this->extend('layouts/front');
$this->section('title', e(__('forum.new_topic')));
$this->section('robots', 'noindex, nofollow');
$this->section('scripts'); ?><script src="<?= e(asset('assets/js/voice-recorder.js')) ?>" type="module"></script><?php $this->endSection();
?>
<?php $this->section('content'); ?>
<?= $this->partial('partials/breadcrumb', ['items' => [['label' => __('forum.title'), 'url' => route('forum.index')], ['label' => __('forum.new_topic')]]]) ?>
<section class="df-section">
    <div class="container">
        <div class="df-measure-wide mx-auto">
            <h1 class="df-title"><?= e(__('forum.new_topic')) ?></h1>
            <?php if ($moderated): ?><div class="alert alert-info"><i class="bi bi-shield-check" aria-hidden="true"></i> <?= e(__('forum.moderation_notice', ['count' => \App\Services\ForumService::moderatedFirstPosts()])) ?></div><?php endif ?>
            <form method="post" action="<?= e(route('forum.store')) ?>" class="df-card" novalidate>
                <?= csrf_field() ?>
                <div class="row g-3">
                    <div class="col-12 col-md-7"><label class="form-label" for="category_id"><?= e(__('forum.form.category')) ?></label><select class="form-select<?= error_for('category_id') ? ' is-invalid' : '' ?>" id="category_id" name="category_id" required><?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (string) old('category_id', $selected) === (string) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach ?></select><?= $this->partial('partials/field-error', ['name' => 'category_id']) ?></div>
                    <div class="col-12 col-md-5"><label class="form-label" for="language"><?= e(__('forum.form.language')) ?></label><select class="form-select" id="language" name="language"><?php foreach (['fr', 'ar', 'en', 'bm', 'other'] as $l): ?><option value="<?= e($l) ?>" <?= old('language', locale()) === $l ? 'selected' : '' ?>><?= e(__('questions.languages.' . $l)) ?></option><?php endforeach ?></select></div>
                    <div class="col-12"><label class="form-label" for="title"><?= e(__('forum.form.title')) ?></label><input type="text" class="form-control<?= error_for('title') ? ' is-invalid' : '' ?>" id="title" name="title" required minlength="5" maxlength="200" value="<?= e(old('title')) ?>" dir="auto"><?= $this->partial('partials/field-error', ['name' => 'title']) ?></div>
                    <div class="col-12"><label class="form-label" for="body"><?= e(__('forum.form.body')) ?></label><textarea class="form-control<?= error_for('body') ? ' is-invalid' : '' ?>" id="body" name="body" rows="8" required minlength="10" dir="auto"><?= e(old('body')) ?></textarea><?= $this->partial('partials/field-error', ['name' => 'body']) ?></div>
                    <div class="col-12"><p class="form-label mb-1"><?= e(__('forum.form.voice')) ?> <span class="text-secondary">(<?= e(__('common.optional')) ?>)</span></p><?= $this->partial('partials/voice-recorder', ['name' => 'media_uuid', 'maxSeconds' => min(180, $maxSeconds)]) ?></div>
                    <?php if (!$rulesAccepted): ?>
                    <div class="col-12"><div class="form-check"><input class="form-check-input<?= error_for('accept_rules') ? ' is-invalid' : '' ?>" type="checkbox" id="accept_rules" name="accept_rules" value="1" required><label class="form-check-label" for="accept_rules"><?= e(__('forum.form.accept_rules')) ?> <a href="<?= e(route('pages.show', ['slug' => __('forum.rules_slug')])) ?>" target="_blank" rel="noopener"><?= e(__('forum.rules_link')) ?></a></label><?= $this->partial('partials/field-error', ['name' => 'accept_rules']) ?></div></div>
                    <?php endif ?>
                </div>
                <div class="d-flex gap-2 mt-3"><button type="submit" class="btn btn-primary"><i class="bi bi-send" aria-hidden="true"></i> <?= e(__('forum.form.submit')) ?></button><a class="btn btn-outline-secondary" href="<?= e(route('forum.index')) ?>"><?= e(__('common.cancel')) ?></a></div>
            </form>
        </div>
    </div>
</section>
<?php $this->endSection(); ?>
