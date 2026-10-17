<?php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\SearchService;

/** Recherche globale (§4.13). */
final class SearchController extends Controller
{
    public function index(Request $request): Response
    {
        $query = trim(mb_substr((string) $request->query('q', ''), 0, 120));
        $results = $query !== '' ? SearchService::search($query) : ['qa' => [], 'articles' => [], 'topics' => [], 'total' => 0];

        return $this->view('front/search', ['query' => $query, 'results' => $results, 'words' => SearchService::words($query)]);
    }
}
