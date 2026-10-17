<?php
/** Réponse de l'équipe à un message de contact. @var \App\Core\View $this @var string $name @var string $original @var string $reply @var string $subject_label @var string $mailLocale */
$this->extend('layouts/email');
$this->section('title', __('emails.contact_reply.title', [], $mailLocale));
?>
<?php $this->section('content'); ?>
<p style="margin:0 0 12px;"><?= e(__('emails.common.greeting', ['name' => $name], $mailLocale)) ?></p>
<p style="margin:0 0 12px;"><?= e(__('emails.contact_reply.body', ['subject' => $subject_label], $mailLocale)) ?></p>
<div style="margin:0 0 16px;padding:12px 16px;background:#F7F1E1;border-radius:10px;" dir="auto"><?= nl2br(e($reply)) ?></div>
<p style="margin:0 0 6px;font-size:13px;color:#4A5A54;"><?= e(__('emails.contact_reply.original', [], $mailLocale)) ?></p>
<blockquote style="margin:0;padding:8px 12px;border-inline-start:3px solid #C9A24B;color:#4A5A54;font-size:14px;" dir="auto"><?= nl2br(e($original)) ?></blockquote>
<?php $this->endSection(); ?>
