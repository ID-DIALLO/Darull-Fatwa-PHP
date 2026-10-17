<?php
/**
 * Page d'accueil (§6) : sections dans l'ordre défini en base ; les sections sans contenu sont masquées.
 *
 * @var \App\Core\View $this
 * @var list<string> $sections
 * @var list<array<string, mixed>> $banners
 * @var list<array<string, mixed>> $rubriques
 * @var array<string, mixed>|null $quote
 * @var list<array<string, mixed>> $scholars
 * @var array{answered: int, members: int, articles: int, scholars: int}|null $stats
 * @var list<array<string, mixed>> $latestQa
 * @var list<array<string, mixed>> $articles
 * @var list<array<string, mixed>> $topics
 * @var list<array<string, mixed>> $ideas
 * @var list<array{label: string, ok: bool, detail: string}> $checks
 */
$this->extend('layouts/front');
$this->section('body_class', 'df-page-home');
// L'équipe (professeur, modérateur, administrateur) ne pose pas de question : ses boutons sont simplement absents.
// L'accès à l'espace professeur n'est jamais proposé sur les pages publiques (lien transmis par l'administration).
$askUrl = ask_url();
$voiceUrl = ask_url(['mode' => 'voice']);
$textUrl = ask_url(['mode' => 'text']);
$whatsapp = whatsapp_link(__('front.whatsapp.message'));
$this->section('head');
if ($banners !== []): ?>
<link rel="stylesheet" href="<?= e(asset('vendor/swiper/swiper-bundle.min.css')) ?>">
<link rel="preload" as="image" href="<?= e(asset((string) $banners[0]['image_desktop'])) ?>">
<?php endif;
$this->endSection();
$this->section('scripts');
if ($banners !== []): ?>
<script src="<?= e(asset('vendor/swiper/swiper-bundle.min.js')) ?>" defer></script>
<script src="<?= e(asset('assets/js/hero-slider.js')) ?>" type="module"></script>
<?php endif;
$this->endSection();
?>
<?php $this->section('content'); ?>
<?php
$sameAs = array_values(array_filter(array_map(static fn (string $k): ?string => setting($k), ['social_facebook', 'social_youtube', 'social_instagram', 'social_telegram', 'social_x', 'social_tiktok'])));
$organization = ['@type' => 'Organization', 'name' => __('common.site.name'), 'url' => url(route('home')), 'logo' => url('/assets/img/logo/darul-fatwa-logo.png'), 'email' => setting('contact_email')];
if (setting('contact_phone')) {
    $organization['telephone'] = setting('contact_phone');
}
if (setting('contact_address')) {
    $organization['address'] = ['@type' => 'PostalAddress', 'streetAddress' => setting('contact_address')];
}
if ($sameAs !== []) {
    $organization['sameAs'] = $sameAs;
}
echo json_ld($organization);
echo json_ld(['@type' => 'WebSite', 'name' => __('common.site.name'), 'url' => url(route('home')), 'inLanguage' => locale(), 'potentialAction' => ['@type' => 'SearchAction', 'target' => ['@type' => 'EntryPoint', 'urlTemplate' => url(route('search')) . '?q={search_term_string}'], 'query-input' => 'required name=search_term_string']]);
?>
<?php foreach ($sections as $code): ?>
<?php switch ($code):
    case 'hero': ?>
        <?= $this->partial('partials/hero-slider', ['banners' => $banners]) ?>
        <?php break;

    case 'voice_cta': ?>
        <section class="df-voice df-pattern">
            <div class="container">
                <div class="df-voice__inner">
                    <?php if ($voiceUrl !== null): ?>
                    <a class="df-voice__button" href="<?= e($voiceUrl) ?>" aria-label="<?= e(__('home.voice.button')) ?>">
                        <span class="df-voice__pulse" aria-hidden="true"></span>
                        <i class="bi bi-mic-fill" aria-hidden="true"></i>
                    </a>
                    <?php else: ?>
                    <span class="df-voice__button" aria-hidden="true"><span class="df-voice__pulse"></span><i class="bi bi-mic-fill"></i></span>
                    <?php endif ?>
                    <div class="df-voice__text">
                        <h2 class="df-voice__title"><?= e(__('home.voice.title')) ?></h2>
                        <p class="df-voice__subtitle"><?= e(__('home.voice.subtitle')) ?></p>
                        <p class="df-voice__languages"><i class="bi bi-translate" aria-hidden="true"></i> <?= e(__('home.voice.languages')) ?></p>
                        <div class="df-voice__actions">
                            <?php if ($voiceUrl !== null): ?>
                            <a class="btn btn-gold btn-lg" href="<?= e($voiceUrl) ?>"><i class="bi bi-mic-fill" aria-hidden="true"></i> <?= e(__('home.voice.button')) ?></a>
                            <a class="df-voice__link" href="<?= e($textUrl) ?>"><i class="bi bi-pencil" aria-hidden="true"></i> <?= e(__('home.voice.prefer_text')) ?></a>
                            <?php endif ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <?php break;

    case 'how_it_works': ?>
        <section class="df-section df-how">
            <div class="container">
                <h2 class="df-section__title text-center"><?= e(__('home.how.title')) ?></h2>
                <ol class="df-how__steps">
                    <?php foreach ([1 => 'person-plus', 2 => 'mic', 3 => 'mortarboard', 4 => 'chat-dots'] as $n => $icon): ?>
                    <li class="df-how__step">
                        <span class="df-how__number" aria-hidden="true"><?= $n ?></span>
                        <i class="bi bi-<?= e($icon) ?> df-how__icon" aria-hidden="true"></i>
                        <h3 class="df-how__title"><?= e(__('home.how.step' . $n)) ?></h3>
                        <p class="df-how__text"><?= e(__('home.how.step' . $n . '_text')) ?></p>
                    </li>
                    <?php endforeach ?>
                </ol>
            </div>
        </section>
        <?php break;

    case 'rubriques': if ($rubriques === []) break; ?>
        <section class="df-section df-section--cream">
            <div class="container">
                <div class="df-section__head">
                    <h2 class="df-section__title"><?= e(__('home.rubriques.title')) ?></h2>
                    <p class="df-section__subtitle"><?= e(__('home.rubriques.subtitle')) ?></p>
                </div>
                <div class="df-rubriques-grid">
                    <?php foreach ($rubriques as $rubrique): ?>
                    <?= $this->partial('partials/rubrique-card', ['rubrique' => $rubrique]) ?>
                    <?php endforeach ?>
                </div>
            </div>
        </section>
        <?php break;

    case 'latest_qa': if ($latestQa === []) break; ?>
        <section class="df-section">
            <div class="container">
                <div class="df-section__head df-section__head--row">
                    <h2 class="df-section__title"><?= e(__('home.latest_qa.title')) ?></h2>
                    <a class="btn btn-outline-primary" href="<?= e(route('qa.index')) ?>"><?= e(__('home.latest_qa.all')) ?></a>
                </div>
                <div class="row g-3">
                    <?php foreach ($latestQa as $qa): ?>
                    <div class="col-12 col-md-6 col-lg-4">
                        <article class="df-card df-qa-card h-100">
                            <p class="df-qa-card__rubrique"><img src="<?= e(asset((string) $qa['rubrique_icon'])) ?>" alt="" width="20" height="20" loading="lazy"> <?= e($qa['rubrique_name']) ?></p>
                            <h3 class="df-qa-card__title"><a href="<?= e(route('qa.show', ['slug' => $qa['slug']])) ?>" dir="auto"><?= e($qa['title']) ?></a></h3>
                            <p class="df-qa-card__excerpt" dir="auto"><?= e(excerpt((string) $qa['question_text'], 140)) ?></p>
                            <?php if (!empty($qa['audio_media_id'])): ?><span class="df-qa-card__audio"><i class="bi bi-headphones" aria-hidden="true"></i> <?= e(__('home.latest_qa.audio')) ?></span><?php endif ?>
                        </article>
                    </div>
                    <?php endforeach ?>
                </div>
            </div>
        </section>
        <?php break;

    case 'daily_quote': if ($quote === null) break; ?>
        <section class="df-section df-quote-section">
            <div class="container">
                <figure class="df-verse df-pattern mb-0">
                    <figcaption class="df-verse__label"><?= e(__('home.quote.title_' . $quote['type'])) ?></figcaption>
                    <blockquote class="mb-2">
                        <p lang="ar" dir="rtl" class="df-verse__text"><?= e($quote['arabic_text']) ?></p>
                    </blockquote>
                    <?php if (!empty($quote['translation'])): ?><p class="df-verse__translation"><?= e($quote['translation']) ?></p><?php endif ?>
                    <p class="df-verse__ref mb-3"><?= e($quote['reference']) ?></p>
                    <?php $shareText = trim($quote['arabic_text'] . "\n" . ($quote['translation'] ?? '') . "\n" . $quote['reference'] . "\n" . url('/' . locale())); ?>
                    <a class="btn btn-outline-light btn-sm" href="https://wa.me/?text=<?= e(rawurlencode($shareText)) ?>" target="_blank" rel="noopener"><i class="bi bi-whatsapp" aria-hidden="true"></i> <?= e(__('home.quote.share')) ?></a>
                </figure>
            </div>
        </section>
        <?php break;

    case 'latest_articles': if ($articles === []) break; ?>
        <section class="df-section df-section--cream">
            <div class="container">
                <div class="df-section__head df-section__head--row">
                    <h2 class="df-section__title"><?= e(__('home.blog.title')) ?></h2>
                    <a class="btn btn-outline-primary" href="<?= e(route('blog.index')) ?>"><?= e(__('home.blog.all')) ?></a>
                </div>
                <div class="row g-3">
                    <?php foreach ($articles as $article): ?>
                    <div class="col-12 col-md-4"><?= $this->partial('partials/article-card', ['article' => $article]) ?></div>
                    <?php endforeach ?>
                </div>
            </div>
        </section>
        <?php break;

    case 'scholars': if ($scholars === []) break; ?>
        <section class="df-section">
            <div class="container">
                <div class="df-section__head df-section__head--row">
                    <div>
                        <h2 class="df-section__title mb-1"><?= e(__('home.scholars.title')) ?></h2>
                        <p class="df-section__subtitle mb-0"><?= e(__('home.scholars.subtitle')) ?></p>
                    </div>
                    <a class="btn btn-outline-primary" href="<?= e(route('scholars.index')) ?>"><?= e(__('home.scholars.all')) ?></a>
                </div>
                <div class="df-scholars-strip">
                    <?php foreach ($scholars as $scholar): ?>
                    <?= $this->partial('partials/scholar-card', ['scholar' => $scholar]) ?>
                    <?php endforeach ?>
                </div>
            </div>
        </section>
        <?php break;

    case 'stats': if ($stats === null) break; ?>
        <section class="df-stats df-pattern">
            <div class="container">
                <h2 class="visually-hidden"><?= e(__('home.stats.title')) ?></h2>
                <div class="row g-3 text-center">
                    <?php foreach (['answered' => 'check2-circle', 'members' => 'people', 'articles' => 'journal-text', 'scholars' => 'mortarboard'] as $key => $icon): ?>
                    <div class="col-6 col-lg-3">
                        <div class="df-stats__item">
                            <i class="bi bi-<?= e($icon) ?>" aria-hidden="true"></i>
                            <p class="df-stats__value" data-counter="<?= (int) $stats[$key] ?>"><?= (int) $stats[$key] ?></p>
                            <p class="df-stats__label"><?= e(__('home.stats.' . $key)) ?></p>
                        </div>
                    </div>
                    <?php endforeach ?>
                </div>
            </div>
        </section>
        <?php break;

    case 'forum_ideas': if ($topics === [] && $ideas === []) break; ?>
        <section class="df-section">
            <div class="container">
                <div class="row g-4">
                    <?php if ($topics !== []): ?>
                    <div class="col-12 col-lg-6">
                        <div class="df-card h-100">
                            <div class="df-section__head df-section__head--row">
                                <h2 class="df-card__title mb-0"><i class="bi bi-chat-square-text" aria-hidden="true"></i> <?= e(__('home.forum_ideas.forum_title')) ?></h2>
                                <a class="small" href="<?= e(route('forum.index')) ?>"><?= e(__('home.forum_ideas.forum_all')) ?></a>
                            </div>
                            <ul class="df-list">
                                <?php foreach ($topics as $topic): ?>
                                <li><a href="<?= e(route('forum.topic', ['id' => $topic['id'], 'slug' => $topic['slug']])) ?>" dir="auto"><?= e($topic['title']) ?></a><small><?= e(trans_choice('home.forum_ideas.replies', (int) $topic['replies_count'])) ?> · <?= e(time_ago($topic['last_post_at'] ?? $topic['created_at'])) ?></small></li>
                                <?php endforeach ?>
                            </ul>
                        </div>
                    </div>
                    <?php endif ?>
                    <?php if ($ideas !== []): ?>
                    <div class="col-12 col-lg-6">
                        <div class="df-card h-100">
                            <div class="df-section__head df-section__head--row">
                                <h2 class="df-card__title mb-0"><i class="bi bi-lightbulb" aria-hidden="true"></i> <?= e(__('home.forum_ideas.ideas_title')) ?></h2>
                                <a class="small" href="<?= e(route('ideas.index')) ?>"><?= e(__('home.forum_ideas.ideas_all')) ?></a>
                            </div>
                            <ul class="df-list">
                                <?php foreach ($ideas as $idea): ?>
                                <li><a href="<?= e(route('ideas.show', ['id' => $idea['id']])) ?>" dir="auto"><?= e($idea['title']) ?></a><small><i class="bi bi-hand-thumbs-up" aria-hidden="true"></i> <?= e(trans_choice('home.forum_ideas.votes', (int) $idea['votes_count'])) ?></small></li>
                                <?php endforeach ?>
                            </ul>
                        </div>
                    </div>
                    <?php endif ?>
                </div>
            </div>
        </section>
        <?php break;

    case 'cta': ?>
        <section class="df-cta df-pattern">
            <div class="container text-center">
                <h2 class="df-cta__title"><?= e(__('home.cta.title')) ?></h2>
                <p class="df-cta__text"><?= e(__('home.cta.text')) ?></p>
                <div class="df-cta__actions">
                    <?php if ($askUrl !== null): ?><a class="btn btn-gold btn-lg" href="<?= e($askUrl) ?>"><i class="bi bi-mic-fill" aria-hidden="true"></i> <?= e(__('home.cta.ask')) ?></a><?php endif ?>
                    <?php if ($whatsapp !== null): ?><a class="btn btn-outline-light btn-lg" href="<?= e($whatsapp) ?>" target="_blank" rel="noopener"><i class="bi bi-whatsapp" aria-hidden="true"></i> <?= e(__('home.cta.whatsapp')) ?></a><?php endif ?>
                    <?php if (auth() === null): ?><a class="btn btn-outline-light btn-lg" href="<?= e(route('auth.register')) ?>"><?= e(__('home.cta.register')) ?></a><?php endif ?>
                </div>
            </div>
        </section>
        <?php break;

    case 'newsletter': ?>
        <section class="df-section df-newsletter">
            <div class="container">
                <div class="df-newsletter__inner df-card">
                    <div>
                        <h2 class="df-newsletter__title"><i class="bi bi-envelope-paper" aria-hidden="true"></i> <?= e(__('home.newsletter.title')) ?></h2>
                        <p class="df-newsletter__text mb-0"><?= e(__('home.newsletter.text')) ?></p>
                    </div>
                    <?= $this->partial('partials/newsletter-form') ?>
                </div>
            </div>
        </section>
        <?php break;
endswitch ?>
<?php endforeach ?>

<?php if ($checks !== []): ?>
<section class="df-section pt-0">
    <div class="container">
        <details class="df-card p-0">
            <summary class="p-3 fw-semibold"><?= e(__('home.debug.title')) ?></summary>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <tbody>
                    <?php foreach ($checks as $check): ?>
                        <tr>
                            <th scope="row" class="ps-3"><?= e($check['label']) ?></th>
                            <td><span class="df-status df-status--<?= $check['ok'] ? 'success' : 'danger' ?>"><i class="bi bi-<?= $check['ok'] ? 'check-circle-fill' : 'x-circle-fill' ?>" aria-hidden="true"></i> <?= e($check['ok'] ? __('common.yes') : __('common.no')) ?></span></td>
                            <td class="text-secondary small pe-3"><?= e($check['detail']) ?></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </details>
    </div>
</section>
<?php endif ?>
<?php $this->endSection(); ?>
