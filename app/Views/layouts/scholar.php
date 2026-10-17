<?php
/**
 * Gabarit de l'espace professeur (§4.5) : barre latérale + contenu, sur le gabarit public.
 * Sections attendues : title, content ; facultative : subtitle, head, scripts.
 *
 * @var \App\Core\View $this
 */
$this->extend('layouts/front');
$this->section('robots', 'noindex, nofollow');
$this->section('hide_float_mic', '1'); // Le professeur ne pose pas de question depuis son espace
$user = auth() ?? [];
$current = \App\Core\App::instance()->router()->current()?->getName() ?? '';
$request = \App\Core\View::shared('request');
$file = $request instanceof \App\Core\Request ? (string) $request->query('file', '') : '';
$items = [
    ['url' => route('scholar.dashboard'), 'icon' => 'speedometer2', 'label' => __('scholar.nav.dashboard'), 'active' => $current === 'scholar.dashboard'],
    ['url' => route('scholar.questions'), 'icon' => 'inbox', 'label' => __('scholar.nav.mine'), 'active' => str_starts_with($current, 'scholar.questions') && $file !== 'a-prendre' && $file !== 'available'],
    ['url' => route('scholar.questions', ['file' => 'a-prendre']), 'icon' => 'hand-index-thumb', 'label' => __('scholar.nav.available'), 'active' => $current === 'scholar.questions' && ($file === 'a-prendre' || $file === 'available')],
    ['url' => route_exists('scholar.articles') ? route('scholar.articles') : null, 'icon' => 'journal-text', 'label' => __('scholar.nav.articles'), 'active' => str_starts_with($current, 'scholar.articles')],
    ['url' => route('scholar.profile'), 'icon' => 'person-badge', 'label' => __('scholar.nav.profile'), 'active' => str_starts_with($current, 'scholar.profile')],
];
$this->section('head');
echo $this->yield('head');
$this->endSection();
$this->section('scripts');
echo $this->yield('scripts');
$this->endSection();
?>
<?php $this->section('content'); ?>
<section class="df-account df-scholar">
    <div class="container">
        <div class="row g-4">
            <aside class="col-12 col-lg-3">
                <div class="df-card df-account__sidebar">
                    <div class="df-account__user">
                        <?php if (!empty($user['avatar_path'])): ?>
                        <img class="df-avatar" src="<?= e($user['avatar_path']) ?>" alt="" width="56" height="56">
                        <?php else: ?>
                        <span class="df-avatar df-avatar--initials" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) ($user['full_name'] ?? '?'), 0, 1))) ?></span>
                        <?php endif ?>
                        <div class="min-w-0">
                            <p class="df-account__name mb-0 text-truncate" dir="auto"><?= e($user['full_name'] ?? '') ?></p>
                            <p class="df-account__role mb-0"><?= e(__('scholar.title')) ?></p>
                        </div>
                    </div>
                    <nav class="df-account__nav" aria-label="<?= e(__('scholar.nav.label')) ?>">
                        <ul class="nav flex-lg-column">
                            <?php foreach ($items as $item): if ($item['url'] === null) continue; ?>
                            <li class="nav-item"><a class="nav-link<?= $item['active'] ? ' active' : '' ?>" href="<?= e($item['url']) ?>" <?= $item['active'] ? 'aria-current="page"' : '' ?>><i class="bi bi-<?= e($item['icon']) ?>" aria-hidden="true"></i> <span><?= e($item['label']) ?></span></a></li>
                            <?php endforeach ?>
                            <li class="nav-item"><a class="nav-link" href="<?= e(route('account.dashboard')) ?>"><i class="bi bi-person-circle" aria-hidden="true"></i> <span><?= e(__('nav.my_account')) ?></span></a></li>
                            <?php if (can('admin.access')): ?>
                            <li class="nav-item"><a class="nav-link" href="<?= e(route('admin.dashboard')) ?>"><i class="bi bi-gear" aria-hidden="true"></i> <span><?= e(__('nav.admin')) ?></span></a></li>
                            <?php endif ?>
                        </ul>
                    </nav>
                </div>
            </aside>
            <div class="col-12 col-lg-9">
                <header class="df-account__header">
                    <h1 class="df-title h2 mb-1"><?= $this->yield('title') ?></h1>
                    <?php if ($this->hasSection('subtitle')): ?><p class="text-secondary mb-0"><?= $this->yield('subtitle') ?></p><?php endif ?>
                </header>
                <?= $this->yield('content') ?>
            </div>
        </div>
    </div>
</section>
<?php $this->endSection(); ?>
