<?php
/**
 * Détail d'une question du membre (§4.4) : audio/texte, statut, chronologie, réponse officielle,
 * précision (texte/vocal), « résolue », avis, WhatsApp.
 *
 * @var \App\Core\View $this
 * @var array<string, mixed> $question
 * @var list<array<string, mixed>> $messages
 * @var array<string, mixed>|null $answer
 * @var list<array<string, mixed>> $timeline
 * @var bool $canFollowUp
 * @var bool $canResolve
 * @var string|null $whatsappLink
 * @var int $maxSeconds
 */
$this->extend('layouts/account');
$this->section('title', __('questions.detail.title', ['reference' => $question['reference']]));
$this->section('head'); ?>
<link rel="stylesheet" href="<?= e(asset('vendor/intl-tel-input/css/intlTelInput.min.css')) ?>">
<?php $this->endSection(); $this->section('scripts'); ?>
<script src="<?= e(asset('assets/js/audio-player.js')) ?>" type="module"></script>
<?php if ($canFollowUp): ?><script src="<?= e(asset('assets/js/voice-recorder.js')) ?>" type="module"></script><?php endif ?>
<?php $this->endSection();
$scholarName = $question['scholar_name'] ? trim(($question['scholar_title'] ? $question['scholar_title'] . ' ' : '') . $question['scholar_name']) : null;
$renderMessage = function (array $m) use ($question): void {
    $isMember = (int) ($m['sender_id'] ?? 0) === (int) $question['user_id'];
    $kind = (string) $m['kind'];
    ?>
    <article class="df-msg df-msg--<?= $isMember ? 'member' : 'team' ?> df-msg--<?= e($kind) ?>">
        <header class="df-msg__head">
            <span class="df-msg__kind"><?= e(__('questions.detail.kinds.' . $kind)) ?></span>
            <time class="df-msg__date" datetime="<?= e($m['created_at']) ?>"><?= e(format_date((string) $m['created_at'], true)) ?></time>
        </header>
        <?php if (!empty($m['media_uuid'])): ?>
        <?= $this->partial('partials/audio-player', ['src' => route('media.audio', ['uuid' => $m['media_uuid']]), 'duration' => $m['media_duration'] !== null ? (int) $m['media_duration'] : null, 'mime' => $m['media_mime'], 'label' => __('questions.detail.kinds.' . $kind)]) ?>
        <?php if (!empty($m['media_transcript'])): ?><details class="mt-2"><summary class="small fw-semibold"><?= e(__('questions.detail.transcript')) ?></summary><p class="mt-2 mb-0" dir="auto"><?= nl2br(e($m['media_transcript'])) ?></p></details><?php endif ?>
        <?php endif ?>
        <?php if (!empty($m['body'])): ?><div class="df-msg__body df-prose" dir="auto"><?= $m['body'] /* HTML purifié à l'enregistrement */ ?></div><?php endif ?>
        <?php if ($kind === 'answer' && !empty($m['references_json'])): $refs = json_decode((string) $m['references_json'], true); if (is_array($refs) && $refs !== []): ?>
        <div class="df-msg__refs">
            <p class="fw-semibold mb-1"><i class="bi bi-bookmark-star" aria-hidden="true"></i> <?= e(__('questions.detail.references')) ?></p>
            <ul class="mb-0">
                <?php foreach ($refs as $ref): ?>
                <li><?= e(\App\Services\ReferenceFormatter::format($ref)) ?></li>
                <?php endforeach ?>
            </ul>
        </div>
        <?php endif; endif ?>
    </article>
    <?php
};
?>
<?php $this->section('content'); ?>
<div class="df-question-head df-card mb-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
        <div>
            <p class="df-question-head__ref mb-1" dir="ltr"><?= e($question['reference']) ?></p>
            <h2 class="h5 mb-1" dir="auto"><?= e($question['title'] ?: __('questions.list.untitled')) ?></h2>
            <p class="df-question-item__meta mb-0">
                <span><img src="<?= e(asset((string) $question['rubrique_icon'])) ?>" alt="" width="18" height="18"> <?= e($question['rubrique_name']) ?></span>
                <span><i class="bi bi-calendar3" aria-hidden="true"></i> <?= e(format_date((string) $question['created_at'], true)) ?></span>
                <span><i class="bi bi-<?= $question['input_type'] === 'text' ? 'pencil' : 'mic-fill' ?>" aria-hidden="true"></i> <?= e(__('questions.types.' . $question['input_type'])) ?></span>
                <span><i class="bi bi-send" aria-hidden="true"></i> <?= e(__('questions.channels.' . $question['answer_channel'])) ?></span>
            </p>
        </div>
        <?= $this->partial('partials/status-badge', ['status' => $question['status']]) ?>
    </div>
</div>

<?php if ($question['status'] === 'rejected'): ?>
<div class="alert alert-danger" role="alert"><strong><?= e(__('questions.detail.rejected')) ?></strong><?php if (!empty($question['rejection_reason'])): ?><br><?= e(__('questions.detail.rejection_reason')) ?> : <span dir="auto"><?= e($question['rejection_reason']) ?></span><?php endif ?></div>
<?php elseif ($question['status'] === 'needs_info'): ?>
<div class="alert alert-warning" role="status"><i class="bi bi-question-circle" aria-hidden="true"></i> <?= e(__('questions.detail.needs_info')) ?></div>
<?php elseif ($answer === null): ?>
<div class="alert alert-info" role="status"><i class="bi bi-hourglass-split" aria-hidden="true"></i> <?= e(__('questions.detail.answer_pending')) ?></div>
<?php endif ?>

<section class="df-card mb-3">
    <h2 class="df-card__title"><i class="bi bi-chat-left-dots" aria-hidden="true"></i> <?= e(__('questions.detail.thread')) ?></h2>
    <div class="df-thread">
        <?php foreach ($messages as $m): if ($m['kind'] === 'answer') continue; $renderMessage($m); endforeach ?>
    </div>
</section>

<?php if ($answer !== null): ?>
<section class="df-card df-answer mb-3">
    <h2 class="df-card__title"><i class="bi bi-patch-check-fill" aria-hidden="true"></i> <?= e(__('questions.detail.answer')) ?></h2>
    <?php if ($scholarName !== null): ?>
    <div class="df-answer__scholar">
        <?php $photo = $question['scholar_photo'] ?: $question['scholar_avatar']; ?>
        <?php if ($photo): ?><img class="df-avatar" src="<?= e($photo) ?>" alt="" width="48" height="48"><?php else: ?><span class="df-avatar df-avatar--initials" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) $question['scholar_name'], 0, 1))) ?></span><?php endif ?>
        <p class="mb-0" dir="auto"><?= e(__('questions.detail.by', ['name' => $scholarName])) ?></p>
    </div>
    <?php endif ?>
    <?php $renderMessage($answer) ?>
    <p class="df-answer__disclaimer small text-secondary mt-3 mb-3"><i class="bi bi-info-circle" aria-hidden="true"></i> <?= e(__('questions.detail.disclaimer')) ?></p>
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <?php if ($question['user_found_helpful'] === null): ?>
        <form method="post" action="<?= e(route('account.questions.feedback', ['reference' => $question['reference']])) ?>" class="d-flex align-items-center gap-2 flex-wrap">
            <?= csrf_field() ?>
            <span><?= e(__('questions.detail.helpful')) ?></span>
            <button type="submit" name="helpful" value="1" class="btn btn-sm btn-outline-success"><i class="bi bi-hand-thumbs-up" aria-hidden="true"></i> <?= e(__('questions.detail.helpful_yes')) ?></button>
            <button type="submit" name="helpful" value="0" class="btn btn-sm btn-outline-secondary"><i class="bi bi-hand-thumbs-down" aria-hidden="true"></i> <?= e(__('questions.detail.helpful_no')) ?></button>
        </form>
        <?php else: ?>
        <span class="text-secondary small"><?= e(__('questions.detail.feedback_given', ['answer' => __((int) $question['user_found_helpful'] === 1 ? 'questions.detail.helpful_yes' : 'questions.detail.helpful_no')])) ?></span>
        <?php endif ?>
        <?php if ($whatsappLink !== null && $question['answer_channel'] !== 'platform'): ?>
        <a class="btn btn-sm btn-success" href="<?= e($whatsappLink) ?>" target="_blank" rel="noopener"><i class="bi bi-whatsapp" aria-hidden="true"></i> <?= e(__('questions.detail.whatsapp')) ?></a>
        <?php endif ?>
        <?php if ($canResolve): ?>
        <form method="post" action="<?= e(route('account.questions.resolve', ['reference' => $question['reference']])) ?>" data-confirm="<?= e(__('questions.detail.resolve_confirm')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-check2-all" aria-hidden="true"></i> <?= e(__('questions.detail.resolve')) ?></button>
        </form>
        <?php endif ?>
    </div>
</section>
<?php endif ?>

<?php if ($canFollowUp): ?>
<section class="df-card mb-3" id="precision">
    <h2 class="df-card__title"><i class="bi bi-plus-circle" aria-hidden="true"></i> <?= e(__('questions.detail.add_follow_up')) ?></h2>
    <p class="text-secondary"><?= e(__('questions.detail.follow_up_help')) ?></p>
    <form method="post" action="<?= e(route('account.questions.follow_up', ['reference' => $question['reference']])) ?>" enctype="multipart/form-data" novalidate class="df-form">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label" for="body"><?= e(__('questions.detail.follow_up_text')) ?></label>
            <textarea class="form-control<?= error_for('body') !== null ? ' is-invalid' : '' ?>" id="body" name="body" rows="4" maxlength="10000" dir="auto"><?= e(old('body')) ?></textarea>
            <?= $this->partial('partials/field-error', ['name' => 'body']) ?>
        </div>
        <?= $this->partial('partials/voice-recorder', ['name' => 'media_uuid', 'maxSeconds' => $maxSeconds]) ?>
        <?= $this->partial('partials/field-error', ['name' => 'audio_file']) ?>
        <button type="submit" class="btn btn-primary mt-3"><i class="bi bi-send" aria-hidden="true"></i> <?= e(__('questions.detail.follow_up_send')) ?></button>
    </form>
</section>
<?php endif ?>

<section class="df-card">
    <h2 class="df-card__title"><i class="bi bi-clock-history" aria-hidden="true"></i> <?= e(__('questions.detail.timeline')) ?></h2>
    <ol class="df-timeline">
        <?php foreach ($timeline as $log): ?>
        <li>
            <span class="df-timeline__dot" aria-hidden="true"></span>
            <div>
                <p class="mb-0"><?= e($log['from_status'] === null ? __('questions.detail.created') : __('questions.detail.status_changed', ['status' => __('questions.status.' . $log['to_status'])])) ?></p>
                <small class="text-secondary"><?= e(format_date((string) $log['created_at'], true)) ?></small>
            </div>
        </li>
        <?php endforeach ?>
    </ol>
</section>
<?php $this->endSection(); ?>
