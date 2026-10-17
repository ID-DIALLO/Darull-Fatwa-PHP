<?php
/** @var \App\Core\View $this @var string $name @var string $mailLocale */
$this->extend('layouts/email');
$this->section('title', __('emails.account_deleted.title', [], $mailLocale));
?>
<?php $this->section('content'); ?>
<p style="margin:0 0 12px;"><?= e(__('emails.common.greeting', ['name' => $name], $mailLocale)) ?></p>
<p style="margin:0;"><?= e(__('emails.account_deleted.body', [], $mailLocale)) ?></p>
<?php $this->endSection(); ?>
