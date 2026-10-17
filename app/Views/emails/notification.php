<?php
/** E-mail générique d'une notification (titre + message + bouton). @var \App\Core\View $this @var string $mailLocale @var string $title @var string $message @var string $url @var string $name */
$this->extend('layouts/email');
$this->section('title', $title);
?>
<?php $this->section('content'); ?>
<p style="margin:0 0 12px;"><?= e(__('emails.common.greeting', ['name' => $name], $mailLocale)) ?></p>
<p style="margin:0 0 12px;" dir="auto"><?= e($message) ?></p>
<?= $this->partial('emails/_button', ['url' => $url, 'label' => __('emails.notification.button', [], $mailLocale)]) ?>
<?php $this->endSection(); ?>
