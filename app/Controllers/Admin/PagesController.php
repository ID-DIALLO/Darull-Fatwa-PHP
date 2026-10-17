<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Cache;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Response;
use App\Models\Page;
use App\Services\ActivityLog;
use App\Services\Html;
use App\Services\SlugService;
use App\Services\Translations;

/** Pages statiques (À propos, CGU, confidentialité, FAQ, charte) : édition multilingue, pied de page, SEO. */
final class PagesController extends AdminController
{
    protected ?string $permission = 'pages.manage';

    public function index(Request $request): Response
    {
        $rows = Database::fetchAll(
            'SELECT p.*, COALESCE(t.title, d.title) AS title, (SELECT GROUP_CONCAT(l.locale ORDER BY l.locale) FROM page_translations l WHERE l.page_id = p.id) AS locales
             FROM pages p LEFT JOIN page_translations t ON t.page_id = p.id AND t.locale = ? LEFT JOIN page_translations d ON d.page_id = p.id AND d.locale = ? ORDER BY p.sort_order, p.id',
            [Lang::getLocale(), Lang::getDefault()],
        );

        return $this->view('admin/pages/index', ['pages' => $rows]);
    }

    public function create(Request $request): Response
    {
        return $this->view('admin/pages/form', ['page' => null]);
    }

    public function edit(Request $request, string $id): Response
    {
        $page = Page::find($this->id($id));
        if ($page === null) {
            throw new HttpException(404);
        }
        $page['translations'] = Translations::all('page_translations', 'page_id', (int) $page['id']);

        return $this->view('admin/pages/form', ['page' => $page]);
    }

    public function store(Request $request): Response
    {
        $this->validate($request, ['code' => 'required|regex:/^[a-z0-9-]{2,40}$/|unique:pages,code', 'title.' . Lang::getDefault() => 'required|max:160', 'sort_order' => 'integer|between:0,999']);
        $id = Page::create(['code' => (string) $request->input('code'), 'template' => 'default', 'show_in_footer' => $request->input('show_in_footer') === '1' ? 1 : 0, 'is_active' => $request->input('is_active') === '1' ? 1 : 0, 'sort_order' => (int) $request->input('sort_order', 0)]);
        $this->saveTranslations($id, $request);
        ActivityLog::record('admin.page.created', 'page', $id, ['code' => $request->input('code')]);
        Cache::flush('pages.');

        return $this->done('admin.pages');
    }

    public function update(Request $request, string $id): Response
    {
        $pageId = $this->id($id);
        if (Page::find($pageId) === null) {
            throw new HttpException(404);
        }
        $this->validate($request, ['title.' . Lang::getDefault() => 'required|max:160', 'sort_order' => 'integer|between:0,999']);
        Page::update($pageId, ['show_in_footer' => $request->input('show_in_footer') === '1' ? 1 : 0, 'is_active' => $request->input('is_active') === '1' ? 1 : 0, 'sort_order' => (int) $request->input('sort_order', 0)]);
        $this->saveTranslations($pageId, $request);
        ActivityLog::record('admin.page.updated', 'page', $pageId);
        Cache::flush('pages.');

        return $this->done('admin.pages.edit', ['id' => $pageId]);
    }

    private function saveTranslations(int $pageId, Request $request): void
    {
        $existing = Translations::all('page_translations', 'page_id', $pageId);
        $translations = [];
        foreach (Lang::locales() as $locale) {
            $title = Translations::text(((array) $request->input('title', []))[$locale] ?? null, 160);
            $translations[$locale] = [
                'title'            => $title,
                'slug'             => $title === null ? null : (isset($existing[$locale]) && $existing[$locale]['title'] === $title ? $existing[$locale]['slug'] : SlugService::unique($title, 'page_translations', $locale, ['page_id' => $pageId])),
                'content'          => Html::purify((string) (((array) $request->input('content', []))[$locale] ?? ''), 'rich'),
                'meta_title'       => Translations::text(((array) $request->input('meta_title', []))[$locale] ?? null, 160),
                'meta_description' => Translations::text(((array) $request->input('meta_description', []))[$locale] ?? null, 255),
            ];
        }
        Translations::save('page_translations', 'page_id', $pageId, $translations, ['title']);
    }
}
