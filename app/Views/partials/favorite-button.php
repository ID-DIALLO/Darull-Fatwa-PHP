<?php
/**
 * Bouton « favori » (membre connecté) ou invitation à se connecter.
 *
 * @var string $type article|qa
 * @var int $id
 * @var bool $active
 * @var int $count
 */
?>
<?php if (auth() !== null): ?>
<form method="post" action="<?= e(route('favorites.toggle')) ?>" class="d-inline" data-favorite-form>
    <?= csrf_field() ?>
    <input type="hidden" name="type" value="<?= e($type) ?>"><input type="hidden" name="id" value="<?= (int) $id ?>">
    <button type="submit" class="btn btn-sm <?= $active ? 'btn-gold' : 'btn-outline-primary' ?>" aria-pressed="<?= $active ? 'true' : 'false' ?>" data-label-add="<?= e(__('front.favorites.add')) ?>" data-label-remove="<?= e(__('front.favorites.remove')) ?>">
        <i class="bi bi-bookmark<?= $active ? '-fill' : '' ?>" aria-hidden="true"></i> <span data-favorite-label><?= e($active ? __('front.favorites.remove') : __('front.favorites.add')) ?></span> <span class="badge text-bg-light" data-favorite-count><?= (int) $count ?></span>
    </button>
</form>
<?php else: ?>
<a class="btn btn-sm btn-outline-primary" href="<?= e(route('auth.login', ['redirect' => \App\Core\View::shared('request')?->uri() ?? '/'])) ?>"><i class="bi bi-bookmark" aria-hidden="true"></i> <?= e(__('front.favorites.login')) ?></a>
<?php endif ?>
