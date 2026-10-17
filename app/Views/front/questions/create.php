<?php
/**
 * Assistant « Poser une question » en 3 étapes (§4.2.1) : thème → ma question (vocal / écrit) → réception.
 * Sans JavaScript, les trois étapes s'affichent à la suite et le formulaire reste utilisable.
 *
 * @var \App\Core\View $this
 * @var list<array<string, mixed>> $rubriques
 * @var array<string, mixed> $user
 * @var int|null $preselected
 * @var string $mode
 * @var int $limit
 * @var int $used
 * @var int $maxSeconds
 * @var list<array<string, mixed>> $scholars
 * @var int|null $requestedScholar
 * @var int $maxMb
 * @var string|null $whatsapp
 * @var string $defaultChannel
 */
use App\Controllers\Front\QuestionController;

$this->extend('layouts/front');
$this->section('title', __('questions.create.title'));
$this->section('description', __('questions.create.subtitle'));
$this->section('robots', 'noindex, follow');
$this->section('body_class', 'df-page-ask');
$remaining = max(0, $limit - $used);
$selectedRubrique = (int) old('rubrique_id', $preselected ?? 0);
$channel = old('answer_channel', $defaultChannel);
$this->section('head'); ?>
<link rel="stylesheet" href="<?= e(asset('vendor/intl-tel-input/css/intlTelInput.min.css')) ?>">
<?php $this->endSection(); $this->section('scripts'); ?>
<script src="<?= e(asset('vendor/intl-tel-input/js/intlTelInputWithUtils.min.js')) ?>" defer></script>
<script src="<?= e(asset('assets/js/auth.js')) ?>" type="module"></script>
<script src="<?= e(asset('assets/js/voice-recorder.js')) ?>" type="module"></script>
<script src="<?= e(asset('assets/js/question-wizard.js')) ?>" type="module"></script>
<?php $this->endSection(); ?>
<?php $this->section('content'); ?>
<?= $this->partial('partials/breadcrumb', ['items' => [['label' => __('questions.create.title')]]]) ?>
<section class="df-section df-ask">
    <div class="container">
        <div class="df-ask__inner">
            <header class="text-center mb-4">
                <h1 class="df-title"><?= e(__('questions.create.title')) ?></h1>
                <p class="text-secondary mb-2"><?= e(__('questions.create.subtitle')) ?></p>
                <?php if ($remaining > 0): ?>
                <p class="small text-secondary mb-0"><?= e(__('questions.create.quota', ['remaining' => $remaining, 'limit' => $limit])) ?></p>
                <?php endif ?>
            </header>

            <?php if ($remaining === 0): ?>
            <div class="alert alert-warning" role="alert"><i class="bi bi-hourglass" aria-hidden="true"></i> <?= e(__('questions.create.quota_reached', ['limit' => $limit])) ?></div>
            <?php else: ?>

            <ol class="df-wizard__steps" data-wizard-steps aria-label="<?= e(__('questions.create.title')) ?>">
                <?php foreach ([1 => 'step1', 2 => 'step2', 3 => 'step3'] as $n => $key): ?>
                <li class="df-wizard__step<?= $n === 1 ? ' is-active' : '' ?>" data-step-indicator="<?= $n ?>" aria-current="<?= $n === 1 ? 'step' : 'false' ?>">
                    <span class="df-wizard__num" aria-hidden="true"><?= $n ?></span>
                    <span class="df-wizard__label"><?= e(__('questions.create.' . $key)) ?></span>
                </li>
                <?php endforeach ?>
            </ol>
            <div class="progress df-wizard__progress" role="progressbar" aria-label="<?= e(__('questions.create.step', ['n' => 1, 'total' => 3])) ?>" aria-valuemin="1" aria-valuemax="3" aria-valuenow="1" style="height: 6px"><div class="progress-bar bg-success" data-wizard-bar style="width: 33%"></div></div>

            <form method="post" action="<?= e(route('questions.store')) ?>" enctype="multipart/form-data" class="df-wizard df-form" data-question-wizard data-mode="<?= e($mode) ?>" novalidate
                  data-labels="<?= e(json_encode(['voice' => __('questions.create.summary_voice', ['duration' => '{d}']), 'text' => __('questions.create.summary_text'), 'mixed' => __('questions.create.summary_mixed'), 'none' => __('questions.create.summary_none'), 'empty' => __('questions.errors.empty'), 'step' => __('questions.create.step', ['n' => '{n}', 'total' => 3]), 'chosen' => __('questions.create.attach_chosen', ['name' => '{name}'])], JSON_UNESCAPED_UNICODE)) ?>">
                <?= csrf_field() ?>

                <!-- Étape 1 : thème -->
                <fieldset class="df-wizard__panel" data-wizard-panel="1">
                    <legend class="df-wizard__title"><?= e(__('questions.create.choose_rubrique')) ?></legend>
                    <?= $this->partial('partials/field-error', ['name' => 'rubrique_id']) ?>
                    <div class="df-rubrique-choices">
                        <?php foreach ($rubriques as $rubrique): ?>
                        <label class="df-rubrique-choice" style="--df-rubrique-color: <?= e($rubrique['color'] ?? '#0B5D45') ?>">
                            <input type="radio" name="rubrique_id" value="<?= (int) $rubrique['id'] ?>" <?= $selectedRubrique === (int) $rubrique['id'] ? 'checked' : '' ?> required data-rubrique-name="<?= e($rubrique['name']) ?>">
                            <img src="<?= e(asset((string) $rubrique['icon'])) ?>" alt="" width="48" height="48">
                            <span class="df-rubrique-choice__name"><?= e($rubrique['name']) ?></span>
                            <span class="df-rubrique-choice__desc"><?= e($rubrique['short_description']) ?></span>
                        </label>
                        <?php endforeach ?>
                    </div>
                    <?php if ($scholars !== []): ?>
                    <div class="mt-3">
                        <label class="form-label" for="requested_scholar_id"><?= e(__('questions.create.scholar')) ?></label>
                        <select class="form-select" id="requested_scholar_id" name="requested_scholar_id" data-scholar-select aria-describedby="requested_scholar_help">
                            <option value=""><?= e(__('questions.create.scholar_any')) ?></option>
                            <?php foreach ($scholars as $s): ?>
                            <option value="<?= (int) $s['id'] ?>" data-rubriques="<?= e((string) $s['rubrique_ids']) ?>" <?= (int) old('requested_scholar_id', $requestedScholar ?? 0) === (int) $s['id'] ? 'selected' : '' ?>><?= e(trim(($s['title'] ? $s['title'] . ' ' : '') . $s['full_name'])) ?><?= $s['rubriques'] ? ' — ' . e($s['rubriques']) : '' ?></option>
                            <?php endforeach ?>
                        </select>
                        <div class="form-text" id="requested_scholar_help"><?= e(__('questions.create.scholar_help')) ?></div>
                    </div>
                    <?php endif ?>
                    <div class="df-wizard__nav">
                        <span></span>
                        <button type="button" class="btn btn-primary btn-lg" data-wizard-next><?= e(__('questions.create.next')) ?> <i class="bi bi-arrow-right icon-dir" aria-hidden="true"></i></button>
                    </div>
                </fieldset>

                <!-- Étape 2 : ma question -->
                <fieldset class="df-wizard__panel" data-wizard-panel="2" hidden>
                    <legend class="df-wizard__title"><?= e(__('questions.create.step2')) ?></legend>
                    <div class="df-tabs" role="tablist">
                        <button type="button" class="df-tab<?= $mode === 'voice' ? ' is-active' : '' ?>" role="tab" aria-selected="<?= $mode === 'voice' ? 'true' : 'false' ?>" data-mode-tab="voice" aria-controls="panel-voice" id="tab-voice"><i class="bi bi-mic-fill" aria-hidden="true"></i> <?= e(__('questions.create.tab_voice')) ?></button>
                        <button type="button" class="df-tab<?= $mode === 'text' ? ' is-active' : '' ?>" role="tab" aria-selected="<?= $mode === 'text' ? 'true' : 'false' ?>" data-mode-tab="text" aria-controls="panel-text" id="tab-text"><i class="bi bi-pencil" aria-hidden="true"></i> <?= e(__('questions.create.tab_text')) ?></button>
                    </div>
                    <input type="hidden" name="input_mode" value="<?= e($mode) ?>" data-mode-field>

                    <div id="panel-voice" role="tabpanel" aria-labelledby="tab-voice" data-mode-panel="voice" <?= $mode === 'voice' ? '' : 'hidden' ?>>
                        <?= $this->partial('partials/voice-recorder', ['name' => 'media_uuid', 'maxSeconds' => $maxSeconds]) ?>
                        <?= $this->partial('partials/field-error', ['name' => 'media_uuid']) ?>
                        <div class="row g-3 mt-1">
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="spoken_language"><?= e(__('questions.create.spoken_language')) ?></label>
                                <select class="form-select" id="spoken_language" name="spoken_language">
                                    <?php foreach (QuestionController::SPOKEN as $code): ?>
                                    <option value="<?= e($code) ?>" <?= old('spoken_language', locale()) === $code ? 'selected' : '' ?>><?= e(__('questions.languages.' . $code)) ?></option>
                                    <?php endforeach ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="audio_file"><?= e(__('questions.create.attach_file')) ?></label>
                                <input type="file" class="form-control<?= error_for('audio_file') !== null ? ' is-invalid' : '' ?>" id="audio_file" name="audio_file" accept="audio/*,.ogg,.opus,.m4a,.mp3,.webm,.wav,.aac" data-audio-file>
                                <div class="form-text"><?= e(__('questions.create.attach_help', ['max' => $maxMb])) ?></div>
                                <?= $this->partial('partials/field-error', ['name' => 'audio_file']) ?>
                            </div>
                        </div>
                        <details class="mt-3" <?= old('body') ? 'open' : '' ?>>
                            <summary class="fw-semibold"><i class="bi bi-pencil-square" aria-hidden="true"></i> <?= e(__('questions.create.add_text')) ?></summary>
                            <div class="mt-2">
                                <label class="visually-hidden" for="body_voice"><?= e(__('questions.create.body_label')) ?></label>
                                <textarea class="form-control" id="body_voice" rows="4" dir="auto" data-body-mirror placeholder="<?= e(__('questions.create.body_placeholder')) ?>"><?= e(old('body')) ?></textarea>
                            </div>
                        </details>
                    </div>

                    <div id="panel-text" role="tabpanel" aria-labelledby="tab-text" data-mode-panel="text" <?= $mode === 'text' ? '' : 'hidden' ?>>
                        <p class="text-secondary"><?= e(__('questions.create.text_intro')) ?></p>
                        <div class="mb-3">
                            <label class="form-label" for="title"><?= e(__('questions.create.title_label')) ?> <span class="text-secondary fw-normal">(<?= e(__('common.optional')) ?>)</span></label>
                            <input type="text" class="form-control" id="title" name="title" value="<?= e(old('title')) ?>" maxlength="200" dir="auto" aria-describedby="title-help">
                            <div class="form-text" id="title-help"><?= e(__('questions.create.title_help')) ?></div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label" for="body"><?= e(__('questions.create.body_label')) ?></label>
                            <textarea class="form-control<?= error_for('body') !== null ? ' is-invalid' : '' ?>" id="body" name="body" rows="7" maxlength="10000" dir="auto" data-body placeholder="<?= e(__('questions.create.body_placeholder')) ?>"><?= e(old('body')) ?></textarea>
                            <?= $this->partial('partials/field-error', ['name' => 'body']) ?>
                        </div>
                    </div>
                    <p class="small text-secondary mt-3 mb-0"><i class="bi bi-shield-lock" aria-hidden="true"></i> <?= e(__('questions.create.confidential')) ?></p>
                    <div class="df-wizard__nav">
                        <button type="button" class="btn btn-outline-primary" data-wizard-prev><i class="bi bi-arrow-left icon-dir" aria-hidden="true"></i> <?= e(__('questions.create.previous')) ?></button>
                        <button type="button" class="btn btn-primary btn-lg" data-wizard-next><?= e(__('questions.create.next')) ?> <i class="bi bi-arrow-right icon-dir" aria-hidden="true"></i></button>
                    </div>
                </fieldset>

                <!-- Étape 3 : réception de la réponse -->
                <fieldset class="df-wizard__panel" data-wizard-panel="3" hidden>
                    <legend class="df-wizard__title"><?= e(__('questions.create.step3')) ?></legend>
                    <div class="mb-3">
                        <label class="form-label" for="answer_language"><?= e(__('questions.create.answer_language')) ?></label>
                        <select class="form-select" id="answer_language" name="answer_language">
                            <?php foreach (QuestionController::ANSWER as $code): ?>
                            <option value="<?= e($code) ?>" <?= old('answer_language', in_array($user['locale'], QuestionController::ANSWER, true) ? $user['locale'] : 'fr') === $code ? 'selected' : '' ?>><?= e(__('questions.languages.' . $code)) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <fieldset class="mb-3">
                        <legend class="form-label"><?= e(__('questions.create.channel')) ?></legend>
                        <?php foreach (['platform', 'whatsapp', 'both'] as $value): ?>
                        <div class="form-check df-choice">
                            <input class="form-check-input" type="radio" name="answer_channel" id="channel_<?= e($value) ?>" value="<?= e($value) ?>" <?= $channel === $value ? 'checked' : '' ?> data-channel>
                            <label class="form-check-label" for="channel_<?= e($value) ?>">
                                <strong><?= e(__('questions.create.channel_' . $value)) ?></strong>
                                <?php if ($value !== 'both'): ?><span class="d-block small text-secondary"><?= e(__('questions.create.channel_' . $value . '_help')) ?></span><?php endif ?>
                            </label>
                        </div>
                        <?php endforeach ?>
                    </fieldset>
                    <div data-whatsapp-wrapper <?= $channel === 'platform' ? 'hidden' : '' ?>>
                        <?= $this->partial('partials/phone-field', ['name' => 'whatsapp_number', 'label' => __('questions.create.whatsapp_number'), 'value' => $whatsapp, 'required' => true]) ?>
                    </div>
                    <fieldset class="mb-3">
                        <legend class="form-label"><?= e(__('questions.create.visibility')) ?></legend>
                        <div class="form-check df-choice">
                            <input class="form-check-input" type="radio" name="visibility" id="visibility_private" value="private" <?= old('visibility', 'private') === 'private' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="visibility_private"><strong><?= e(__('questions.create.visibility_private')) ?></strong><span class="d-block small text-secondary"><?= e(__('questions.create.visibility_private_help')) ?></span></label>
                        </div>
                        <div class="form-check df-choice">
                            <input class="form-check-input" type="radio" name="visibility" id="visibility_publishable" value="publishable" <?= old('visibility') === 'publishable' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="visibility_publishable"><strong><?= e(__('questions.create.visibility_publishable')) ?></strong><span class="d-block small text-secondary"><?= e(__('questions.create.visibility_publishable_help')) ?></span></label>
                        </div>
                    </fieldset>

                    <div class="df-card df-summary mb-3">
                        <h2 class="h6 df-title mb-2"><?= e(__('questions.create.summary')) ?></h2>
                        <dl class="df-dl mb-0">
                            <dt><?= e(__('questions.create.summary_rubrique')) ?></dt><dd data-summary-rubrique>—</dd>
                            <dt><?= e(__('questions.create.summary_type')) ?></dt><dd data-summary-type>—</dd>
                            <dt><?= e(__('questions.create.summary_answer')) ?></dt><dd data-summary-answer>—</dd>
                            <dt><?= e(__('questions.create.summary_channel')) ?></dt><dd data-summary-channel>—</dd>
                        </dl>
                    </div>
                    <div class="df-wizard__nav">
                        <button type="button" class="btn btn-outline-primary" data-wizard-prev><i class="bi bi-arrow-left icon-dir" aria-hidden="true"></i> <?= e(__('questions.create.previous')) ?></button>
                        <button type="submit" class="btn btn-gold btn-lg" data-wizard-submit><i class="bi bi-send" aria-hidden="true"></i> <?= e(__('questions.create.submit')) ?></button>
                    </div>
                </fieldset>
            </form>
            <?php endif ?>
        </div>
    </div>
</section>
<?php $this->endSection(); ?>
