<?php
/**
 * Enregistreur vocal (§4.2.2), piloté par voice-recorder.js. Le champ caché « media_uuid » reçoit
 * l'identifiant de l'enregistrement une fois envoyé à POST /api/audio.
 *
 * @var string $name          Nom du champ caché (media_uuid)
 * @var int $maxSeconds
 * @var string|null $existingUuid
 * @var string|null $existingUrl
 * @var int|null $existingDuration
 */
$existingUuid ??= null;
$existingUrl ??= null;
$existingDuration ??= null;
$labels = [];
foreach (['record', 'pause', 'resume', 'stop', 'listen', 'delete', 'send', 'recording', 'paused', 'ready', 'uploading', 'uploaded', 'retrying', 'offline', 'failed', 'retry', 'too_short', 'almost_max', 'max_reached', 'unsupported', 'https_required', 'denied_title', 'denied', 'denied_android', 'denied_ios', 'fallback', 'draft_found', 'timer', 'level'] as $key) {
    $labels[$key] = __('questions.recorder.' . $key);
}
$labels['player_play'] = __('questions.player.play');
$labels['player_pause'] = __('questions.player.pause');
?>
<div class="df-recorder" data-voice-recorder
     data-upload-url="<?= e(route('api.audio.store')) ?>" data-delete-url="<?= e(route('api.audio.destroy', ['uuid' => '__UUID__'])) ?>"
     data-max-seconds="<?= (int) $maxSeconds ?>" data-min-seconds="2" data-field="<?= e($name) ?>"
     data-worker="<?= e(asset('assets/js/mp3-worker.js')) ?>" data-lame="<?= e(asset('vendor/lamejs/lame.min.js')) ?>" data-processor="<?= e(asset('assets/js/pcm-processor.js')) ?>"
     data-labels="<?= e(json_encode($labels, JSON_UNESCAPED_UNICODE)) ?>"
     <?= $existingUuid ? 'data-existing-uuid="' . e($existingUuid) . '" data-existing-url="' . e((string) $existingUrl) . '" data-existing-duration="' . (int) $existingDuration . '"' : '' ?>>
    <input type="hidden" name="<?= e($name) ?>" value="<?= e(old($name, $existingUuid ?? '')) ?>" data-recorder-field>

    <div class="df-recorder__main">
        <button type="button" class="df-recorder__mic" data-recorder-main aria-label="<?= e(__('questions.recorder.record')) ?>">
            <span class="df-recorder__pulse" aria-hidden="true"></span>
            <i class="bi bi-mic-fill" aria-hidden="true"></i>
        </button>
        <div class="df-recorder__status">
            <p class="df-recorder__timer" data-recorder-timer aria-live="off" dir="ltr">0:00</p>
            <canvas class="df-recorder__wave" data-recorder-wave width="320" height="48" aria-label="<?= e(__('questions.recorder.level')) ?>" role="img"></canvas>
            <p class="df-recorder__message" data-recorder-message aria-live="polite"><?= e(__('questions.create.voice_intro')) ?></p>
        </div>
    </div>

    <div class="df-recorder__controls" data-recorder-controls hidden>
        <button type="button" class="btn btn-outline-primary" data-recorder-pause><i class="bi bi-pause-fill" aria-hidden="true"></i> <span><?= e(__('questions.recorder.pause')) ?></span></button>
        <button type="button" class="btn btn-primary" data-recorder-stop><i class="bi bi-stop-fill" aria-hidden="true"></i> <?= e(__('questions.recorder.stop')) ?></button>
    </div>

    <div class="df-recorder__review" data-recorder-review hidden>
        <audio controls preload="metadata" class="df-recorder__preview" data-recorder-preview></audio>
        <div class="df-recorder__actions">
            <button type="button" class="btn btn-gold" data-recorder-send><i class="bi bi-cloud-arrow-up" aria-hidden="true"></i> <?= e(__('questions.recorder.send')) ?></button>
            <button type="button" class="btn btn-outline-danger" data-recorder-delete><i class="bi bi-trash3" aria-hidden="true"></i> <?= e(__('questions.recorder.delete')) ?></button>
        </div>
        <div class="progress df-recorder__progress" data-recorder-progress hidden role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" style="height: 8px"><div class="progress-bar bg-success" style="width: 0%"></div></div>
    </div>

    <div class="alert alert-warning df-recorder__help" data-recorder-help hidden role="alert"></div>
</div>
