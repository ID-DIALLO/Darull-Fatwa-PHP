<?php
/**
 * Bandeau « contenu non disponible dans cette langue » (§7.1) : affiché quand la traduction manque
 * et que la langue par défaut est utilisée.
 *
 * @var string|null $fallbackLocale
 */
if (empty($fallbackLocale) || $fallbackLocale === locale()) {
    return;
}
?>
<div class="container pt-3">
    <div class="alert alert-info d-flex align-items-center gap-2 mb-0" role="status">
        <i class="bi bi-translate" aria-hidden="true"></i>
        <span><?= e(__('common.content_unavailable', ['language' => __('common.lang.' . locale()), 'fallback' => __('common.lang.' . $fallbackLocale)])) ?></span>
    </div>
</div>
