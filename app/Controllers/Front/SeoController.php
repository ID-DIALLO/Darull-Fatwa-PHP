<?php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\SitemapService;

/**
 * Fichiers techniques du référencement et de la PWA (§13) : sitemaps, robots.txt, page hors-ligne.
 */
final class SeoController extends Controller
{
    public function sitemap(Request $request, string $name = 'sitemap.xml'): Response
    {
        $xml = SitemapService::content($name);
        if ($xml === null) {
            throw new HttpException(404);
        }

        return (new Response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']));
    }

    public function sitemapLocale(Request $request, string $name): Response
    {
        return $this->sitemap($request, 'sitemap-' . $name . '.xml');
    }

    public function robots(Request $request): Response
    {
        return new Response(SitemapService::robots(), 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'public, max-age=86400']);
    }

    /** Page hors-ligne mise en cache par le service worker : les trois langues sur une seule page, sans dépendance. */
    public function offline(Request $request): Response
    {
        return $this->view('front/offline')->header('Cache-Control', 'no-cache');
    }
}
