<?php
/**
 * Boutons de partage (§4.7) : WhatsApp, Facebook, X, Telegram, copier le lien. Aucun script en ligne.
 *
 * @var string $url URL absolue
 * @var string $title
 */
$text = rawurlencode($title . ' — ' . $url);
$encodedUrl = rawurlencode($url);
$links = [
    ['icon' => 'whatsapp', 'label' => 'WhatsApp', 'href' => 'https://wa.me/?text=' . $text],
    ['icon' => 'facebook', 'label' => 'Facebook', 'href' => 'https://www.facebook.com/sharer/sharer.php?u=' . $encodedUrl],
    ['icon' => 'twitter-x', 'label' => 'X', 'href' => 'https://twitter.com/intent/tweet?url=' . $encodedUrl . '&text=' . rawurlencode($title)],
    ['icon' => 'telegram', 'label' => 'Telegram', 'href' => 'https://t.me/share/url?url=' . $encodedUrl . '&text=' . rawurlencode($title)],
];
?>
<div class="df-share" role="group" aria-label="<?= e(__('front.share.label')) ?>">
    <span class="df-share__title"><i class="bi bi-share" aria-hidden="true"></i> <?= e(__('front.share.label')) ?></span>
    <?php foreach ($links as $link): ?>
    <a class="df-share__btn df-share__btn--<?= e($link['icon']) ?>" href="<?= e($link['href']) ?>" target="_blank" rel="noopener nofollow" aria-label="<?= e(__('front.share.on', ['network' => $link['label']])) ?>" title="<?= e($link['label']) ?>"><i class="bi bi-<?= e($link['icon']) ?>" aria-hidden="true"></i></a>
    <?php endforeach ?>
    <button type="button" class="df-share__btn df-share__btn--copy" data-copy-link="<?= e($url) ?>" data-copied-label="<?= e(__('front.share.copied')) ?>" aria-label="<?= e(__('front.share.copy')) ?>" title="<?= e(__('front.share.copy')) ?>"><i class="bi bi-link-45deg" aria-hidden="true"></i></button>
    <span class="df-share__status small text-success" role="status" aria-live="polite"></span>
</div>
