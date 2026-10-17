<?php
/** Pied de page (vert nuit), 4 colonnes : présentation, liens rapides, rubriques, contact (§6, section 15). */
use App\Models\Page;
use App\Models\Rubrique;

$rubriques = Rubrique::allActive();
$pages = Page::footer();
$phone = setting('contact_phone');
$phone = $phone !== null && !str_contains($phone, 'X') ? $phone : null;
$email = setting('contact_email');
$address = setting('contact_address');
$whatsapp = whatsapp_link(__('front.whatsapp.message'));
$socials = [];
foreach (['facebook' => 'facebook', 'youtube' => 'youtube', 'instagram' => 'instagram', 'telegram' => 'telegram', 'x' => 'twitter-x', 'tiktok' => 'tiktok'] as $key => $icon) {
    $url = setting('social_' . $key);
    if ($url !== null && $url !== '') {
        $socials[$key] = ['url' => $url, 'icon' => $icon];
    }
}
$quick = [
    ['route' => 'questions.create', 'label' => __('nav.ask')],
    ['route' => 'qa.index', 'label' => __('nav.qa')],
    ['route' => 'blog.index', 'label' => __('nav.blog')],
    ['route' => 'forum.index', 'label' => __('nav.forum')],
    ['route' => 'ideas.index', 'label' => __('nav.ideas')],
    ['route' => 'scholars.index', 'label' => __('nav.scholars')],
    ['route' => 'contact', 'label' => __('nav.contact')],
];
$legalCodes = ['terms', 'privacy', 'forum-rules'];
?>
<footer class="df-footer df-pattern">
    <div class="container">
        <div class="row g-4 df-footer__columns">
            <div class="col-12 col-md-6 col-lg-4">
                <img class="df-footer__logo" src="<?= e(asset('assets/img/logo/darul-fatwa-logo-blanc.svg')) ?>" alt="Darul Fatwa – دار الفتوى" width="220" height="66" loading="lazy">
                <p class="df-footer__slogan"><?= e(__('common.site.slogan')) ?></p>
                <p class="df-footer__about"><?= e(__('front.footer.about')) ?></p>
                <?php if ($socials !== []): ?>
                <ul class="df-footer__social list-inline mb-0" aria-label="<?= e(__('front.footer.follow')) ?>">
                    <?php foreach ($socials as $key => $social): ?>
                    <li class="list-inline-item"><a href="<?= e($social['url']) ?>" target="_blank" rel="noopener" aria-label="<?= e(__('front.social.' . $key)) ?>"><i class="bi bi-<?= e($social['icon']) ?>" aria-hidden="true"></i></a></li>
                    <?php endforeach ?>
                </ul>
                <?php endif ?>
            </div>
            <div class="col-6 col-md-6 col-lg-2">
                <h2 class="df-footer__title"><?= e(__('front.footer.quick_links')) ?></h2>
                <ul class="df-footer__links">
                    <?php foreach ($quick as $link): if (!route_exists($link['route'])) continue; ?>
                    <li><a href="<?= e(route($link['route'])) ?>"><?= e($link['label']) ?></a></li>
                    <?php endforeach ?>
                    <?php foreach ($pages as $page): if ($page['code'] !== 'about' && $page['code'] !== 'faq') continue; ?>
                    <li><a href="<?= e(route('pages.show', ['slug' => $page['slug']])) ?>"><?= e($page['title']) ?></a></li>
                    <?php endforeach ?>
                </ul>
            </div>
            <div class="col-6 col-md-6 col-lg-3">
                <h2 class="df-footer__title"><?= e(__('front.footer.rubriques')) ?></h2>
                <ul class="df-footer__links">
                    <?php foreach ($rubriques as $rubrique): ?>
                    <li><a href="<?= e(route('rubriques.show', ['slug' => $rubrique['slug']])) ?>"><?= e($rubrique['name']) ?></a></li>
                    <?php endforeach ?>
                </ul>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <h2 class="df-footer__title"><?= e(__('front.footer.contact')) ?></h2>
                <ul class="df-footer__links df-footer__contact">
                    <?php if ($address !== null && $address !== ''): ?><li><i class="bi bi-geo-alt" aria-hidden="true"></i> <span><?= e($address) ?></span></li><?php endif ?>
                    <?php if ($phone !== null): ?><li><i class="bi bi-telephone" aria-hidden="true"></i> <a class="ltr" href="tel:<?= e($phone) ?>"><?= e(\App\Services\PhoneNumber::format($phone)) ?></a></li><?php endif ?>
                    <?php if ($email !== null && $email !== ''): ?><li><i class="bi bi-envelope" aria-hidden="true"></i> <a class="ltr" href="mailto:<?= e($email) ?>"><?= e($email) ?></a></li><?php endif ?>
                    <?php if ($whatsapp !== null): ?><li><i class="bi bi-whatsapp" aria-hidden="true"></i> <a href="<?= e($whatsapp) ?>" target="_blank" rel="noopener"><?= e(__('nav.whatsapp')) ?></a></li><?php endif ?>
                </ul>
            </div>
        </div>
        <div class="df-footer__bottom">
            <p class="mb-0"><?= e(__('common.footer.rights', ['year' => date('Y')])) ?></p>
            <ul class="df-footer__legal list-inline mb-0">
                <?php foreach ($pages as $page): if (!in_array($page['code'], $legalCodes, true)) continue; ?>
                <li class="list-inline-item"><a href="<?= e(route('pages.show', ['slug' => $page['slug']])) ?>"><?= e($page['title']) ?></a></li>
                <?php endforeach ?>
            </ul>
            <?= $this->partial('partials/lang-switcher') ?>
        </div>
    </div>
</footer>
