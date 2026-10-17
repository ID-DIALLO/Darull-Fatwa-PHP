<?php
/**
 * Gabarit du back-office (§4.6) : barre latérale (offcanvas sous 992 px), en-tête, contenu.
 * Sections : title, content ; facultatives : subtitle, actions (HTML), head, scripts, breadcrumbs.
 *
 * @var \App\Core\View $this
 */
use App\Core\Database;

$locale = locale();
$dir = text_dir($locale);
$user = auth() ?? [];
$current = \App\Core\App::instance()->router()->current()?->getName() ?? '';
$title = $this->hasSection('title') ? $this->yield('title') : e(__('admin.title'));
$counts = ['questions' => 0, 'reviews' => 0, 'contacts' => 0];
try {
    $counts['questions'] = (int) Database::value("SELECT COUNT(*) FROM questions WHERE status = 'new' AND deleted_at IS NULL");
    $counts['reviews'] = (int) Database::value("SELECT COUNT(DISTINCT question_id) FROM question_messages WHERE kind = 'answer' AND review_status = 'pending'");
    $counts['contacts'] = (int) Database::value("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'");
} catch (\Throwable) {
    // Compteurs facultatifs
}
$menu = [
    ['items' => [
        ['route' => 'admin.dashboard', 'icon' => 'speedometer2', 'label' => __('admin.nav.dashboard'), 'match' => ['admin.dashboard']],
        ['route' => 'admin.questions', 'icon' => 'chat-left-dots', 'label' => __('admin.nav.questions'), 'match' => ['admin.questions'], 'can' => 'questions.view_all', 'badge' => $counts['questions'] + $counts['reviews']],
    ]],
    ['label' => __('admin.nav.group_people'), 'items' => [
        ['route' => 'admin.users', 'icon' => 'people', 'label' => __('admin.nav.users'), 'match' => ['admin.users'], 'can' => 'users.manage'],
        ['route' => 'admin.scholars', 'icon' => 'mortarboard', 'label' => __('admin.nav.scholars'), 'match' => ['admin.scholars'], 'can' => 'scholars.manage'],
    ]],
    ['label' => __('admin.nav.group_content'), 'items' => [
        ['route' => 'admin.rubriques', 'icon' => 'grid', 'label' => __('admin.nav.rubriques'), 'match' => ['admin.rubriques'], 'can' => 'rubriques.manage'],
        ['route' => 'admin.qa', 'icon' => 'patch-question', 'label' => __('admin.nav.qa'), 'match' => ['admin.qa'], 'can' => 'qa.manage'],
        ['route' => 'admin.articles', 'icon' => 'journal-text', 'label' => __('admin.nav.blog'), 'match' => ['admin.articles', 'admin.tags', 'admin.comments'], 'can' => 'articles.publish'],
        ['route' => 'admin.forum', 'icon' => 'chat-square-text', 'label' => __('admin.nav.forum'), 'match' => ['admin.forum', 'admin.reports'], 'can' => 'forum.moderate'],
        ['route' => 'admin.ideas', 'icon' => 'lightbulb', 'label' => __('admin.nav.ideas'), 'match' => ['admin.ideas'], 'can' => 'ideas.moderate'],
        ['route' => 'admin.banners', 'icon' => 'images', 'label' => __('admin.nav.banners'), 'match' => ['admin.banners'], 'can' => 'banners.manage'],
        ['route' => 'admin.home_sections', 'icon' => 'house-gear', 'label' => __('admin.nav.home'), 'match' => ['admin.home_sections'], 'can' => 'home.manage'],
        ['route' => 'admin.daily_quotes', 'icon' => 'book', 'label' => __('admin.nav.daily_quotes'), 'match' => ['admin.daily_quotes'], 'can' => 'home.manage'],
        ['route' => 'admin.pages', 'icon' => 'file-earmark-text', 'label' => __('admin.nav.pages'), 'match' => ['admin.pages'], 'can' => 'pages.manage'],
    ]],
    ['label' => __('admin.nav.group_communication'), 'items' => [
        ['route' => 'admin.contact', 'icon' => 'envelope-open', 'label' => __('admin.nav.contact'), 'match' => ['admin.contact'], 'can' => 'contact.manage', 'badge' => $counts['contacts']],
        ['route' => 'admin.newsletter', 'icon' => 'newspaper', 'label' => __('admin.nav.newsletter'), 'match' => ['admin.newsletter'], 'can' => 'newsletter.manage'],
    ]],
    ['label' => __('admin.nav.group_system'), 'items' => [
        ['route' => 'admin.settings', 'icon' => 'sliders', 'label' => __('admin.nav.settings'), 'match' => ['admin.settings', 'admin.cache'], 'can' => 'settings.manage'],
        ['route' => 'admin.activity_log', 'icon' => 'clock-history', 'label' => __('admin.nav.activity_log'), 'match' => ['admin.activity_log'], 'can' => 'activity_log.view'],
        ['route' => 'admin.backups', 'icon' => 'hdd-stack', 'label' => __('admin.nav.backups'), 'match' => ['admin.backups'], 'role' => 'superadmin'],
    ]],
];
$isActive = static function (array $item) use ($current): bool {
    foreach ($item['match'] as $prefix) {
        if ($current === $prefix || str_starts_with($current, $prefix . '.')) {
            return true;
        }
    }

    return false;
};
?>
<!doctype html>
<html lang="<?= e($locale) ?>" dir="<?= e($dir) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= $title ?> | <?= e(__('admin.title')) ?> — <?= e(__('common.site.name')) ?></title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="theme-color" content="#073D2E">
    <link rel="icon" href="/assets/img/favicons/favicon.ico" sizes="32x32">
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('assets/img/logo/darul-fatwa-icone.svg')) ?>">
    <link rel="stylesheet" href="<?= e(asset($dir === 'rtl' ? 'vendor/bootstrap/css/bootstrap.rtl.min.css' : 'vendor/bootstrap/css/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
    <?= $dir === 'rtl'
        ? styles(['assets/css/fonts.css', 'assets/css/variables.css', 'assets/css/app.css', 'assets/css/rtl.css'], 'assets/dist/app-rtl.css')
        : styles(['assets/css/fonts.css', 'assets/css/variables.css', 'assets/css/app.css'], 'assets/dist/app.css') ?>
    <link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>">
    <?= $this->yield('head') ?>
</head>
<body class="df-body df-admin-body df-locale-<?= e($locale) ?>">
<a class="visually-hidden-focusable df-skip-link" href="#main"><?= e(__('common.skip_to_content')) ?></a>
<div class="df-admin">
    <aside class="df-admin__sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="df-admin-sidebar" aria-label="<?= e(__('admin.nav.label')) ?>">
        <div class="df-admin__brand">
            <a href="<?= e(route('admin.dashboard')) ?>" class="d-inline-flex align-items-center gap-2 text-decoration-none">
                <img src="<?= e(asset('assets/img/logo/darul-fatwa-icone.svg')) ?>" alt="" width="36" height="36">
                <span><strong><?= e(__('common.site.name')) ?></strong><small><?= e(__('admin.title')) ?></small></span>
            </a>
            <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#df-admin-sidebar" aria-label="<?= e(__('nav.close_menu')) ?>"></button>
        </div>
        <nav class="df-admin__nav" aria-label="<?= e(__('admin.nav.label')) ?>">
            <?php foreach ($menu as $group): ?>
            <?php $visible = array_filter($group['items'], static fn (array $i): bool => route_exists($i['route']) && (!isset($i['can']) || can($i['can'])) && (!isset($i['role']) || \App\Core\Auth::hasRole($i['role']))); if ($visible === []) continue; ?>
            <?php if (!empty($group['label'])): ?><p class="df-admin__nav-label"><?= e($group['label']) ?></p><?php endif ?>
            <ul class="nav flex-column">
                <?php foreach ($visible as $item): $active = $isActive($item); ?>
                <li class="nav-item">
                    <a class="nav-link<?= $active ? ' active' : '' ?>" href="<?= e(route($item['route'])) ?>" <?= $active ? 'aria-current="page"' : '' ?>>
                        <i class="bi bi-<?= e($item['icon']) ?>" aria-hidden="true"></i> <span><?= e($item['label']) ?></span>
                        <?php if (!empty($item['badge'])): ?><span class="df-admin__badge"><?= (int) $item['badge'] ?></span><?php endif ?>
                    </a>
                </li>
                <?php endforeach ?>
            </ul>
            <?php endforeach ?>
            <ul class="nav flex-column df-admin__nav-bottom">
                <li class="nav-item"><a class="nav-link" href="<?= e(route('home')) ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> <span><?= e(__('admin.nav.view_site')) ?></span></a></li>
                <?php if (can('scholar.dashboard')): ?><li class="nav-item"><a class="nav-link" href="<?= e(route('scholar.dashboard')) ?>"><i class="bi bi-mortarboard" aria-hidden="true"></i> <span><?= e(__('nav.scholar_space')) ?></span></a></li><?php endif ?>
                <li class="nav-item"><a class="nav-link" href="<?= e(route('account.dashboard')) ?>"><i class="bi bi-person-circle" aria-hidden="true"></i> <span><?= e(__('nav.my_account')) ?></span></a></li>
            </ul>
        </nav>
    </aside>
    <div class="df-admin__main">
        <header class="df-admin__topbar">
            <button type="button" class="btn df-admin__burger d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#df-admin-sidebar" aria-controls="df-admin-sidebar" aria-label="<?= e(__('nav.open_menu')) ?>"><i class="bi bi-list" aria-hidden="true"></i></button>
            <?php if ($this->hasSection('breadcrumbs')): ?><nav aria-label="<?= e(__('nav.breadcrumb')) ?>" class="df-admin__crumbs d-none d-md-block"><?= $this->yield('breadcrumbs') ?></nav><?php else: ?><span class="d-none d-md-block text-secondary small"><?= e(gregorian_date()) ?> · <?= e(hijri_date()) ?></span><?php endif ?>
            <div class="ms-auto d-flex align-items-center gap-2">
                <nav class="df-admin__lang" aria-label="<?= e(__('common.lang.switch')) ?>">
                    <?php foreach (\App\Core\Lang::locales() as $code): ?>
                    <?php if ($code === $locale): ?><span class="is-active" lang="<?= e($code) ?>"><?= e(__('common.lang.short.' . $code)) ?></span>
                    <?php else: ?><a href="<?= e(route('admin.lang', ['code' => $code])) ?>" lang="<?= e($code) ?>" hreflang="<?= e($code) ?>" title="<?= e(__('common.lang.' . $code)) ?>"><?= e(__('common.lang.short.' . $code)) ?></a><?php endif ?>
                    <?php endforeach ?>
                </nav>
                <div class="dropdown">
                    <button type="button" class="btn df-admin__user dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="df-avatar df-avatar--initials df-avatar--sm" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) ($user['full_name'] ?? '?'), 0, 1))) ?></span>
                        <span class="d-none d-sm-inline"><?= e($user['full_name'] ?? '') ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><span class="dropdown-item-text small text-secondary"><?= e(__('account.roles.' . ($user['role'] ?? 'member'))) ?></span></li>
                        <li><a class="dropdown-item" href="<?= e(route('account.profile')) ?>"><i class="bi bi-person" aria-hidden="true"></i> <?= e(__('account.nav.profile')) ?></a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><form method="post" action="<?= e(route('auth.logout')) ?>"><?= csrf_field() ?><button type="submit" class="dropdown-item"><i class="bi bi-box-arrow-right icon-dir" aria-hidden="true"></i> <?= e(__('nav.logout')) ?></button></form></li>
                    </ul>
                </div>
            </div>
        </header>
        <main id="main" tabindex="-1" class="df-admin__content">
            <?= $this->partial('partials/flash') ?>
            <div class="container-fluid">
                <div class="df-admin__header">
                    <div>
                        <h1 class="df-title h2 mb-1"><?= $title ?></h1>
                        <?php if ($this->hasSection('subtitle')): ?><p class="text-secondary mb-0"><?= $this->yield('subtitle') ?></p><?php endif ?>
                    </div>
                    <?php if ($this->hasSection('actions')): ?><div class="df-admin__actions"><?= $this->yield('actions') ?></div><?php endif ?>
                </div>
                <?= $this->yield('content') ?>
            </div>
        </main>
    </div>
</div>
<script src="<?= e(asset('vendor/bootstrap/js/bootstrap.bundle.min.js')) ?>" defer></script>
<script src="<?= e(asset('assets/js/app.js')) ?>" type="module"></script>
<script src="<?= e(asset('assets/js/admin.js')) ?>" type="module"></script>
<?= $this->yield('scripts') ?>
</body>
</html>
