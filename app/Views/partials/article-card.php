<?php
/**
 * Carte d'un article de blog (image, rubrique, titre, extrait, auteur, date, temps de lecture).
 *
 * @var array<string, mixed> $article
 */
$url = route('blog.show', ['slug' => $article['slug']]);
?>
<article class="df-card df-article-card h-100">
    <a class="df-article-card__cover" href="<?= e($url) ?>" tabindex="-1" aria-hidden="true">
        <?php if (!empty($article['cover_path'])): ?>
        <img src="<?= e($article['cover_path']) ?>" alt="" width="640" height="360" loading="lazy">
        <?php else: ?>
        <span class="df-article-card__placeholder df-pattern"><i class="bi bi-journal-text" aria-hidden="true"></i></span>
        <?php endif ?>
    </a>
    <div class="df-article-card__body">
        <?php if (!empty($article['rubrique_name'])): ?><p class="df-article-card__rubrique"><?= e($article['rubrique_name']) ?></p><?php endif ?>
        <h3 class="df-article-card__title"><a href="<?= e($url) ?>" dir="auto"><?= e($article['title']) ?></a></h3>
        <?php if (!empty($article['excerpt'])): ?><p class="df-article-card__excerpt" dir="auto"><?= e(excerpt((string) $article['excerpt'], 150)) ?></p><?php endif ?>
        <p class="df-article-card__meta">
            <?php if (!empty($article['author_name'])): ?><span><i class="bi bi-person" aria-hidden="true"></i> <?= e($article['author_name']) ?></span><?php endif ?>
            <?php if (!empty($article['published_at'])): ?><span><i class="bi bi-calendar3" aria-hidden="true"></i> <?= e(format_date((string) $article['published_at'])) ?></span><?php endif ?>
            <?php if (!empty($article['reading_minutes'])): ?><span><i class="bi bi-clock" aria-hidden="true"></i> <?= (int) $article['reading_minutes'] ?> min</span><?php endif ?>
        </p>
    </div>
</article>
