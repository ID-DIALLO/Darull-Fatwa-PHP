<?php
/** @var \App\Core\View $this @var string $name @var string $url @var string $code @var int $days @var string $role @var string $mailLocale */
$this->extend('layouts/email');
$this->section('title', __('emails.staff_invitation.title', [], $mailLocale));
?>
<?php $this->section('content'); ?>
<p style="margin:0 0 12px;"><?= e(__('emails.common.greeting', ['name' => $name], $mailLocale)) ?></p>
<p style="margin:0 0 12px;"><?= e(__('emails.staff_invitation.body', ['role' => $role], $mailLocale)) ?></p>
<p style="margin:0 0 6px;font-size:14px;color:#4A5A54;"><?= e(__('emails.staff_invitation.code_label', [], $mailLocale)) ?></p>
<p style="margin:0 0 16px;font-size:28px;font-weight:700;letter-spacing:.3em;color:#0B5D45;" dir="ltr"><?= e(substr($code, 0, 3) . ' ' . substr($code, 3)) ?></p>
<?= $this->partial('emails/_button', ['url' => $url, 'label' => __('emails.staff_invitation.button', [], $mailLocale)]) ?>
<p style="margin:0 0 8px;font-size:14px;color:#4A5A54;"><?= e(__('emails.staff_invitation.steps', [], $mailLocale)) ?></p>
<p style="margin:0;font-size:14px;color:#4A5A54;"><?= e(__('emails.staff_invitation.expires', ['days' => $days], $mailLocale)) ?></p>
<?php $this->endSection(); ?>
