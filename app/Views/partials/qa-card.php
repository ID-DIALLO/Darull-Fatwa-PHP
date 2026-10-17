<?php
/**
 * Carte d'une Q/R publique.
 *
 * @var array<string, mixed> $qa
 * @var bool|null $showRubrique
 */
$showRubrique ??= true;
?>
<article class="df-card df-qa-card h-100">
    <?php if ($showRubrique && !empty($qa['rubrique_name'])): ?><p class="df-qa-card__rubrique"><?php if (!empty($qa['rubrique_icon'])): ?><img src="<?= e(asset((string) $qa['rubrique_icon'])) ?>" alt="" width="20" height="20" loading="lazy"> <?php endif ?><?= e($qa['rubrique_name']) ?></p><?php endif ?>
    <h3 class="df-qa-card__title"><a href="<?= e(route('qa.show', ['slug' => $qa['slug']])) ?>" dir="auto"><?= e($qa['title']) ?></a></h3>
    <p class="df-qa-card__excerpt" dir="auto"><?= e(excerpt((string) $qa['question_text'], 140)) ?></p>
    <p class="df-qa-card__meta small text-secondary mb-0">
        <?php if (!empty($qa['audio_media_id'])): ?><span class="df-qa-card__audio"><i class="bi bi-headphones" aria-hidden="true"></i> <?= e(__('home.latest_qa.audio')) ?></span> · <?php endif ?>
        <span><i class="bi bi-calendar3" aria-hidden="true"></i> <?= e(format_date($qa['published_at'] ?? null)) ?></span>
        <?php if (isset($qa['views'])): ?> · <span><i class="bi bi-eye" aria-hidden="true"></i> <?= (int) $qa['views'] ?></span><?php endif ?>
    </p>
</article>
