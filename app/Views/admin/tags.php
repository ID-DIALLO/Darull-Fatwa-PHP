<?php
/**
 * Tags du blog par langue.
 *
 * @var \App\Core\View $this
 * @var list<array<string, mixed>> $tags
 */
$this->extend('layouts/admin');
$this->section('title', __('admin.tags.title'));
$this->section('subtitle', __('admin.tags.intro'));
?>
<?php $this->section('content'); ?>
<?php if ($tags === []): ?><?= $this->partial('admin/partials/empty') ?><?php else: ?>
<div class="df-table-wrap">
    <table class="table df-table">
        <thead><tr><th><?= e(__('validation.attributes.locale')) ?></th><th><?= e(__('admin.tags.col.name')) ?></th><th><?= e(__('admin.tags.col.slug')) ?></th><th><?= e(__('admin.tags.col.articles')) ?></th><th class="actions"></th></tr></thead>
        <tbody>
            <?php foreach ($tags as $t): ?>
            <tr>
                <td><?= e(strtoupper((string) $t['locale'])) ?></td>
                <td><form method="post" action="<?= e(route('admin.tags.update', ['id' => $t['id']])) ?>" class="input-group input-group-sm"><?= csrf_field() ?><input type="text" class="form-control" name="name" value="<?= e($t['name']) ?>" maxlength="60" aria-label="<?= e(__('admin.tags.col.name')) ?>" dir="auto"><button type="submit" class="btn btn-outline-primary"><?= e(__('admin.common.change')) ?></button></form></td>
                <td class="ltr"><span class="df-code"><?= e($t['slug']) ?></span></td>
                <td><?= (int) $t['articles_count'] ?></td>
                <td class="actions"><?= $this->partial('admin/partials/post-button', ['url' => route('admin.tags.destroy', ['id' => $t['id']]), 'label' => __('admin.common.delete'), 'icon' => 'trash', 'class' => 'btn btn-sm btn-outline-danger', 'confirm' => __('admin.common.delete_confirm')]) ?></td>
            </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</div>
<?php endif ?>
<?php $this->endSection(); ?>
