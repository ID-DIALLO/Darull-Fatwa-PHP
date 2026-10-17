<?php
/** Confirmation de réception d'une question. @var \App\Core\View $this @var string $mailLocale @var string $name @var string $reference @var int $late_hours @var string $url */
$this->extend('layouts/email');
$this->section('title', __('emails.question_received.title', [], $mailLocale));
?>
<?php $this->section('content'); ?>
<p style="margin:0 0 12px;"><?= e(__('emails.common.greeting', ['name' => $name], $mailLocale)) ?></p>
<p style="margin:0 0 12px;"><?= e(__('emails.question_received.body', ['reference' => $reference, 'late_hours' => $late_hours], $mailLocale)) ?></p>
<p style="margin:0 0 4px;text-align:center;font-size:13px;color:#4A5A54;"><?= e(__('questions.confirmation.reference', [], $mailLocale)) ?></p>
<p style="margin:0 0 8px;text-align:center;font-size:26px;font-weight:700;color:#0B5D45;letter-spacing:.04em;" dir="ltr"><?= e($reference) ?></p>
<?= $this->partial('emails/_button', ['url' => $url, 'label' => __('emails.question_received.button', [], $mailLocale)]) ?>
<?php $this->endSection(); ?>
