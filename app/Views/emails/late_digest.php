<?php
/** Récapitulatif quotidien des questions en retard (équipe). @var \App\Core\View $this @var string $mailLocale @var string $name @var int $count @var int $hours @var list<string> $references @var string $url */
$this->extend('layouts/email');
$this->section('title', __('scholar.late_digest.title', [], $mailLocale));
?>
<?php $this->section('content'); ?>
<p style="margin:0 0 12px;"><?= e(__('emails.common.greeting', ['name' => $name], $mailLocale)) ?></p>
<p style="margin:0 0 12px;"><?= e(__('scholar.late_digest.body', ['count' => $count, 'hours' => $hours], $mailLocale)) ?></p>
<ul style="margin:0 0 16px;padding-inline-start:20px;" dir="ltr">
    <?php foreach (array_slice($references, 0, 50) as $reference): ?><li><?= e($reference) ?></li><?php endforeach ?>
    <?php if (count($references) > 50): ?><li>…</li><?php endif ?>
</ul>
<?= $this->partial('emails/_button', ['url' => $url, 'label' => __('scholar.late_digest.button', [], $mailLocale)]) ?>
<?php $this->endSection(); ?>
