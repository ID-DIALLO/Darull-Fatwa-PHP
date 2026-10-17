<?php
/**
 * Confirmation d'envoi : référence DF-AAAA-NNNNNN, délai indicatif, suivi, WhatsApp (§4.2.1).
 *
 * @var \App\Core\View $this
 * @var array<string, mixed> $question
 * @var int $lateHours
 * @var string|null $whatsappLink
 */
$this->extend('layouts/front');
$this->section('title', __('questions.confirmation.title'));
$this->section('robots', 'noindex, nofollow');
?>
<?php $this->section('content'); ?>
<section class="df-section df-pattern df-confirmation">
    <div class="container">
        <div class="df-card df-confirmation__card text-center">
            <i class="bi bi-check-circle-fill df-confirmation__icon" aria-hidden="true"></i>
            <h1 class="df-title"><?= e(__('questions.confirmation.title')) ?></h1>
            <p class="df-lead"><?= e(__('questions.confirmation.thanks')) ?></p>
            <p class="mb-1 text-secondary"><?= e(__('questions.confirmation.reference')) ?></p>
            <p class="df-confirmation__reference" dir="ltr"><?= e($question['reference']) ?></p>
            <p class="small text-secondary"><?= e(__('questions.confirmation.keep')) ?></p>
            <p><?= e(__('questions.confirmation.delay', ['hours' => $lateHours])) ?></p>
            <div class="d-flex flex-wrap justify-content-center gap-2 mt-3">
                <a class="btn btn-primary" href="<?= e(route('account.questions.show', ['reference' => $question['reference']])) ?>"><i class="bi bi-eye" aria-hidden="true"></i> <?= e(__('questions.confirmation.follow')) ?></a>
                <?php if ($whatsappLink !== null): ?>
                <a class="btn btn-success" href="<?= e($whatsappLink) ?>" target="_blank" rel="noopener"><i class="bi bi-whatsapp" aria-hidden="true"></i> <?= e(__('questions.confirmation.whatsapp')) ?></a>
                <?php endif ?>
                <a class="btn btn-outline-primary" href="<?= e(route('questions.create')) ?>"><?= e(__('questions.confirmation.another')) ?></a>
            </div>
            <?php if ($whatsappLink !== null): ?>
            <p class="small text-secondary mt-3 mb-0"><?= e(__('questions.confirmation.whatsapp_help')) ?></p>
            <?php endif ?>
        </div>
    </div>
</section>
<?php $this->endSection(); ?>
