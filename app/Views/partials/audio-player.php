<?php
/**
 * Lecteur audio maison (§4.2.2) : lecture/pause, progression, vitesse 0,75× à 2×. Amélioré par audio-player.js ;
 * sans JavaScript, l'élément <audio> natif reste utilisable.
 *
 * @var string $src URL de l'audio (route media.audio)
 * @var int|null $duration Durée en secondes (affichage initial)
 * @var string|null $mime
 * @var string|null $label Libellé accessible
 */
$duration ??= null;
$mime ??= null;
$label ??= __('questions.detail.my_question');
$format = static fn (?int $s): string => $s === null ? '--:--' : sprintf('%d:%02d', intdiv($s, 60), $s % 60);
?>
<div class="df-player" data-audio-player data-label-play="<?= e(__('questions.player.play')) ?>" data-label-pause="<?= e(__('questions.player.pause')) ?>" dir="ltr" role="group" aria-label="<?= e($label) ?>">
    <audio preload="metadata" src="<?= e($src) ?>" <?= $mime ? 'type="' . e($mime) . '"' : '' ?> controls class="df-player__native"></audio>
    <button type="button" class="df-player__toggle" aria-label="<?= e(__('questions.player.play')) ?>" hidden><i class="bi bi-play-fill" aria-hidden="true"></i></button>
    <div class="df-player__body" hidden>
        <input type="range" class="df-player__seek" min="0" max="<?= (int) ($duration ?? 100) ?>" value="0" step="1" aria-label="<?= e(__('questions.player.seek')) ?>">
        <div class="df-player__times"><span class="df-player__current">0:00</span><span class="df-player__duration"><?= e($format($duration)) ?></span></div>
    </div>
    <div class="dropdown df-player__speed" hidden>
        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" aria-label="<?= e(__('questions.player.speed')) ?>">1×</button>
        <ul class="dropdown-menu dropdown-menu-end">
            <?php foreach (['0.75', '1', '1.25', '1.5', '2'] as $rate): ?>
            <li><button type="button" class="dropdown-item" data-rate="<?= e($rate) ?>"><?= e($rate) ?>×</button></li>
            <?php endforeach ?>
        </ul>
    </div>
</div>
