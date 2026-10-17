<?php
/**
 * En-tête collant (§6) : logo, menu (Accueil · Rubriques ▾ méga-menu · Questions-Réponses · Blog · Forum · Idées · Contact),
 * recherche, cloche, Connexion / Mon espace, bouton doré « Poser une question ». Mobile : menu plein écran (offcanvas).
 * Les entrées dont la route n'existe pas encore (phases suivantes) sont masquées.
 */
use App\Models\Rubrique;

$user = auth();
$current = \App\Core\App::instance()->router()->current()?->getName() ?? '';
$rubriques = Rubrique::allActive();
$items = [
    ['route' => 'home', 'label' => __('nav.home'), 'match' => 'home'],
    ['route' => 'qa.index', 'label' => __('nav.qa'), 'match' => 'qa.'],
    ['route' => 'blog.index', 'label' => __('nav.blog'), 'match' => 'blog.'],
    ['route' => 'forum.index', 'label' => __('nav.forum'), 'match' => 'forum.'],
    ['route' => 'ideas.index', 'label' => __('nav.ideas'), 'match' => 'ideas.'],
    ['route' => 'scholars.index', 'label' => __('nav.scholars'), 'match' => 'scholars.'],
    ['route' => 'contact', 'label' => __('nav.contact'), 'match' => 'contact'],
];
$items = array_values(array_filter($items, static fn (array $i): bool => route_exists($i['route'])));
$isActive = static fn (string $match): bool => $match === 'home' ? $current === 'home' : str_starts_with($current, $match);
$askUrl = route_exists('questions.create') ? route('questions.create') : route('auth.register');
?>
<header class="df-header">
    <div class="container">
        <div class="df-header__row">
            <a class="df-header__brand" href="<?= e(route('home')) ?>" aria-label="<?= e(__('common.site.name')) ?> — <?= e(__('nav.home')) ?>">
                <img src="<?= e(asset('assets/img/logo/darul-fatwa-logo.svg')) ?>" alt="Darul Fatwa – دار الفتوى – <?= e(__('common.site.slogan')) ?>" width="194" height="58">
            </a>

            <nav class="df-nav d-none d-lg-block" aria-label="<?= e(__('nav.main_nav')) ?>">
                <ul class="df-nav__list">
                    <li class="df-nav__item"><a class="df-nav__link<?= $isActive('home') ? ' is-active' : '' ?>" href="<?= e(route('home')) ?>" <?= $isActive('home') ? 'aria-current="page"' : '' ?>><?= e(__('nav.home')) ?></a></li>
                    <li class="df-nav__item df-nav__item--mega">
                        <button type="button" class="df-nav__link df-nav__toggle<?= $isActive('rubriques.') ? ' is-active' : '' ?>" aria-expanded="false" aria-controls="df-mega" data-mega-toggle>
                            <?= e(__('nav.rubriques')) ?> <i class="bi bi-chevron-down" aria-hidden="true"></i>
                        </button>
                        <div class="df-mega" id="df-mega" hidden>
                            <div class="df-mega__grid">
                                <?php foreach ($rubriques as $rubrique): ?>
                                <a class="df-mega__item" href="<?= e(route('rubriques.show', ['slug' => $rubrique['slug']])) ?>">
                                    <img src="<?= e(asset((string) $rubrique['icon'])) ?>" alt="" width="40" height="40" loading="lazy">
                                    <span>
                                        <strong><?= e($rubrique['name']) ?></strong>
                                        <small><?= e($rubrique['short_description']) ?></small>
                                    </span>
                                </a>
                                <?php endforeach ?>
                            </div>
                            <a class="df-mega__all" href="<?= e(route('rubriques.index')) ?>"><?= e(__('nav.rubriques_all')) ?> <i class="bi bi-arrow-right icon-dir" aria-hidden="true"></i></a>
                        </div>
                    </li>
                    <?php foreach (array_slice($items, 1) as $item): ?>
                    <li class="df-nav__item"><a class="df-nav__link<?= $isActive($item['match']) ? ' is-active' : '' ?>" href="<?= e(route($item['route'])) ?>" <?= $isActive($item['match']) ? 'aria-current="page"' : '' ?>><?= e($item['label']) ?></a></li>
                    <?php endforeach ?>
                </ul>
            </nav>

            <div class="df-header__actions">
                <?php if (route_exists('search')): ?>
                <a class="df-header__icon d-none d-lg-inline-flex" href="<?= e(route('search')) ?>" aria-label="<?= e(__('nav.search')) ?>" title="<?= e(__('nav.search')) ?>"><i class="bi bi-search" aria-hidden="true"></i></a>
                <?php endif ?>
                <?php if ($user !== null): ?>
                <?php if (route_exists('account.notifications')): ?>
                <a class="df-header__icon" href="<?= e(route('account.notifications')) ?>" aria-label="<?= e(__('nav.notifications')) ?>" data-notification-bell>
                    <i class="bi bi-bell" aria-hidden="true"></i><span class="df-header__badge" data-notification-count hidden>0</span>
                </a>
                <?php endif ?>
                <a class="btn btn-outline-primary btn-sm d-none d-md-inline-flex" href="<?= e(route('account.dashboard')) ?>"><i class="bi bi-person-circle" aria-hidden="true"></i> <?= e(__('nav.my_account')) ?></a>
                <?php else: ?>
                <a class="btn btn-outline-primary btn-sm d-none d-md-inline-flex" href="<?= e(route('auth.login')) ?>"><?= e(__('nav.login')) ?></a>
                <?php endif ?>
                <a class="btn btn-gold btn-sm df-header__ask" href="<?= e($askUrl) ?>"><i class="bi bi-mic-fill" aria-hidden="true"></i> <span><?= e(__('nav.ask')) ?></span></a>
                <button class="df-header__burger d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#df-offcanvas" aria-controls="df-offcanvas" aria-label="<?= e(__('nav.open_menu')) ?>">
                    <i class="bi bi-list" aria-hidden="true"></i>
                </button>
            </div>
        </div>
    </div>
</header>

<div class="offcanvas offcanvas-end df-offcanvas" tabindex="-1" id="df-offcanvas" aria-labelledby="df-offcanvas-title">
    <div class="offcanvas-header">
        <h2 class="offcanvas-title h5" id="df-offcanvas-title"><?= e(__('nav.menu')) ?></h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?= e(__('nav.close_menu')) ?>"></button>
    </div>
    <div class="offcanvas-body">
        <nav aria-label="<?= e(__('nav.main_nav')) ?>">
            <ul class="df-offcanvas__list">
                <li><a class="<?= $isActive('home') ? 'is-active' : '' ?>" href="<?= e(route('home')) ?>"><i class="bi bi-house-door" aria-hidden="true"></i> <?= e(__('nav.home')) ?></a></li>
                <li>
                    <a class="<?= $isActive('rubriques.') ? 'is-active' : '' ?>" href="<?= e(route('rubriques.index')) ?>"><i class="bi bi-grid" aria-hidden="true"></i> <?= e(__('nav.rubriques')) ?></a>
                    <ul class="df-offcanvas__sub">
                        <?php foreach ($rubriques as $rubrique): ?>
                        <li><a href="<?= e(route('rubriques.show', ['slug' => $rubrique['slug']])) ?>"><img src="<?= e(asset((string) $rubrique['icon'])) ?>" alt="" width="24" height="24" loading="lazy"> <?= e($rubrique['name']) ?></a></li>
                        <?php endforeach ?>
                    </ul>
                </li>
                <?php $icons = ['qa.index' => 'patch-question', 'blog.index' => 'journal-text', 'forum.index' => 'chat-square-text', 'ideas.index' => 'lightbulb', 'scholars.index' => 'mortarboard', 'contact' => 'envelope']; ?>
                <?php foreach (array_slice($items, 1) as $item): ?>
                <li><a class="<?= $isActive($item['match']) ? 'is-active' : '' ?>" href="<?= e(route($item['route'])) ?>"><i class="bi bi-<?= e($icons[$item['route']] ?? 'dot') ?>" aria-hidden="true"></i> <?= e($item['label']) ?></a></li>
                <?php endforeach ?>
                <?php if (route_exists('search')): ?>
                <li><a href="<?= e(route('search')) ?>"><i class="bi bi-search" aria-hidden="true"></i> <?= e(__('nav.search')) ?></a></li>
                <?php endif ?>
            </ul>
        </nav>
        <hr>
        <div class="d-grid gap-2">
            <a class="btn btn-gold" href="<?= e($askUrl) ?>"><i class="bi bi-mic-fill" aria-hidden="true"></i> <?= e(__('nav.ask')) ?></a>
            <?php if ($user !== null): ?>
            <a class="btn btn-outline-primary" href="<?= e(route('account.dashboard')) ?>"><i class="bi bi-person-circle" aria-hidden="true"></i> <?= e(__('nav.my_account')) ?></a>
            <form method="post" action="<?= e(route('auth.logout')) ?>" class="d-grid">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-link text-decoration-none"><?= e(__('nav.logout')) ?></button>
            </form>
            <?php else: ?>
            <a class="btn btn-outline-primary" href="<?= e(route('auth.login')) ?>"><?= e(__('nav.login')) ?></a>
            <a class="btn btn-link text-decoration-none" href="<?= e(route('auth.register')) ?>"><?= e(__('nav.register')) ?></a>
            <?php endif ?>
        </div>
        <div class="mt-3 d-flex justify-content-center">
            <?= $this->partial('partials/lang-switcher') ?>
        </div>
    </div>
</div>
