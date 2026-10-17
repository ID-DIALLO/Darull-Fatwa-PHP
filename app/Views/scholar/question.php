<?php
/**
 * Fiche question du professeur (§4.5) : demandeur, audio avec vitesse, texte, transcription, historique,
 * autres questions du membre, actions (répondre, précision, note, réassignation, WhatsApp, publication).
 *
 * @var \App\Core\View $this
 * @var array<string, mixed> $question
 * @var array<string, mixed> $member
 * @var list<array<string, mixed>> $messages
 * @var array<string, mixed>|null $answer
 * @var bool $mine
 * @var bool $canTake
 * @var bool $canAct
 * @var array<string, mixed>|null $pendingReview
 * @var bool $rejectedReview
 * @var array{visible: bool, display: string, raw: string|null} $phone
 * @var string|null $whatsappLink
 * @var string|null $whatsappText
 * @var list<array<string, mixed>> $others
 * @var list<array<string, mixed>> $timeline
 * @var list<array<string, mixed>> $assignments
 * @var array<int, string> $surahs
 * @var list<string> $collections
 * @var int $maxSeconds
 * @var string|null $country
 */
$this->extend('layouts/scholar');
$this->section('title', __('scholar.question.title', ['reference' => $question['reference']]));
$this->section('head'); ?>
<link rel="stylesheet" href="<?= e(asset('vendor/quill/quill.snow.css')) ?>">
<?php $this->endSection(); $this->section('scripts'); ?>
<script src="<?= e(asset('vendor/quill/quill.js')) ?>" defer></script>
<script src="<?= e(asset('assets/js/audio-player.js')) ?>" type="module"></script>
<script src="<?= e(asset('assets/js/voice-recorder.js')) ?>" type="module"></script>
<script src="<?= e(asset('assets/js/answer-editor.js')) ?>" type="module"></script>
<?php $this->endSection();
$asker = \App\Services\AnswerService::askerLabel($member);
$renderMessage = function (array $m) use ($question): void {
    $kind = (string) $m['kind'];
    $isMember = (int) ($m['sender_id'] ?? 0) === (int) $question['user_id'];
    $label = $kind === 'internal_note' ? __('scholar.question.internal') : __('questions.detail.kinds.' . $kind);
    ?>
    <article class="df-msg df-msg--<?= $isMember ? 'member' : 'team' ?> df-msg--<?= e($kind) ?>" id="message-<?= (int) $m['id'] ?>">
        <header class="df-msg__head">
            <span class="df-msg__kind"><?= e($label) ?><?php if ($kind === 'answer' && $m['review_status'] === 'pending'): ?> · <span class="df-badge df-badge--warning"><?= e(__('questions.status.in_progress')) ?></span><?php elseif ($kind === 'answer' && $m['review_status'] === 'rejected'): ?> · <span class="df-badge df-badge--danger"><?= e(__('scholar.actions.remove')) ?></span><?php endif ?></span>
            <span class="df-msg__date"><?= e($m['sender_name'] ? (string) ($m['sender_display_name'] ?: $m['sender_name']) : '') ?> · <?= e(format_date((string) $m['created_at'], true)) ?></span>
        </header>
        <?php if (!empty($m['media_uuid'])): ?>
        <?= $this->partial('partials/audio-player', ['src' => route('media.audio', ['uuid' => $m['media_uuid']]), 'duration' => $m['media_duration'] !== null ? (int) $m['media_duration'] : null, 'mime' => $m['media_mime'], 'label' => $label]) ?>
        <?php if ($isMember): ?>
        <details class="mt-2">
            <summary class="small fw-semibold"><i class="bi bi-text-paragraph" aria-hidden="true"></i> <?= e(__('scholar.question.transcript')) ?></summary>
            <form method="post" action="<?= e(route('scholar.questions.transcript', ['reference' => $question['reference']])) ?>" class="mt-2">
                <?= csrf_field() ?>
                <input type="hidden" name="media_id" value="<?= (int) $m['media_id'] ?>">
                <textarea class="form-control mb-2" name="transcript" rows="4" dir="auto" aria-label="<?= e(__('scholar.question.transcript')) ?>"><?= e($m['media_transcript'] ?? '') ?></textarea>
                <div class="d-flex justify-content-between align-items-center gap-2"><small class="text-secondary"><?= e(__('scholar.question.transcript_help')) ?></small><button type="submit" class="btn btn-sm btn-outline-primary"><?= e(__('scholar.question.save')) ?></button></div>
            </form>
        </details>
        <?php endif ?>
        <?php endif ?>
        <?php if (!empty($m['body'])): ?><div class="df-msg__body df-prose" dir="auto"><?= $m['body'] /* HTML purifié à l'enregistrement */ ?></div><?php endif ?>
        <?php if ($kind === 'answer' && !empty($m['references_json'])): $refs = json_decode((string) $m['references_json'], true); if (is_array($refs) && $refs !== []): ?>
        <div class="df-msg__refs"><p class="fw-semibold mb-1"><i class="bi bi-bookmark-star" aria-hidden="true"></i> <?= e(__('questions.detail.references')) ?></p><ul class="mb-0"><?php foreach ($refs as $ref): ?><li><?= e(\App\Services\ReferenceFormatter::format($ref)) ?></li><?php endforeach ?></ul></div>
        <?php endif; endif ?>
        <?php if ($kind === 'answer' && !empty($m['sent_via_whatsapp_at'])): ?><p class="small text-success mt-2 mb-0"><i class="bi bi-whatsapp" aria-hidden="true"></i> <?= e(__('scholar.actions.whatsapp_sent_at', ['date' => format_date((string) $m['sent_via_whatsapp_at'], true)])) ?></p><?php endif ?>
    </article>
    <?php
};
?>
<?php $this->section('content'); ?>
<?php if (!$mine && !$canTake): ?><div class="alert alert-info" role="status"><?= e(__('scholar.question.not_yours')) ?></div><?php endif ?>
<?php if ($pendingReview !== null): ?><div class="alert alert-warning" role="status"><i class="bi bi-eye" aria-hidden="true"></i> <?= e(__('scholar.question.review_pending')) ?></div><?php endif ?>
<?php if ($rejectedReview): ?><div class="alert alert-danger" role="alert"><i class="bi bi-pencil-square" aria-hidden="true"></i> <?= e(__('scholar.question.review_rejected')) ?></div><?php endif ?>
<?php if ($mine && in_array($question['status'], ['answered', 'published', 'closed'], true)): ?><div class="alert alert-success" role="status"><i class="bi bi-check2-circle" aria-hidden="true"></i> <?= e(__('scholar.question.answered_notice')) ?></div><?php endif ?>

<div class="row g-4">
    <div class="col-12 col-xl-8">
        <div class="df-card mb-3">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                <div>
                    <p class="df-question-head__ref mb-1" dir="ltr"><?= e($question['reference']) ?></p>
                    <h2 class="h5 mb-1" dir="auto"><?= e($question['title'] ?: __('questions.list.untitled')) ?></h2>
                </div>
                <?= $this->partial('partials/status-badge', ['status' => $question['status']]) ?>
            </div>
            <p class="df-question-item__meta mb-0">
                <span><img src="<?= e(asset((string) $question['rubrique_icon'])) ?>" alt="" width="18" height="18"> <?= e($question['rubrique_name']) ?></span>
                <span><i class="bi bi-<?= $question['input_type'] === 'text' ? 'pencil' : 'mic-fill' ?>" aria-hidden="true"></i> <?= e(__('questions.types.' . $question['input_type'])) ?></span>
                <span><i class="bi bi-translate" aria-hidden="true"></i> <?= e(__('questions.languages.' . $question['spoken_language'])) ?> → <?= e(__('questions.languages.' . $question['answer_language'])) ?></span>
                <span><i class="bi bi-calendar3" aria-hidden="true"></i> <?= e(__('scholar.question.asked_on')) ?> <?= e(format_date((string) $question['created_at'], true)) ?> (<?= e(time_ago((string) $question['created_at'])) ?>)</span>
                <span><i class="bi bi-<?= $question['source'] === 'whatsapp' ? 'whatsapp' : 'globe2' ?>" aria-hidden="true"></i> <?= e(__('scholar.question.source')) ?> : <?= e(__('scholar.question.source_' . $question['source'])) ?></span>
                <?php if (!empty($question['requested_scholar_id'])): ?><span class="df-badge df-badge--gold"><i class="bi bi-star-fill" aria-hidden="true"></i> <?= e((int) $question['requested_scholar_id'] === (int) auth()['id'] ? __('scholar.question.requested_you') : __('scholar.question.requested_other', ['name' => (string) ($question['requested_scholar_name'] ?? '')])) ?></span><?php endif ?>
            </p>
            <?php if ($canTake): ?>
            <form method="post" action="<?= e(route('scholar.questions.take', ['reference' => $question['reference']])) ?>" class="mt-3">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-gold"><i class="bi bi-hand-index-thumb" aria-hidden="true"></i> <?= e(__('scholar.queue.take')) ?></button>
            </form>
            <?php endif ?>
        </div>

        <section class="df-card mb-3">
            <h2 class="df-card__title"><i class="bi bi-chat-left-dots" aria-hidden="true"></i> <?= e(__('scholar.question.thread')) ?></h2>
            <div class="df-thread"><?php foreach ($messages as $m) { $renderMessage($m); } ?></div>
        </section>

        <?php if ($canAct): ?>
        <section class="df-card mb-3" id="repondre">
            <h2 class="df-card__title"><i class="bi bi-reply-fill icon-dir" aria-hidden="true"></i> <?= e(__('scholar.actions.answer_title')) ?></h2>
            <p class="text-secondary"><?= e(__('scholar.actions.answer_help')) ?></p>
            <form method="post" action="<?= e(route('scholar.questions.answer', ['reference' => $question['reference']])) ?>" enctype="multipart/form-data" class="df-form" data-answer-form novalidate>
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label" for="answer-editor"><?= e(__('scholar.actions.answer_text')) ?></label>
                    <div id="answer-editor" class="df-editor" data-editor dir="auto"><?= old('body_html') ?></div>
                    <textarea class="form-control<?= error_for('body_html') !== null ? ' is-invalid' : '' ?>" name="body_html" id="body_html" rows="8" data-editor-target dir="auto"><?= e(old('body_html')) ?></textarea>
                    <?= $this->partial('partials/field-error', ['name' => 'body_html']) ?>
                </div>
                <p class="form-label mb-1"><?= e(__('scholar.actions.answer_voice')) ?></p>
                <?= $this->partial('partials/voice-recorder', ['name' => 'media_uuid', 'maxSeconds' => $maxSeconds]) ?>
                <div class="row g-3 mt-1">
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="answer_language"><?= e(__('scholar.actions.answer_language')) ?></label>
                        <select class="form-select" id="answer_language" name="answer_language">
                            <?php foreach (['fr', 'ar', 'en', 'bm'] as $code): ?><option value="<?= e($code) ?>" <?= old('answer_language', $question['answer_language']) === $code ? 'selected' : '' ?>><?= e(__('questions.languages.' . $code)) ?></option><?php endforeach ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="audio_file"><?= e(__('questions.create.attach_file')) ?></label>
                        <input type="file" class="form-control" id="audio_file" name="audio_file" accept="audio/*,.ogg,.opus,.m4a,.mp3,.webm,.wav">
                        <?= $this->partial('partials/field-error', ['name' => 'audio_file']) ?>
                    </div>
                </div>
                <fieldset class="df-fieldset mt-3" data-references>
                    <legend><?= e(__('scholar.actions.references')) ?></legend>
                    <p class="form-text mt-0"><?= e(__('scholar.actions.references_help')) ?></p>
                    <div data-references-list></div>
                    <input type="hidden" name="references" data-references-json value="<?= e(old('references', '[]')) ?>">
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <?php foreach (['quran' => 'book', 'hadith' => 'journal-bookmark', 'book' => 'bookmarks'] as $type => $icon): ?>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-add-reference="<?= e($type) ?>"><i class="bi bi-<?= e($icon) ?>" aria-hidden="true"></i> <?= e(__('references.types.' . $type)) ?></button>
                        <?php endforeach ?>
                    </div>
                    <template data-reference-template="quran">
                        <div class="df-reference" data-reference-row data-type="quran">
                            <div class="row g-2 align-items-end">
                                <div class="col-12 col-md-6"><label class="form-label small"><?= e(__('scholar.actions.ref_surah')) ?></label><select class="form-select form-select-sm" data-field="surah"><?php foreach ($surahs as $n => $label): ?><option value="<?= $n ?>"><?= e($label) ?></option><?php endforeach ?></select></div>
                                <div class="col-5 col-md-2"><label class="form-label small"><?= e(__('scholar.actions.ref_ayah_from')) ?></label><input type="number" min="1" max="286" class="form-control form-control-sm" data-field="ayah_from" dir="ltr"></div>
                                <div class="col-5 col-md-2"><label class="form-label small"><?= e(__('scholar.actions.ref_ayah_to')) ?></label><input type="number" min="1" max="286" class="form-control form-control-sm" data-field="ayah_to" dir="ltr"></div>
                                <div class="col-2 col-md-2 text-end"><button type="button" class="btn btn-sm btn-outline-danger" data-remove-reference aria-label="<?= e(__('scholar.actions.remove')) ?>"><i class="bi bi-x-lg" aria-hidden="true"></i></button></div>
                            </div>
                        </div>
                    </template>
                    <template data-reference-template="hadith">
                        <div class="df-reference" data-reference-row data-type="hadith">
                            <div class="row g-2 align-items-end">
                                <div class="col-12 col-md-4"><label class="form-label small"><?= e(__('scholar.actions.ref_collection')) ?></label><select class="form-select form-select-sm" data-field="collection"><?php foreach ($collections as $c): ?><option value="<?= e($c) ?>"><?= e(__('references.collections.' . $c)) ?></option><?php endforeach ?></select></div>
                                <div class="col-12 col-md-3" data-other-collection hidden><label class="form-label small"><?= e(__('scholar.actions.ref_collection_name')) ?></label><input type="text" class="form-control form-control-sm" data-field="collection_name" dir="auto"></div>
                                <div class="col-5 col-md-2"><label class="form-label small"><?= e(__('scholar.actions.ref_number')) ?></label><input type="text" class="form-control form-control-sm" data-field="number" dir="ltr"></div>
                                <div class="col-5 col-md-2"><label class="form-label small"><?= e(__('scholar.actions.ref_grade')) ?></label><input type="text" class="form-control form-control-sm" data-field="grade" dir="auto"></div>
                                <div class="col-2 col-md-1 text-end"><button type="button" class="btn btn-sm btn-outline-danger" data-remove-reference aria-label="<?= e(__('scholar.actions.remove')) ?>"><i class="bi bi-x-lg" aria-hidden="true"></i></button></div>
                            </div>
                        </div>
                    </template>
                    <template data-reference-template="book">
                        <div class="df-reference" data-reference-row data-type="book">
                            <div class="row g-2 align-items-end">
                                <div class="col-12 col-md-5"><label class="form-label small"><?= e(__('scholar.actions.ref_title')) ?></label><input type="text" class="form-control form-control-sm" data-field="title" dir="auto"></div>
                                <div class="col-7 col-md-4"><label class="form-label small"><?= e(__('scholar.actions.ref_author')) ?></label><input type="text" class="form-control form-control-sm" data-field="author" dir="auto"></div>
                                <div class="col-3 col-md-2"><label class="form-label small"><?= e(__('scholar.actions.ref_page')) ?></label><input type="text" class="form-control form-control-sm" data-field="page" dir="ltr"></div>
                                <div class="col-2 col-md-1 text-end"><button type="button" class="btn btn-sm btn-outline-danger" data-remove-reference aria-label="<?= e(__('scholar.actions.remove')) ?>"><i class="bi bi-x-lg" aria-hidden="true"></i></button></div>
                            </div>
                        </div>
                    </template>
                </fieldset>
                <?php if ($question['visibility'] === 'publishable'): ?>
                <div class="form-check df-choice mt-3">
                    <input class="form-check-input" type="checkbox" id="propose_public" name="propose_public" value="1" <?= old('propose_public') ? 'checked' : '' ?>>
                    <label class="form-check-label" for="propose_public"><strong><?= e(__('scholar.actions.propose_public')) ?></strong><span class="d-block small text-secondary"><?= e(__('scholar.actions.propose_public_help')) ?></span></label>
                </div>
                <?php else: ?>
                <p class="small text-secondary mt-3 mb-0"><i class="bi bi-lock" aria-hidden="true"></i> <?= e(__('scholar.actions.private_only')) ?></p>
                <?php endif ?>
                <button type="submit" class="btn btn-gold btn-lg mt-2"><i class="bi bi-send" aria-hidden="true"></i> <?= e(__('scholar.actions.send_answer')) ?></button>
            </form>
        </section>

        <section class="df-card mb-3" id="precision">
            <h2 class="df-card__title"><i class="bi bi-question-circle" aria-hidden="true"></i> <?= e(__('scholar.actions.request_info')) ?></h2>
            <p class="text-secondary"><?= e(__('scholar.actions.request_info_help')) ?></p>
            <form method="post" action="<?= e(route('scholar.questions.request_info', ['reference' => $question['reference']])) ?>" enctype="multipart/form-data" class="df-form" novalidate>
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label" for="info_body"><?= e(__('scholar.actions.request_info_text')) ?></label>
                    <textarea class="form-control" id="info_body" name="body" rows="3" dir="auto"></textarea>
                </div>
                <?= $this->partial('partials/voice-recorder', ['name' => 'media_uuid', 'maxSeconds' => $maxSeconds]) ?>
                <button type="submit" class="btn btn-primary mt-3"><i class="bi bi-send" aria-hidden="true"></i> <?= e(__('scholar.actions.request_info_send')) ?></button>
            </form>
        </section>
        <?php endif ?>

        <?php if ($mine): ?>
        <div class="row g-3 mb-3">
            <div class="col-12 col-lg-6">
                <section class="df-card h-100">
                    <h2 class="df-card__title h6"><i class="bi bi-journal-text" aria-hidden="true"></i> <?= e(__('scholar.actions.note')) ?></h2>
                    <p class="small text-secondary"><?= e(__('scholar.actions.note_help')) ?></p>
                    <form method="post" action="<?= e(route('scholar.questions.note', ['reference' => $question['reference']])) ?>">
                        <?= csrf_field() ?>
                        <textarea class="form-control mb-2<?= error_for('body') !== null ? ' is-invalid' : '' ?>" name="body" rows="3" dir="auto" aria-label="<?= e(__('scholar.actions.note_text')) ?>" required></textarea>
                        <button type="submit" class="btn btn-sm btn-outline-primary"><?= e(__('scholar.actions.note_save')) ?></button>
                    </form>
                </section>
            </div>
            <?php if ($canAct): ?>
            <div class="col-12 col-lg-6">
                <section class="df-card h-100">
                    <h2 class="df-card__title h6"><i class="bi bi-arrow-left-right" aria-hidden="true"></i> <?= e(__('scholar.actions.reassign')) ?></h2>
                    <p class="small text-secondary"><?= e(__('scholar.actions.reassign_help')) ?></p>
                    <form method="post" action="<?= e(route('scholar.questions.reassign', ['reference' => $question['reference']])) ?>">
                        <?= csrf_field() ?>
                        <textarea class="form-control mb-2<?= error_for('reason') !== null ? ' is-invalid' : '' ?>" name="reason" rows="3" dir="auto" aria-label="<?= e(__('scholar.actions.reassign_reason')) ?>" required minlength="5"></textarea>
                        <?= $this->partial('partials/field-error', ['name' => 'reason']) ?>
                        <button type="submit" class="btn btn-sm btn-outline-primary"><?= e(__('scholar.actions.reassign_send')) ?></button>
                    </form>
                </section>
            </div>
            <?php endif ?>
        </div>

        <?php if ($answer !== null): ?>
        <div class="row g-3 mb-3">
            <div class="col-12 col-lg-6">
                <section class="df-card h-100">
                    <h2 class="df-card__title h6"><i class="bi bi-whatsapp" aria-hidden="true"></i> <?= e(__('scholar.actions.whatsapp')) ?></h2>
                    <?php if ($whatsappLink !== null): ?>
                    <p class="small text-secondary"><?= e(__('scholar.actions.whatsapp_help')) ?></p>
                    <a class="btn btn-success btn-sm mb-2" href="<?= e($whatsappLink) ?>" target="_blank" rel="noopener"><i class="bi bi-whatsapp" aria-hidden="true"></i> <?= e(__('scholar.actions.whatsapp_open')) ?></a>
                    <form method="post" action="<?= e(route('scholar.questions.whatsapp_sent', ['reference' => $question['reference']])) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="text" value="<?= e($whatsappText) ?>">
                        <button type="submit" class="btn btn-sm btn-outline-success"><i class="bi bi-check2" aria-hidden="true"></i> <?= e(__('scholar.actions.whatsapp_mark')) ?></button>
                    </form>
                    <?php else: ?>
                    <p class="small text-secondary mb-0"><?= e(__('scholar.actions.whatsapp_unavailable')) ?></p>
                    <?php endif ?>
                </section>
            </div>
            <div class="col-12 col-lg-6">
                <section class="df-card h-100">
                    <h2 class="df-card__title h6"><i class="bi bi-megaphone" aria-hidden="true"></i> <?= e(__('scholar.actions.publish')) ?></h2>
                    <?php if ($question['visibility'] === 'publishable'): ?>
                    <p class="small text-secondary"><?= e(__('scholar.actions.publish_help')) ?></p>
                    <form method="post" action="<?= e(route('scholar.questions.propose_publication', ['reference' => $question['reference']])) ?>">
                        <?= csrf_field() ?>
                        <input type="text" class="form-control form-control-sm mb-2<?= error_for('title') !== null ? ' is-invalid' : '' ?>" name="title" placeholder="<?= e(__('scholar.actions.publish_title')) ?>" aria-label="<?= e(__('scholar.actions.publish_title')) ?>" dir="auto" required minlength="5">
                        <textarea class="form-control form-control-sm mb-2<?= error_for('rephrased') !== null ? ' is-invalid' : '' ?>" name="rephrased" rows="3" placeholder="<?= e(__('scholar.actions.publish_question')) ?>" aria-label="<?= e(__('scholar.actions.publish_question')) ?>" dir="auto" required minlength="10"></textarea>
                        <button type="submit" class="btn btn-sm btn-outline-primary"><?= e(__('scholar.actions.publish_send')) ?></button>
                    </form>
                    <?php else: ?>
                    <p class="small text-secondary mb-0"><?= e(__('scholar.actions.publish_not_allowed')) ?></p>
                    <?php endif ?>
                </section>
            </div>
        </div>
        <?php endif ?>
        <?php endif ?>
    </div>

    <aside class="col-12 col-xl-4">
        <div class="df-card mb-3">
            <h2 class="df-card__title h6"><i class="bi bi-person" aria-hidden="true"></i> <?= e(__('scholar.question.asker')) ?></h2>
            <dl class="df-dl mb-0 df-dl--compact">
                <dt><?= e(__('scholar.question.asker')) ?></dt><dd dir="auto"><?= e($asker) ?></dd>
                <?php if ($country): ?><dt><?= e(__('scholar.question.country')) ?></dt><dd><?= e(trim(($member['city'] ?? '') . ' ' . $country)) ?></dd><?php endif ?>
                <?php if (!empty($member['gender'])): ?><dt><?= e(__('scholar.question.gender')) ?></dt><dd><?= e(__('scholar.question.gender_' . $member['gender'])) ?></dd><?php endif ?>
                <dt><?= e(__('scholar.question.channel')) ?></dt><dd><?= e(__('questions.channels.' . $question['answer_channel'])) ?></dd>
                <dt><?= e(__('scholar.question.phone')) ?></dt>
                <dd class="ltr"><?= $phone['visible'] ? e($phone['display']) : ($phone['display'] !== '' ? e($phone['display']) . ' <small class="text-secondary">(' . e(__('scholar.question.phone_hidden')) . ')</small>' : '<small class="text-secondary">' . e(__('scholar.question.phone_hidden')) . '</small>') ?></dd>
                <dt><?= e(__('scholar.question.visibility')) ?></dt><dd><?= e(__('scholar.question.visibility_' . $question['visibility'])) ?></dd>
            </dl>
        </div>
        <div class="df-card mb-3">
            <h2 class="df-card__title h6"><i class="bi bi-collection" aria-hidden="true"></i> <?= e(__('scholar.question.other_questions')) ?></h2>
            <?php if ($others === []): ?><p class="small text-secondary mb-0"><?= e(__('scholar.question.no_other')) ?></p><?php else: ?>
            <ul class="df-list small">
                <?php foreach ($others as $o): ?><li><a href="<?= e(route('scholar.questions.show', ['reference' => $o['reference']])) ?>" dir="auto"><span class="ltr"><?= e($o['reference']) ?></span> · <?= e($o['title'] ?: __('questions.list.untitled')) ?></a><small><?= e(__('questions.status.' . $o['status'])) ?></small></li><?php endforeach ?>
            </ul>
            <?php endif ?>
        </div>
        <div class="df-card">
            <h2 class="df-card__title h6"><i class="bi bi-clock-history" aria-hidden="true"></i> <?= e(__('scholar.question.history')) ?></h2>
            <ol class="df-timeline small">
                <?php foreach ($timeline as $log): ?>
                <li><span class="df-timeline__dot" aria-hidden="true"></span><div><p class="mb-0"><?= e($log['from_status'] === null ? __('questions.detail.created') : __('questions.detail.status_changed', ['status' => __('questions.status.' . $log['to_status'])])) ?><?php if ($log['actor_name']): ?> <span class="text-secondary"><?= e(__('scholar.question.by', ['name' => $log['actor_name']])) ?></span><?php endif ?></p><?php if ($log['comment']): ?><p class="mb-0 text-secondary fst-italic" dir="auto"><?= e($log['comment']) ?></p><?php endif ?><small class="text-secondary"><?= e(format_date((string) $log['created_at'], true)) ?></small></div></li>
                <?php endforeach ?>
            </ol>
            <?php if ($assignments !== []): ?>
            <p class="fw-semibold small mt-3 mb-1"><?= e(__('scholar.question.assignments')) ?></p>
            <ul class="small text-secondary mb-0 ps-3">
                <?php foreach ($assignments as $a): ?><li><?= e($a['scholar_name'] ?? '—') ?> — <?= e(format_date((string) $a['assigned_at'], true)) ?> (<?= e($a['assigned_by_name'] ?? __('scholar.question.auto')) ?>)<?php if ($a['reason']): ?> : <?= e($a['reason']) ?><?php endif ?></li><?php endforeach ?>
            </ul>
            <?php endif ?>
        </div>
    </aside>
</div>
<?php $this->endSection(); ?>
