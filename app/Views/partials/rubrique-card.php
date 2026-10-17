<?php
/**
 * Carte d'une rubrique : icône, nom, description courte, nombre de Q/R publiées, lien « Explorer ».
 *
 * @var array<string, mixed> $rubrique
 */
$url = route('rubriques.show', ['slug' => $rubrique['slug']]);
?>
<article class="df-rubrique-card" style="--df-rubrique-color: <?= e($rubrique['color'] ?? '#0B5D45') ?>">
    <a class="df-rubrique-card__icon" href="<?= e($url) ?>" tabindex="-1" aria-hidden="true">
        <img src="<?= e(asset((string) $rubrique['icon'])) ?>" alt="" width="56" height="56" loading="lazy">
    </a>
    <div class="df-rubrique-card__body">
        <h3 class="df-rubrique-card__title"><a href="<?= e($url) ?>"><?= e($rubrique['name']) ?></a></h3>
        <p class="df-rubrique-card__text"><?= e($rubrique['short_description']) ?></p>
        <div class="df-rubrique-card__footer">
            <span class="df-rubrique-card__count"><i class="bi bi-patch-question" aria-hidden="true"></i> <?= e(trans_choice('home.rubriques.qa_count', (int) ($rubrique['qa_count'] ?? 0))) ?></span>
            <a class="df-rubrique-card__link" href="<?= e($url) ?>"><?= e(__('home.rubriques.explore')) ?> <i class="bi bi-arrow-right icon-dir" aria-hidden="true"></i></a>
        </div>
    </div>
</article>
