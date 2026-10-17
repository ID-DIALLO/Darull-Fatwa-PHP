<?php
/**
 * Enregistrer une question reçue sur WhatsApp au nom d'un membre (source « whatsapp »).
 *
 * @var \App\Core\View $this
 * @var list<array<string, mixed>> $rubriques
 * @var list<array<string, mixed>> $members
 */
$this->extend('layouts/admin');
$this->section('title', __('admin.questions.create.title'));
$this->section('subtitle', __('admin.questions.create.intro'));
$request = \App\Core\View::shared('request');
$term = $request instanceof \App\Core\Request ? (string) $request->query('member', '') : '';
?>
<?php $this->section('content'); ?>
<div class="row g-3">
    <div class="col-12 col-lg-4">
        <form method="get" action="<?= e(route('admin.questions.create')) ?>" class="df-card mb-3">
            <h2 class="df-card__title h6"><?= e(__('admin.questions.create.find_member')) ?></h2>
            <div class="input-group">
                <input type="search" class="form-control" name="member" value="<?= e($term) ?>" placeholder="<?= e(__('admin.questions.create.find_placeholder')) ?>" aria-label="<?= e(__('admin.questions.create.find_member')) ?>">
                <button class="btn btn-outline-primary" type="submit"><i class="bi bi-search" aria-hidden="true"></i></button>
            </div>
            <?php if ($term !== '' && $members === []): ?><p class="small text-secondary mt-2 mb-0"><?= e(__('admin.questions.create.no_member')) ?> <?php if (can('users.manage')): ?><a href="<?= e(route('admin.users.create')) ?>"><?= e(__('admin.users.create')) ?></a><?php endif ?></p><?php endif ?>
        </form>
    </div>
    <div class="col-12 col-lg-8">
        <form method="post" action="<?= e(route('admin.questions.store')) ?>" enctype="multipart/form-data" class="df-card" novalidate>
            <?= csrf_field() ?>
            <fieldset class="mb-3">
                <legend class="h6"><?= e(__('admin.questions.create.member')) ?></legend>
                <?php if ($members === []): ?>
                <input type="hidden" name="user_id" value="<?= e(old('user_id')) ?>" data-member-id>
                <p class="text-secondary small mb-0"><?= e(__('admin.questions.create.pick_hint')) ?></p>
                <?php else: ?>
                <?php foreach ($members as $m): ?>
                <div class="form-check"><input class="form-check-input" type="radio" name="user_id" id="member-<?= (int) $m['id'] ?>" value="<?= (int) $m['id'] ?>" <?= (string) old('user_id') === (string) $m['id'] ? 'checked' : '' ?> required><label class="form-check-label" for="member-<?= (int) $m['id'] ?>" dir="auto"><?= e($m['full_name']) ?> <span class="text-secondary small ltr"><?= e($m['phone'] ?: $m['email']) ?></span></label></div>
                <?php endforeach ?>
                <?php endif ?>
                <?= $this->partial('partials/field-error', ['name' => 'user_id']) ?>
            </fieldset>
            <div class="row g-3">
                <div class="col-12 col-md-6"><label class="form-label" for="rubrique_id"><?= e(__('admin.questions.col.rubrique')) ?></label><select class="form-select" id="rubrique_id" name="rubrique_id" required><?php foreach ($rubriques as $r): ?><option value="<?= (int) $r['id'] ?>" <?= (string) old('rubrique_id') === (string) $r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option><?php endforeach ?></select></div>
                <div class="col-12 col-md-6"><label class="form-label" for="title"><?= e(__('questions.create.title_label')) ?></label><input type="text" class="form-control" id="title" name="title" maxlength="200" value="<?= e(old('title')) ?>" dir="auto"></div>
                <div class="col-12"><label class="form-label" for="audio"><?= e(__('admin.questions.import.file')) ?></label><input type="file" class="form-control" id="audio" name="audio" accept="audio/*,.ogg,.opus,.m4a,.mp3"><div class="form-text"><?= e(__('admin.questions.create.audio_help')) ?></div></div>
                <div class="col-12"><label class="form-label" for="body"><?= e(__('questions.create.body_label')) ?></label><textarea class="form-control" id="body" name="body" rows="4" dir="auto"><?= e(old('body')) ?></textarea></div>
                <div class="col-6 col-md-3"><label class="form-label" for="spoken_language"><?= e(__('questions.create.spoken_language')) ?></label><select class="form-select" id="spoken_language" name="spoken_language"><?php foreach (['fr', 'ar', 'en', 'bm', 'other'] as $l): ?><option value="<?= e($l) ?>" <?= old('spoken_language', 'fr') === $l ? 'selected' : '' ?>><?= e(__('questions.languages.' . $l)) ?></option><?php endforeach ?></select></div>
                <div class="col-6 col-md-3"><label class="form-label" for="answer_language"><?= e(__('questions.create.answer_language')) ?></label><select class="form-select" id="answer_language" name="answer_language"><?php foreach (['fr', 'ar', 'en', 'bm'] as $l): ?><option value="<?= e($l) ?>" <?= old('answer_language', 'fr') === $l ? 'selected' : '' ?>><?= e(__('questions.languages.' . $l)) ?></option><?php endforeach ?></select></div>
                <div class="col-6 col-md-3"><label class="form-label" for="answer_channel"><?= e(__('questions.create.channel')) ?></label><select class="form-select" id="answer_channel" name="answer_channel"><?php foreach (['whatsapp', 'platform', 'both'] as $c): ?><option value="<?= e($c) ?>" <?= old('answer_channel', 'whatsapp') === $c ? 'selected' : '' ?>><?= e(__('questions.channels.' . $c)) ?></option><?php endforeach ?></select></div>
                <div class="col-6 col-md-3"><label class="form-label" for="whatsapp_number"><?= e(__('questions.create.whatsapp_number')) ?></label><input type="tel" class="form-control" id="whatsapp_number" name="whatsapp_number" value="<?= e(old('whatsapp_number')) ?>" placeholder="+223…" dir="ltr"><?= $this->partial('partials/field-error', ['name' => 'whatsapp_number']) ?></div>
                <div class="col-12">
                    <div class="form-check"><input class="form-check-input" type="radio" name="visibility" id="vis-private" value="private" <?= old('visibility', 'private') === 'private' ? 'checked' : '' ?>><label class="form-check-label" for="vis-private"><?= e(__('questions.create.visibility_private')) ?></label></div>
                    <div class="form-check"><input class="form-check-input" type="radio" name="visibility" id="vis-pub" value="publishable" <?= old('visibility') === 'publishable' ? 'checked' : '' ?>><label class="form-check-label" for="vis-pub"><?= e(__('questions.create.visibility_publishable')) ?></label></div>
                </div>
            </div>
            <div class="mt-3"><button type="submit" class="btn btn-primary"><i class="bi bi-send" aria-hidden="true"></i> <?= e(__('admin.questions.create.submit')) ?></button></div>
        </form>
    </div>
</div>
<?php $this->endSection(); ?>
