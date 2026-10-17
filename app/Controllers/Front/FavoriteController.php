<?php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\FavoriteService;

/** Favoris (§4.4) : bascule (formulaire ou JSON) et liste dans « Mon espace ». */
final class FavoriteController extends Controller
{
    public function toggle(Request $request): Response
    {
        $data = $this->validate($request, ['type' => 'required|in:article,qa', 'id' => 'required|integer']);
        try {
            $added = FavoriteService::toggle((int) Auth::id(), (string) $data['type'], (int) $data['id']);
        } catch (\InvalidArgumentException) {
            throw new HttpException(404);
        }
        if ($request->wantsJson()) {
            return $this->json(['favorite' => $added, 'count' => FavoriteService::count((string) $data['type'], (int) $data['id'])]);
        }
        Flash::success(__($added ? 'front.favorites.added' : 'front.favorites.removed'));

        return $this->back($request, route('account.favorites'));
    }

    public function index(Request $request): Response
    {
        return $this->view('account/favorites', ['items' => FavoriteService::listFor((int) Auth::id())]);
    }
}
