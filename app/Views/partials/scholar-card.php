<?php
/**
 * Carte d'un professeur : photo (ou initiale), titre, nom, spécialités.
 *
 * @var array<string, mixed> $scholar
 */
use App\Models\ScholarProfile;

$url = route('scholars.show', ['slug' => ScholarProfile::slug($scholar)]);
$photo = $scholar['photo_path'] ?: $scholar['avatar_path'];
$name = trim(($scholar['title'] ? $scholar['title'] . ' ' : '') . $scholar['full_name']);
?>
<article class="df-scholar-card">
    <a href="<?= e($url) ?>" class="df-scholar-card__photo" tabindex="-1" aria-hidden="true">
        <?php if ($photo): ?>
        <img src="<?= e($photo) ?>" alt="" width="96" height="96" loading="lazy">
        <?php else: ?>
        <span class="df-avatar df-avatar--initials df-avatar--xl"><?= e(mb_strtoupper(mb_substr((string) $scholar['full_name'], 0, 1))) ?></span>
        <?php endif ?>
    </a>
    <h3 class="df-scholar-card__name"><a href="<?= e($url) ?>" dir="auto"><?= e($name) ?></a></h3>
    <?php if ($scholar['rubriques'] !== []): ?>
    <p class="df-scholar-card__specialties"><?= e(implode(' · ', array_map(static fn (array $r): string => (string) $r['name'], array_slice($scholar['rubriques'], 0, 3)))) ?></p>
    <?php endif ?>
    <a class="df-scholar-card__link" href="<?= e($url) ?>"><?= e(__('front.scholars.profile')) ?> <i class="bi bi-arrow-right icon-dir" aria-hidden="true"></i></a>
</article>
