<?php
/**
 * Gabarit de « Mon espace » : barre latérale (menu) + contenu. Étend le gabarit public.
 * Sections attendues : title, content ; facultative : subtitle.
 *
 * @var \App\Core\View $this
 */
$this->extend('layouts/front');
$this->section('robots', 'noindex, nofollow');
$user = auth() ?? [];
$current = \App\Core\App::instance()->router()->current()?->getName() ?? '';
$items = [
    ['route' => 'account.dashboard', 'icon' => 'speedometer2', 'label' => __('account.nav.dashboard'), 'match' => 'account.dashboard'],
    ['route' => 'account.questions', 'icon' => 'chat-left-dots', 'label' => __('account.nav.questions'), 'match' => 'account.questions'],
    ['route' => 'account.favorites', 'icon' => 'bookmark', 'label' => __('account.nav.favorites'), 'match' => 'account.favorites'],
    ['route' => 'account.forum', 'icon' => 'chat-square-text', 'label' => __('account.nav.forum'), 'match' => 'account.forum'],
    ['route' => 'account.ideas', 'icon' => 'lightbulb', 'label' => __('account.nav.ideas'), 'match' => 'account.ideas'],
    ['route' => 'account.notifications', 'icon' => 'bell', 'label' => __('account.nav.notifications'), 'match' => 'account.notifications'],
    ['route' => 'account.profile', 'icon' => 'person', 'label' => __('account.nav.profile'), 'match' => 'account.profile'],
    ['route' => 'account.security', 'icon' => 'shield-lock', 'label' => __('account.nav.security'), 'match' => 'account.security'],
    ['route' => 'account.data', 'icon' => 'database', 'label' => __('account.nav.data'), 'match' => 'account.data'],
];
$this->section('head');
echo $this->yield('head');
?>
<link rel="stylesheet" href="<?= e(asset('vendor/intl-tel-input/css/intlTelInput.min.css')) ?>">
<?php $this->endSection(); ?>
<?php $this->section('scripts'); echo $this->yield('scripts'); ?>
<script src="<?= e(asset('vendor/intl-tel-input/js/intlTelInputWithUtils.min.js')) ?>" defer></script>
<script src="<?= e(asset('assets/js/auth.js')) ?>" type="module"></script>
<?php $this->endSection(); ?>

<?php $this->section('content'); ?>
<section class="df-account">
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
                            <p class="df-account__role mb-0"><?= e(__('account.roles.' . ($user['role'] ?? 'member'))) ?></p>
                        </div>
                    </div>
                    <nav class="df-account__nav" aria-label="<?= e(__('account.nav.label')) ?>">
                        <ul class="nav flex-lg-column">
                            <?php foreach ($items as $item): if (!route_exists($item['route'])) continue; $active = str_starts_with($current, $item['match']); ?>
                            <li class="nav-item">
                                <a class="nav-link<?= $active ? ' active' : '' ?>" href="<?= e(route($item['route'])) ?>" <?= $active ? 'aria-current="page"' : '' ?>>
                                    <i class="bi bi-<?= e($item['icon']) ?>" aria-hidden="true"></i> <span><?= e($item['label']) ?></span>
                                </a>
                            </li>
                            <?php endforeach ?>
                            <?php if (can('scholar.dashboard')): ?>
                            <li class="nav-item"><a class="nav-link" href="<?= e(route('scholar.dashboard')) ?>"><i class="bi bi-mortarboard" aria-hidden="true"></i> <span><?= e(__('nav.scholar_space')) ?></span></a></li>
                            <?php endif ?>
                            <?php if (can('admin.access')): ?>
                            <li class="nav-item"><a class="nav-link" href="<?= e(route('admin.dashboard')) ?>"><i class="bi bi-gear" aria-hidden="true"></i> <span><?= e(__('nav.admin')) ?></span></a></li>
                            <?php endif ?>
                            <li class="nav-item">
                                <form method="post" action="<?= e(route('auth.logout')) ?>">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="nav-link w-100 text-start"><i class="bi bi-box-arrow-right icon-dir" aria-hidden="true"></i> <span><?= e(__('account.nav.logout')) ?></span></button>
                                </form>
                            </li>
                        </ul>
                    </nav>
                </div>
            </aside>
            <div class="col-12 col-lg-9">
                <?php if (!empty($user['email']) && empty($user['email_verified_at'])): ?>
                <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2" role="status">
                    <span><i class="bi bi-envelope-exclamation" aria-hidden="true"></i> <?= e(__('auth.verify.banner')) ?></span>
                    <form method="post" action="<?= e(route('account.verification.resend')) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-outline-primary"><?= e(__('auth.verify.resend')) ?></button>
                    </form>
                </div>
                <?php endif ?>
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
