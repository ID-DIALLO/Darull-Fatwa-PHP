<?php
/**
 * Page d'attente des espaces professeur et administration (protégés par les rôles dès la Phase 1).
 *
 * @var \App\Core\View $this
 * @var string $area admin | scholar
 */
$this->extend('layouts/account');
$this->section('title', __('account.placeholder.' . $area . '_title'));
?>
<?php $this->section('content'); ?>
<div class="df-card">
    <p class="mb-0"><i class="bi bi-cone-striped text-warning" aria-hidden="true"></i> <?= e(__('account.placeholder.text')) ?></p>
</div>
<?php $this->endSection(); ?>
