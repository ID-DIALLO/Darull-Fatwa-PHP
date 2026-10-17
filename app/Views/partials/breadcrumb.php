<?php
/**
 * Fil d'Ariane (§13.1). $items : liste de ['label' => …, 'url' => …|null] (le dernier sans URL = page courante).
 *
 * @var list<array{label: string, url?: string|null}> $items
 */
$position = 1;
$crumbs = [['@type' => 'ListItem', 'position' => $position++, 'name' => __('nav.home'), 'item' => url(route('home'))]];
foreach ($items as $item) {
    $crumb = ['@type' => 'ListItem', 'position' => $position++, 'name' => (string) $item['label']];
    if (!empty($item['url'])) {
        $crumb['item'] = str_starts_with((string) $item['url'], 'http') ? (string) $item['url'] : url((string) $item['url']);
    }
    $crumbs[] = $crumb;
}
?>
<?= json_ld(['@type' => 'BreadcrumbList', 'itemListElement' => $crumbs]) ?>
<nav class="df-breadcrumb" aria-label="<?= e(__('nav.breadcrumb')) ?>">
    <div class="container">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= e(route('home')) ?>"><i class="bi bi-house-door" aria-hidden="true"></i> <span class="visually-hidden"><?= e(__('nav.home')) ?></span></a></li>
            <?php foreach ($items as $item): ?>
            <?php if (!empty($item['url'])): ?>
            <li class="breadcrumb-item"><a href="<?= e($item['url']) ?>"><?= e($item['label']) ?></a></li>
            <?php else: ?>
            <li class="breadcrumb-item active" aria-current="page"><?= e($item['label']) ?></li>
            <?php endif ?>
            <?php endforeach ?>
        </ol>
    </div>
</nav>
