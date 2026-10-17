<?php
/**
 * Fiche question (§4.6.2) : informations, fil complet, chronologie, assignation, statut, relecture,
 * publication anonymisée, import d'audio WhatsApp, notes internes, journal WhatsApp.
 *
 * @var \App\Core\View $this
 * @var array<string, mixed> $question
 * @var list<array{at: string, kind: string, actor: string|null, text: string, comment: string|null}> $timeline
 * @var list<array<string, mixed>> $scholars
 * @var array<string, mixed>|null $pending
 * @var list<string> $nextStatuses
 * @var array{title: string, question_text: string, answer_html: string, has_scholar_audio: bool}|null $draft
 * @var array<string, mixed>|null $publication
 * @var callable $formatter
 * @var int $lateHours
 */
$this->extend('layouts/admin');
$this->section('title', e(__('admin.questions.sheet_title', ['reference' => $question['reference']])));
$member = $question['member'] ?? [];
$id = (int) $question['id'];
$open = in_array($question['status'], ['new', 'assigned', 'in_progress'], true);
$ageHours = (int) floor((time() - (int) strtotime($question['created_at'] . ' UTC')) / 3600);
$this->section('breadcrumbs');
?>
<ol><li><a href="<?= e(route('admin.dashboard')) ?>"><?= e(__('admin.nav.dashboard')) ?></a></li><li><a href="<?= e(route('admin.questions')) ?>"><?= e(__('admin.nav.questions')) ?></a></li><li aria-current="page"><?= e($question['reference']) ?></li></ol>
<?php $this->endSection(); ?>
<?php $this->section('actions'); ?>
<?= $this->partial('partials/status-badge', ['status' => $question['status']]) ?>
<?php if ($question['priority'] === 'high'): ?><span class="df-badge df-badge--danger"><i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i> <?= e(__('admin.questions.priority.high')) ?></span><?php endif ?>
<?php if ($open && $ageHours >= $lateHours): ?><span class="df-badge df-badge--danger"><i class="bi bi-alarm" aria-hidden="true"></i> <?= e(__('admin.questions.late_badge', ['hours' => $ageHours])) ?></span><?php endif ?>
<?php $this->endSection(); ?>
<?php $this->section('scripts'); ?>
<script src="<?= e(asset('vendor/quill/quill.js')) ?>" defer></script>
<script src="<?= e(asset('assets/js/audio-player.js')) ?>" type="module"></script>
<?php $this->endSection(); ?>
<?php $this->section('head'); ?><link rel="stylesheet" href="<?= e(asset('vendor/quill/quill.snow.css')) ?>"><?php $this->endSection(); ?>
<?php $this->section('content'); ?>
<div class="row g-3">
    <div class="col-12 col-xl-8">
        <?php if ($pending !== null && can('questions.review')): ?>
        <div class="df-card border-warning mb-3">
            <h2 class="df-card__title h6"><i class="bi bi-eye text-warning" aria-hidden="true"></i> <?= e(__('admin.questions.review.title')) ?></h2>
            <p class="small text-secondary"><?= e(__('admin.questions.review.intro')) ?></p>
            <form method="post" action="<?= e(route('admin.questions.review', ['id' => $id, 'messageId' => $pending['id']])) ?>" class="row g-2 align-items-end">
                <?= csrf_field() ?>
                <div class="col-12 col-md-7"><label class="form-label" for="review-reason"><?= e(__('admin.questions.review.reason')) ?></label><input type="text" class="form-control" id="review-reason" name="reason" maxlength="1000" placeholder="<?= e(__('admin.questions.review.reason_help')) ?>"></div>
                <div class="col-12 col-md-5 d-flex gap-2">
                    <button type="submit" class="btn btn-success" name="decision" value="approve"><i class="bi bi-check2" aria-hidden="true"></i> <?= e(__('admin.questions.review.approve')) ?></button>
                    <button type="submit" class="btn btn-outline-danger" name="decision" value="reject"><i class="bi bi-x-lg" aria-hidden="true"></i> <?= e(__('admin.questions.review.reject')) ?></button>
                </div>
            </form>
        </div>
        <?php endif ?>

        <div class="df-card mb-3">
            <h2 class="df-card__title h6"><i class="bi bi-chat-left-text" aria-hidden="true"></i> <?= e(__('admin.questions.thread')) ?></h2>
            <?php if (empty($question['messages'])): ?><p class="text-secondary mb-0"><?= e(__('admin.common.empty')) ?></p><?php else: ?>
            <div class="df-thread">
                <?php foreach ($question['messages'] as $m): $byMember = (int) $m['sender_id'] === (int) $question['user_id']; ?>
                <article class="df-msg df-msg--<?= $byMember ? 'member' : 'team' ?> df-msg--<?= e($m['kind']) ?>">
                    <header class="df-msg__head">
                        <span class="df-msg__kind"><?= e(__('admin.questions.kinds.' . $m['kind'])) ?><?php if ($m['review_status'] === 'pending'): ?> · <span class="text-warning"><?= e(__('admin.questions.review.pending')) ?></span><?php elseif ($m['review_status'] === 'rejected'): ?> · <span class="text-danger"><?= e(__('admin.questions.review.rejected')) ?></span><?php endif ?></span>
                        <span class="df-msg__date"><?= e((string) ($m['sender_name'] ?? '')) ?> · <?= e(format_date((string) $m['created_at'], true)) ?></span>
                    </header>
                    <?php if (!empty($m['media_uuid'])): ?>
                    <?= $this->partial('partials/audio-player', ['src' => route('media.audio', ['uuid' => $m['media_uuid']]), 'duration' => $m['media_duration'] !== null ? (int) $m['media_duration'] : null, 'mime' => $m['media_mime'], 'label' => __('admin.questions.kinds.' . $m['kind'])]) ?>
                    <?php if (!empty($m['media_transcript'])): ?><details class="small mt-1"><summary><?= e(__('scholar.question.transcript')) ?></summary><p class="mb-0" dir="auto"><?= nl2br(e($m['media_transcript'])) ?></p></details><?php endif ?>
                    <?php endif ?>
                    <?php if (!empty($m['body'])): ?><div class="df-msg__body df-prose" dir="auto"><?= $m['kind'] === 'internal_note' || $m['kind'] === 'follow_up' ? nl2br(e(strip_tags((string) $m['body']))) : \App\Services\Html::purify((string) $m['body'], 'rich') ?></div><?php endif ?>
                    <?php $refs = json_decode((string) ($m['references_json'] ?? '[]'), true); if (is_array($refs) && $refs !== []): ?>
                    <ul class="df-msg__refs"><?php foreach ($refs as $ref): ?><li><i class="bi bi-bookmark" aria-hidden="true"></i> <?= e($formatter($ref)) ?></li><?php endforeach ?></ul>
                    <?php endif ?>
                    <?php if (!empty($m['sent_via_whatsapp_at'])): ?><p class="small text-success mb-0"><i class="bi bi-whatsapp" aria-hidden="true"></i> <?= e(__('admin.questions.sent_whatsapp', ['date' => format_date((string) $m['sent_via_whatsapp_at'], true)])) ?></p><?php endif ?>
                </article>
                <?php endforeach ?>
            </div>
            <?php endif ?>
        </div>

        <div class="row g-3 mb-3">
            <?php if (can('questions.assign')): ?>
            <div class="col-12 col-md-6">
                <form method="post" action="<?= e(route('admin.questions.assign', ['id' => $id])) ?>" class="df-card h-100">
                    <?= csrf_field() ?>
                    <h2 class="df-card__title h6"><i class="bi bi-person-check" aria-hidden="true"></i> <?= e(__('admin.questions.assign.title')) ?></h2>
                    <label class="form-label" for="scholar_id"><?= e(__('admin.questions.col.scholar')) ?></label>
                    <select class="form-select mb-2" id="scholar_id" name="scholar_id" required>
                        <?php foreach ($scholars as $s): ?><option value="<?= (int) $s['id'] ?>" <?= (int) ($question['assigned_to'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['full_name']) ?> — <?= e(__('admin.questions.assign.load', ['count' => (int) $s['open_count'], 'max' => (int) ($s['max_open_questions'] ?? 20)])) ?><?= (int) $s['is_available'] === 0 ? ' · ' . e(__('admin.questions.assign.unavailable')) : '' ?></option><?php endforeach ?>
                    </select>
                    <input type="text" class="form-control mb-2" name="comment" maxlength="255" placeholder="<?= e(__('admin.questions.assign.reason')) ?>" aria-label="<?= e(__('admin.questions.assign.reason')) ?>">
                    <button type="submit" class="btn btn-primary btn-sm" <?= in_array($question['status'], ['rejected', 'closed', 'published'], true) ? 'disabled' : '' ?>><?= e(!empty($question['assigned_to']) ? __('admin.questions.assign.reassign') : __('admin.questions.assign.submit')) ?></button>
                </form>
            </div>
            <?php endif ?>
            <?php if (can('questions.change_status')): ?>
            <div class="col-12 col-md-6">
                <form method="post" action="<?= e(route('admin.questions.status', ['id' => $id])) ?>" class="df-card h-100">
                    <?= csrf_field() ?>
                    <h2 class="df-card__title h6"><i class="bi bi-arrow-repeat" aria-hidden="true"></i> <?= e(__('admin.questions.status.title')) ?></h2>
                    <?php if ($nextStatuses === []): ?><p class="text-secondary small mb-0"><?= e(__('admin.questions.status.final')) ?></p><?php else: ?>
                    <label class="form-label" for="status"><?= e(__('admin.questions.status.new_status')) ?></label>
                    <select class="form-select mb-2" id="status" name="status"><?php foreach ($nextStatuses as $s): ?><option value="<?= e($s) ?>"><?= e(__('questions.status.' . $s)) ?></option><?php endforeach ?></select>
                    <textarea class="form-control mb-2" name="comment" rows="2" maxlength="1000" placeholder="<?= e(__('admin.questions.status.comment')) ?>" aria-label="<?= e(__('admin.questions.status.comment')) ?>"></textarea>
                    <button type="submit" class="btn btn-outline-primary btn-sm"><?= e(__('admin.questions.status.submit')) ?></button>
                    <?php endif ?>
                </form>
            </div>
            <?php endif ?>
        </div>

        <?php if ($draft !== null && can('questions.publish')): ?>
        <div class="df-card mb-3">
            <h2 class="df-card__title h6"><i class="bi bi-globe2" aria-hidden="true"></i> <?= e(__('admin.questions.publish.title')) ?></h2>
            <?php if ($publication !== null): ?>
            <p class="mb-2"><i class="bi bi-check-circle-fill text-success" aria-hidden="true"></i> <?= e(__('admin.questions.publish.exists', ['status' => __('admin.qa.status.' . $publication['status'])])) ?> <a href="<?= e(route('admin.qa.edit', ['id' => $publication['id']])) ?>"><?= e(__('admin.questions.publish.edit')) ?></a></p>
            <?php else: ?>
            <?php if ($question['visibility'] !== 'publishable'): ?><div class="alert alert-warning small"><i class="bi bi-shield-exclamation" aria-hidden="true"></i> <?= e(__('admin.questions.publish.not_consented')) ?></div><?php endif ?>
            <p class="small text-secondary"><?= e(__('admin.questions.publish.intro')) ?></p>
            <form method="post" action="<?= e(route('admin.questions.publish', ['id' => $id])) ?>">
                <?= csrf_field() ?>
                <div class="mb-2"><label class="form-label" for="pub-title"><?= e(__('admin.questions.publish.title_field')) ?></label><input type="text" class="form-control" id="pub-title" name="title" maxlength="200" required value="<?= e(old('title', $draft['title'])) ?>" dir="auto"></div>
                <div class="mb-2"><label class="form-label" for="pub-question"><?= e(__('admin.questions.publish.question_text')) ?></label><textarea class="form-control" id="pub-question" name="question_text" rows="4" required dir="auto"><?= e(old('question_text', strip_tags(str_replace(['</p>', '<br>'], ["\n\n", "\n"], $draft['question_text'])))) ?></textarea><div class="form-text"><?= e(__('admin.questions.publish.question_help')) ?></div></div>
                <div class="mb-2"><label class="form-label" for="pub-answer"><?= e(__('admin.questions.publish.answer_html')) ?></label><textarea class="form-control" id="pub-answer" name="answer_html" rows="8" data-rich-editor><?= e(old('answer_html', $draft['answer_html'])) ?></textarea></div>
                <?php if ($draft['has_scholar_audio']): ?><div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="include_audio" name="include_audio" value="1"><label class="form-check-label" for="include_audio"><?= e(__('admin.questions.publish.include_audio')) ?></label></div><?php endif ?>
                <div class="d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary" name="status" value="published" <?= $question['visibility'] !== 'publishable' ? 'disabled' : '' ?>><i class="bi bi-globe2" aria-hidden="true"></i> <?= e(__('admin.questions.publish.submit')) ?></button>
                    <button type="submit" class="btn btn-outline-secondary" name="status" value="draft"><?= e(__('admin.questions.publish.draft')) ?></button>
                </div>
            </form>
            <?php endif ?>
        </div>
        <?php endif ?>

        <div class="row g-3 mb-3">
            <div class="col-12 col-md-6">
                <form method="post" action="<?= e(route('admin.questions.note', ['id' => $id])) ?>" class="df-card h-100">
                    <?= csrf_field() ?>
                    <h2 class="df-card__title h6"><i class="bi bi-journal-text" aria-hidden="true"></i> <?= e(__('admin.questions.note.title')) ?></h2>
                    <textarea class="form-control mb-2" name="body" rows="3" maxlength="5000" required placeholder="<?= e(__('admin.questions.note.placeholder')) ?>" aria-label="<?= e(__('admin.questions.note.title')) ?>"></textarea>
                    <button type="submit" class="btn btn-outline-primary btn-sm"><?= e(__('admin.questions.note.submit')) ?></button>
                </form>
            </div>
            <?php if (can('questions.change_status')): ?>
            <div class="col-12 col-md-6">
                <form method="post" action="<?= e(route('admin.questions.import_audio', ['id' => $id])) ?>" enctype="multipart/form-data" class="df-card h-100">
                    <?= csrf_field() ?>
                    <h2 class="df-card__title h6"><i class="bi bi-whatsapp" aria-hidden="true"></i> <?= e(__('admin.questions.import.title')) ?></h2>
                    <p class="small text-secondary"><?= e(__('admin.questions.import.intro')) ?></p>
                    <input type="file" class="form-control mb-2" name="audio" accept="audio/*,.ogg,.opus,.m4a,.mp3" required aria-label="<?= e(__('admin.questions.import.file')) ?>">
                    <select class="form-select mb-2" name="kind" aria-label="<?= e(__('admin.questions.import.kind')) ?>">
                        <option value="question"><?= e(__('admin.questions.import.kind_question')) ?></option>
                        <?php if (!empty($question['assigned_to'])): ?><option value="answer"><?= e(__('admin.questions.import.kind_answer')) ?></option><?php endif ?>
                    </select>
                    <button type="submit" class="btn btn-outline-primary btn-sm"><?= e(__('admin.questions.import.submit')) ?></button>
                </form>
            </div>
            <?php endif ?>
        </div>
    </div>

    <div class="col-12 col-xl-4">
        <div class="df-card mb-3">
            <h2 class="df-card__title h6"><i class="bi bi-person" aria-hidden="true"></i> <?= e(__('admin.questions.member')) ?></h2>
            <dl class="df-dl df-dl--compact mb-0">
                <dt><?= e(__('admin.users.col.name')) ?></dt><dd dir="auto"><?php if (can('users.manage')): ?><a href="<?= e(route('admin.users.show', ['id' => $question['user_id']])) ?>"><?= e($member['full_name'] ?? '') ?></a><?php else: ?><?= e($member['full_name'] ?? '') ?><?php endif ?><?php if (!empty($member['display_name'])): ?> <small class="text-secondary">(<?= e($member['display_name']) ?>)</small><?php endif ?></dd>
                <?php if (!empty($member['phone'])): ?><dt><?= e(__('admin.users.col.phone')) ?></dt><dd class="ltr"><?= e($member['phone']) ?></dd><?php endif ?>
                <?php if (!empty($question['whatsapp_number'])): ?><dt>WhatsApp</dt><dd class="ltr"><a href="https://wa.me/<?= e(ltrim((string) $question['whatsapp_number'], '+')) ?>" target="_blank" rel="noopener"><?= e($question['whatsapp_number']) ?></a></dd><?php endif ?>
                <?php if (!empty($member['email'])): ?><dt><?= e(__('admin.users.col.email')) ?></dt><dd class="ltr"><?= e($member['email']) ?></dd><?php endif ?>
                <dt><?= e(__('admin.users.col.country')) ?></dt><dd><?= e(!empty($member['country']) ? (\Locale::getDisplayRegion('-' . $member['country'], locale()) ?: $member['country']) : '—') ?><?= !empty($member['city']) ? ' · ' . e($member['city']) : '' ?></dd>
                <dt><?= e(__('admin.questions.col.channel')) ?></dt><dd><?= e(__('questions.channels.' . $question['answer_channel'])) ?> · <?= e(__('admin.questions.sources.' . $question['source'])) ?></dd>
                <dt><?= e(__('questions.create.visibility')) ?></dt><dd><?= e(__('questions.create.visibility_' . $question['visibility'])) ?></dd>
                <dt><?= e(__('admin.questions.col.language')) ?></dt><dd class="ltr"><?= e(__('questions.languages.' . $question['spoken_language'])) ?> → <?= e(__('questions.languages.' . $question['answer_language'])) ?></dd>
                <dt><?= e(__('admin.questions.col.rubrique')) ?></dt><dd><?= e($question['rubrique_name']) ?></dd>
                <dt><?= e(__('admin.questions.col.date')) ?></dt><dd><?= e(format_date((string) $question['created_at'], true)) ?> <small class="text-secondary">(<?= e(time_ago((string) $question['created_at'])) ?>)</small></dd>
                <?php if (!empty($question['requested_scholar_name'])): ?><dt><?= e(__('admin.questions.requested_scholar')) ?></dt><dd><i class="bi bi-star-fill text-warning" aria-hidden="true"></i> <?= e($question['requested_scholar_name']) ?></dd><?php endif ?>
                <?php if ($question['assigned_to']): ?><dt><?= e(__('admin.questions.col.scholar')) ?></dt><dd><?= e($question['scholar_name']) ?> <small class="text-secondary"><?= e(format_date($question['assigned_at'], true)) ?></small></dd><?php endif ?>
                <?php if ($question['answered_at']): ?><dt><?= e(__('admin.questions.answered_at')) ?></dt><dd><?= e(format_date($question['answered_at'], true)) ?><?php if ($question['first_response_minutes'] !== null): ?> · <?= e(__('admin.questions.answered_in', ['hours' => round(((int) $question['first_response_minutes']) / 60, 1)])) ?><?php endif ?></dd><?php endif ?>
                <?php if ($question['rejection_reason']): ?><dt><?= e(__('admin.questions.rejection_reason')) ?></dt><dd><?= e($question['rejection_reason']) ?></dd><?php endif ?>
                <?php if ($question['user_found_helpful'] !== null): ?><dt><?= e(__('admin.questions.feedback')) ?></dt><dd><?= e((int) $question['user_found_helpful'] === 1 ? __('common.yes') : __('common.no')) ?></dd><?php endif ?>
            </dl>
        </div>

        <div class="df-card mb-3">
            <h2 class="df-card__title h6"><i class="bi bi-clock-history" aria-hidden="true"></i> <?= e(__('admin.questions.timeline.title')) ?></h2>
            <ol class="df-timeline-admin small">
                <?php foreach ($timeline as $event): ?>
                <li class="kind-<?= e($event['kind']) ?>">
                    <p class="mb-0"><?= e($event['text']) ?><?php if ($event['actor']): ?> <span class="text-secondary"><?= e(__('scholar.question.by', ['name' => $event['actor']])) ?></span><?php endif ?></p>
                    <?php if ($event['comment']): ?><p class="mb-0 text-secondary fst-italic" dir="auto"><?= e($event['comment']) ?></p><?php endif ?>
                    <small class="text-secondary"><?= e(format_date($event['at'], true)) ?></small>
                </li>
                <?php endforeach ?>
            </ol>
        </div>

        <?php if (!empty($question['whatsapp'])): ?>
        <div class="df-card mb-3">
            <h2 class="df-card__title h6"><i class="bi bi-whatsapp" aria-hidden="true"></i> <?= e(__('admin.questions.whatsapp_log')) ?></h2>
            <ul class="df-list small mb-0">
                <?php foreach ($question['whatsapp'] as $w): ?>
                <li><span><i class="bi bi-arrow-<?= $w['direction'] === 'in' ? 'down-left' : 'up-right' ?>" aria-hidden="true"></i> <?= e(__('admin.questions.whatsapp_' . $w['direction'])) ?> · <?= e($w['type']) ?> · <span class="ltr"><?= e($w['phone'] ?? '') ?></span></span><small><?= e($w['actor'] ?? '') ?> · <?= e(format_date((string) $w['created_at'], true)) ?></small></li>
                <?php endforeach ?>
            </ul>
        </div>
        <?php endif ?>

        <?php if (can('questions.delete')): ?>
        <div class="df-card df-card--danger">
            <h2 class="df-card__title h6"><i class="bi bi-trash" aria-hidden="true"></i> <?= e(__('admin.questions.delete.title')) ?></h2>
            <p class="small text-secondary"><?= e(__('admin.questions.delete.intro')) ?></p>
            <?= $this->partial('admin/partials/post-button', ['url' => route('admin.questions.destroy', ['id' => $id]), 'label' => __('admin.questions.delete.submit'), 'icon' => 'trash', 'class' => 'btn btn-sm btn-outline-danger', 'confirm' => __('admin.questions.delete.confirm')]) ?>
        </div>
        <?php endif ?>
    </div>
</div>
<?php $this->endSection(); ?>
