<?php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\Page;

/**
 * Pages CMS (À propos, CGU, confidentialité, FAQ, charte du forum), §4.6.3.
 */
final class PageController extends Controller
{
    public function show(Request $request, string $slug): Response
    {
        $page = Page::findBySlug($slug);
        if ($page === null) {
            throw new HttpException(404);
        }
        $slugs = Page::slugs((int) $page['id']);
        $alternates = [];
        foreach (Lang::locales() as $code) {
            $alternates[$code] = isset($slugs[$code]) ? route('pages.show', ['slug' => $slugs[$code]], $code) : route('home', [], $code);
        }
        View::share('alternates', $alternates);

        return $this->view('front/page', [
            'page'           => $page,
            'fallbackLocale' => (int) $page['is_fallback'] === 1 ? Lang::getDefault() : null,
        ]);
    }
}
