<?php
/** @var \App\Core\View $this @var string $name @var string $url @var int $hours @var string $mailLocale */
$this->extend('layouts/email');
$this->section('title', __('emails.verify_email.title', [], $mailLocale));
?>
<?php $this->section('content'); ?>
<p style="margin:0 0 12px;"><?= e(__('emails.common.greeting', ['name' => $name], $mailLocale)) ?></p>
<p style="margin:0 0 12px;"><?= e(__('emails.verify_email.body', [], $mailLocale)) ?></p>
<?= $this->partial('emails/_button', ['url' => $url, 'label' => __('emails.verify_email.button', [], $mailLocale)]) ?>
<p style="margin:0 0 8px;font-size:14px;color:#4A5A54;"><?= e(__('emails.verify_email.expires', ['hours' => $hours], $mailLocale)) ?></p>
<p style="margin:0;font-size:14px;color:#4A5A54;"><?= e(__('emails.common.ignore', [], $mailLocale)) ?></p>
<?php $this->endSection(); ?>
