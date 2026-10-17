<?php
/** Double confirmation de l'abonnement à la newsletter. @var \App\Core\View $this @var string $mailLocale @var string $url @var string $unsubscribe_url */
$this->extend('layouts/email');
$this->section('title', __('emails.newsletter_confirm.title', [], $mailLocale));
?>
<?php $this->section('content'); ?>
<p style="margin:0 0 12px;"><?= e(__('emails.newsletter_confirm.body', [], $mailLocale)) ?></p>
<?= $this->partial('emails/_button', ['url' => $url, 'label' => __('emails.newsletter_confirm.button', [], $mailLocale)]) ?>
<p style="margin:0;font-size:13px;color:#4A5A54;"><?= e(__('emails.common.ignore', [], $mailLocale)) ?> <a href="<?= e($unsubscribe_url) ?>" style="color:#0B5D45;"><?= e(__('emails.newsletter_confirm.unsubscribe', [], $mailLocale)) ?></a></p>
<?php $this->endSection(); ?>
