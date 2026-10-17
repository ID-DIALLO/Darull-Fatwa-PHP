<?php
/**
 * Badge de statut d'une question : pastille colorée + libellé traduit (jamais la couleur seule, §8.4).
 *
 * @var string $status
 */
use App\Models\Question;

$color = Question::BADGES[$status] ?? 'secondary';
$icons = ['warning' => 'hourglass-split', 'info' => 'arrow-repeat', 'success' => 'check-circle-fill', 'danger' => 'x-circle-fill', 'secondary' => 'archive'];
?>
<span class="df-badge df-badge--<?= e($color) ?>"><i class="bi bi-<?= e($icons[$color] ?? 'circle-fill') ?>" aria-hidden="true"></i> <?= e(__('questions.status.' . $status)) ?></span>
