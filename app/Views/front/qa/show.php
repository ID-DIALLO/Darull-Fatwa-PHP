<?php
/**
 * Détail d'une Q/R publique (§4.8) : question reformulée, réponse (texte, audio du professeur, références),
 * JSON-LD QAPage, partage, favoris, « poser une question similaire », Q/R liées.
 *
 * @var \App\Core\View $this
 * @var array<string, mixed> $publication
 * @var list<array<string, mixed>> $related
 * @var callable $formatter
 * @var bool $isFavorite
 * @var int $favorites
 * @var list<array<string, mixed>> $comments
 * @var list<array<string, mixed>> $pending
 * @var string $askUrl
 */
$this->extend('layouts/front');
$this->section('title', e($publication['title']));
$this->section('description', e($publication['meta_description'] ?: excerpt((string) $publication['question_text'], 160)));
$url = url(route('qa.show', ['slug' => $publication['slug']]));
$jsonLd = [
    '@context' => 'https://schema.org', '@type' => 'QAPage',
    'mainEntity' => [
        '@type' => 'Question', 'name' => $publication['title'], 'text' => strip_tags((string) $publication['question_text']), 'answerCount' => 1, 'dateCreated' => $publication['published_at'],
        'acceptedAnswer' => ['@type' => 'Answer', 'text' => strip_tags((string) $publication['answer_html']), 'dateCreated' => $publication['published_at'], 'url' => $url . '#answer', 'author' => ['@type' => $publication['scholar_name'] ? 'Person' : 'Organization', 'name' => $publication['scholar_name'] ?: __('common.site.name')]],
    ],
];
$this->section('head');
?>
<script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
<?php $this->endSection(); ?>
<?php $this->section('scripts'); ?>
<script src="<?= e(asset('assets/js/audio-player.js')) ?>" type="module"></script>
<?php $this->endSection(); ?>
<?php $this->section('content'); ?>
<?= $this->partial('partials/breadcrumb', ['items' => [['label' => __('qa.index.title'), 'url' => route('qa.index')], ['label' => $publication['rubrique_name'], 'url' => route('qa.index', ['rubrique' => $publication['rubrique_id']])], ['label' => excerpt((string) $publication['title'], 60)]]]) ?>
<article class="df-section df-qa">
    <div class="container">
        <div class="df-measure-wide mx-auto">
            <p class="df-qa-card__rubrique"><img src="<?= e(asset((string) $publication['rubrique_icon'])) ?>" alt="" width="24" height="24"> <a href="<?= e(route('rubriques.show', ['slug' => $publication['rubrique_slug']])) ?>"><?= e($publication['rubrique_name']) ?></a></p>
            <h1 class="df-title" dir="auto"><?= e($publication['title']) ?></h1>
            <p class="text-secondary small"><i class="bi bi-calendar3" aria-hidden="true"></i> <?= e(format_date($publication['published_at'])) ?> · <i class="bi bi-eye" aria-hidden="true"></i> <?= e(trans_choice('qa.show.views', (int) $publication['views'])) ?></p>
            <section class="df-qa__question df-card" aria-labelledby="qa-question">
                <h2 class="h5 df-card__title" id="qa-question"><i class="bi bi-question-circle" aria-hidden="true"></i> <?= e(__('qa.show.question')) ?></h2>
                <div class="df-prose" dir="auto"><?= $publication['question_text'] /* HTML purifié à l'enregistrement */ ?></div>
                <p class="small text-secondary mb-0"><i class="bi bi-shield-check" aria-hidden="true"></i> <?= e(__('qa.show.anonymous_notice')) ?></p>
            </section>
            <section class="df-qa__answer df-answer mt-4" id="answer" aria-labelledby="qa-answer">
                <h2 class="h5 df-card__title" id="qa-answer"><i class="bi bi-chat-left-quote" aria-hidden="true"></i> <?= e(__('qa.show.answer')) ?></h2>
                <?php if ($publication['scholar_name']): ?>
                <p class="df-answer__scholar">
                    <?php if ($publication['scholar_photo']): ?><img class="df-avatar" src="<?= e($publication['scholar_photo']) ?>" alt="" width="48" height="48"><?php endif ?>
                    <span><a href="<?= e(route('scholars.show', ['slug' => \App\Models\ScholarProfile::slug(['id' => $publication['scholar_user_id'], 'full_name' => $publication['scholar_name']])])) ?>"><?= e($publication['scholar_name']) ?></a><?php if ($publication['scholar_title']): ?><br><small class="text-secondary"><?= e($publication['scholar_title']) ?></small><?php endif ?></span>
                </p>
                <?php endif ?>
                <?php if (!empty($publication['audio_uuid'])): ?>
                <div class="mb-3"><?= $this->partial('partials/audio-player', ['src' => route('media.audio', ['uuid' => $publication['audio_uuid']]), 'duration' => $publication['audio_duration'] !== null ? (int) $publication['audio_duration'] : null, 'mime' => $publication['audio_mime'], 'label' => __('qa.show.audio')]) ?></div>
                <?php endif ?>
                <div class="df-prose" dir="auto"><?= $publication['answer_html'] /* HTML purifié à l'enregistrement */ ?></div>
                <?php if ($publication['references'] !== []): ?>
                <h3 class="h6 mt-3"><?= e(__('qa.show.references')) ?></h3>
                <ul class="df-msg__refs"><?php foreach ($publication['references'] as $ref): ?><li><i class="bi bi-bookmark" aria-hidden="true"></i> <?= e($formatter($ref)) ?></li><?php endforeach ?></ul>
                <?php endif ?>
                <p class="small text-secondary mt-3 mb-0"><?= e(__('front.disclaimer')) ?></p>
            </section>
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mt-4">
                <?= $this->partial('partials/share', ['url' => $url, 'title' => $publication['title']]) ?>
                <div class="d-flex flex-wrap gap-2">
                    <?= $this->partial('partials/favorite-button', ['type' => 'qa', 'id' => (int) $publication['id'], 'active' => $isFavorite, 'count' => $favorites]) ?>
                    <a class="btn btn-sm btn-gold" href="<?= e($askUrl) ?>"><i class="bi bi-mic-fill" aria-hidden="true"></i> <?= e(__('qa.show.ask_similar')) ?></a>
                </div>
            </div>
        </div>
    </div>
</article>
<?= $this->partial('partials/comments', ['comments' => $comments, 'pending' => $pending, 'action' => route('qa.comment', ['slug' => $publication['slug']])]) ?>
<?php if ($related !== []): ?>
<section class="df-section df-section--cream">
    <div class="container">
        <h2 class="df-section__title"><?= e(__('qa.show.related')) ?></h2>
        <div class="row g-3"><?php foreach ($related as $qa): ?><div class="col-12 col-md-6 col-lg-3"><?= $this->partial('partials/qa-card', ['qa' => $qa, 'showRubrique' => false]) ?></div><?php endforeach ?></div>
    </div>
</section>
<?php endif ?>
<?php $this->endSection(); ?>
