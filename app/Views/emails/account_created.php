<?php
/** Compte créé par l'administration : lien pour choisir son mot de passe. @var \App\Core\View $this @var string $name @var string $url @var int $minutes @var string $mailLocale */
$this->extend('layouts/email');
$this->section('title', __('emails.account_created.title', [], $mailLocale));
?>
<?php $this->section('content'); ?>
<p style="margin:0 0 12px;"><?= e(__('emails.common.greeting', ['name' => $name], $mailLocale)) ?></p>
<p style="margin:0 0 12px;"><?= e(__('emails.account_created.body', [], $mailLocale)) ?></p>
<?= $this->partial('emails/_button', ['url' => $url, 'label' => __('emails.account_created.button', [], $mailLocale)]) ?>
<p style="margin:0;font-size:14px;color:#4A5A54;"><?= e(__('emails.reset_password.expires', ['minutes' => $minutes], $mailLocale)) ?></p>
<?php $this->endSection(); ?>
