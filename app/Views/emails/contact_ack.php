<?php
/** Accusé de réception du formulaire de contact. @var \App\Core\View $this @var string $mailLocale */
$this->extend('layouts/email');
$this->section('title', __('emails.contact_ack.title', [], $mailLocale));
?>
<?php $this->section('content'); ?>
<p style="margin:0 0 12px;"><?= e(__('emails.common.greeting', ['name' => $name], $mailLocale)) ?></p>
<p style="margin:0 0 16px;"><?= e(__('emails.contact_ack.body', [], $mailLocale)) ?></p>
<p style="margin:0 0 6px;color:#4A5A54;font-size:14px;"><?= e(__('emails.contact_ack.copy', [], $mailLocale)) ?> <strong><?= e($subject_label) ?></strong></p>
<blockquote style="margin:0;padding:12px 16px;border-inline-start:4px solid #C9A24B;background:#F7F1E1;border-radius:8px;" dir="auto"><?= nl2br(e($message)) ?></blockquote>
<?php $this->endSection(); ?>
