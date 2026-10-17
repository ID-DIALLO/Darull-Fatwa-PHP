<?php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\ScholarProfile;

/**
 * « Nos professeurs » : liste des profils publics et fiche d'un professeur (§4.5).
 */
final class ScholarController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('front/scholars/index', ['scholars' => ScholarProfile::publicList()]);
    }

    public function show(Request $request, string $slug): Response
    {
        $id = ScholarProfile::idFromSlug($slug);
        $scholar = $id > 0 ? ScholarProfile::findPublic($id) : null;
        if ($scholar === null) {
            throw new HttpException(404);
        }
        // Même fiche dans chaque langue (le segment ne dépend pas de la langue)
        $alternates = [];
        foreach (Lang::locales() as $code) {
            $alternates[$code] = route('scholars.show', ['slug' => ScholarProfile::slug($scholar)], $code);
        }
        View::share('alternates', $alternates);

        return $this->view('front/scholars/show', [
            'scholar'        => $scholar,
            'fallbackLocale' => (int) $scholar['is_fallback'] === 1 ? Lang::getDefault() : null,
        ]);
    }
}
