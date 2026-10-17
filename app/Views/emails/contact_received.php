<?php
/** E-mail à l'administration : nouveau message de contact. @var \App\Core\View $this @var string $mailLocale */
$this->extend('layouts/email');
$this->section('title', __('emails.contact_received.title', [], $mailLocale));
?>
<?php $this->section('content'); ?>
<p style="margin:0 0 12px;"><?= e(__('emails.contact_received.body', [], $mailLocale)) ?></p>
<table role="presentation" cellpadding="6" cellspacing="0" style="margin:0 0 16px;font-size:15px;">
    <tr><td style="color:#4A5A54;"><?= e(__('emails.contact_received.from', [], $mailLocale)) ?></td><td dir="auto"><strong><?= e($name) ?></strong></td></tr>
    <?php if (!empty($email)): ?><tr><td style="color:#4A5A54;">E-mail</td><td dir="ltr"><a href="mailto:<?= e($email) ?>" style="color:#0B5D45;"><?= e($email) ?></a></td></tr><?php endif ?>
    <?php if (!empty($phone)): ?><tr><td style="color:#4A5A54;">Tél.</td><td dir="ltr"><?= e($phone) ?></td></tr><?php endif ?>
    <tr><td style="color:#4A5A54;">Objet</td><td><?= e($subject_label) ?></td></tr>
</table>
<blockquote style="margin:0 0 16px;padding:12px 16px;border-inline-start:4px solid #C9A24B;background:#F7F1E1;border-radius:8px;" dir="auto"><?= nl2br(e($message)) ?></blockquote>
<?php if (!empty($email)): ?>
<?= $this->partial('emails/_button', ['url' => 'mailto:' . $email . '?subject=' . rawurlencode('Re: ' . $subject_label), 'label' => __('emails.contact_received.reply', [], $mailLocale)]) ?>
<?php endif ?>
<?php $this->endSection(); ?>
