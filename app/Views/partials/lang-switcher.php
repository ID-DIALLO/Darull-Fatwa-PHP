<?php
/** Sélecteur de langue FR · ع · EN (mène à la même page dans l'autre langue). */
$alternates = alternate_urls();
?>
<nav class="df-lang-switcher" aria-label="<?= e(__('common.lang.switch')) ?>">
    <ul class="list-inline mb-0">
        <?php foreach (\App\Core\Lang::locales() as $code): ?>
        <li class="list-inline-item">
            <?php if ($code === locale()): ?>
            <span class="df-lang-switcher__link is-active" aria-current="true" title="<?= e(__('common.lang.current')) ?>" lang="<?= e($code) ?>"><?= e(__('common.lang.short.' . $code)) ?></span>
            <?php else: ?>
            <a class="df-lang-switcher__link" href="<?= e($alternates[$code] ?? '/' . $code) ?>" hreflang="<?= e($code) ?>" lang="<?= e($code) ?>" title="<?= e(__('common.lang.' . $code)) ?>"><?= e(__('common.lang.short.' . $code)) ?></a>
            <?php endif ?>
        </li>
        <?php endforeach ?>
    </ul>
</nav>
