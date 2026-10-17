<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Cache;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Response;
use App\Models\Banner;
use App\Services\ActivityLog;
use App\Services\ImageService;
use App\Services\Translations;

/** Bannières du Hero (§6.1, §4.6.3) : CRUD multilingue, ordre, dates, image ordinateur + mobile. */
final class BannersController extends AdminController
{
    protected ?string $permission = 'banners.manage';

    public function index(Request $request): Response
    {
        $rows = Database::fetchAll(
            'SELECT b.*, COALESCE(t.title, d.title) AS title, (SELECT GROUP_CONCAT(l.locale ORDER BY l.locale) FROM banner_translations l WHERE l.banner_id = b.id) AS locales
             FROM banners b LEFT JOIN banner_translations t ON t.banner_id = b.id AND t.locale = ? LEFT JOIN banner_translations d ON d.banner_id = b.id AND d.locale = ?
             ORDER BY b.sort_order, b.id',
            [Lang::getLocale(), Lang::getDefault()],
        );

        return $this->view('admin/banners/index', ['banners' => $rows]);
    }

    public function create(Request $request): Response
    {
        return $this->view('admin/banners/form', ['banner' => null, 'routes' => $this->linkRoutes()]);
    }

    public function edit(Request $request, string $id): Response
    {
        $banner = Banner::find($this->id($id));
        if ($banner === null) {
            throw new HttpException(404);
        }
        $banner['translations'] = Translations::all('banner_translations', 'banner_id', (int) $banner['id']);

        return $this->view('admin/banners/form', ['banner' => $banner, 'routes' => $this->linkRoutes()]);
    }

    public function store(Request $request): Response
    {
        $data = $this->payload($request, null);
        if (empty($data['attributes']['image_desktop'])) {
            return $this->done('admin.banners.create', [], __('admin.banners.flash.image_required'), 'error');
        }
        $id = Banner::create($data['attributes']);
        Translations::save('banner_translations', 'banner_id', $id, $data['translations'], ['title']);
        ActivityLog::record('admin.banner.created', 'banner', $id);
        Cache::flush('banners.');

        return $this->done('admin.banners');
    }

    public function update(Request $request, string $id): Response
    {
        $bannerId = $this->id($id);
        $current = Banner::find($bannerId);
        if ($current === null) {
            throw new HttpException(404);
        }
        $data = $this->payload($request, $current);
        Banner::update($bannerId, $data['attributes']);
        Translations::save('banner_translations', 'banner_id', $bannerId, $data['translations'], ['title']);
        ActivityLog::record('admin.banner.updated', 'banner', $bannerId);
        Cache::flush('banners.');

        return $this->done('admin.banners');
    }

    public function destroy(Request $request, string $id): Response
    {
        $bannerId = $this->id($id);
        $banner = Banner::find($bannerId);
        if ($banner !== null) {
            ImageService::delete($banner['image_desktop']);
            ImageService::delete($banner['image_mobile']);
            Banner::destroy($bannerId);
            ActivityLog::record('admin.banner.deleted', 'banner', $bannerId);
            Cache::flush('banners.');
        }

        return $this->done('admin.banners', [], __('admin.common.deleted'));
    }

    /**
     * @param array<string, mixed>|null $current
     * @return array{attributes: array<string, mixed>, translations: array<string, array<string, mixed>>}
     */
    private function payload(Request $request, ?array $current): array
    {
        $rules = ['sort_order' => 'integer|between:0,999', 'starts_at' => 'date', 'ends_at' => 'date', 'link_url' => 'max:255', 'link2_url' => 'max:255',
            'image_desktop' => 'file|mimes:jpg,jpeg,png,webp|max_size:5120', 'image_mobile' => 'file|mimes:jpg,jpeg,png,webp|max_size:5120'];
        $rules['title.' . Lang::getDefault()] = 'required|max:160';
        $this->validate($request, $rules);
        $attributes = [
            'link_url'   => $this->link((string) $request->input('link_url', '')),
            'link2_url'  => $this->link((string) $request->input('link2_url', '')),
            'sort_order' => (int) $request->input('sort_order', 0),
            'is_active'  => $request->input('is_active') === '1' ? 1 : 0,
            'starts_at'  => $this->datetime($request->input('starts_at')),
            'ends_at'    => $this->datetime($request->input('ends_at')),
        ];
        foreach (['image_desktop' => [1920, 900], 'image_mobile' => [900, 1200]] as $field => [$w, $h]) {
            $file = $request->file($field);
            if ($file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                if ($current !== null) {
                    ImageService::delete($current[$field]);
                }
                $attributes[$field] = ImageService::store($file, 'banners', $w, $h, true, 80);
            } elseif ($current !== null && !empty($request->input('remove_' . $field))) {
                ImageService::delete($current[$field]);
                $attributes[$field] = null;
            }
        }
        $translations = [];
        foreach (Lang::locales() as $locale) {
            $translations[$locale] = [
                'title'         => Translations::text(((array) $request->input('title', []))[$locale] ?? null, 160),
                'subtitle'      => Translations::text(((array) $request->input('subtitle', []))[$locale] ?? null, 300),
                'button_label'  => Translations::text(((array) $request->input('button_label', []))[$locale] ?? null, 60),
                'button2_label' => Translations::text(((array) $request->input('button2_label', []))[$locale] ?? null, 60),
                'image_alt'     => Translations::text(((array) $request->input('image_alt', []))[$locale] ?? null, 160),
            ];
        }

        return ['attributes' => $attributes, 'translations' => $translations];
    }

    /** Lien : « route:nom » (routes publiques connues) ou URL http(s) ou chemin relatif. */
    private function link(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (str_starts_with($value, 'route:')) {
            return in_array(substr($value, 6), $this->linkRoutes(), true) ? $value : null;
        }

        return preg_match('#^(https?://|/)#i', $value) ? mb_substr($value, 0, 255) : null;
    }

    private function datetime(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $time = strtotime($value);

        return $time === false ? null : gmdate('Y-m-d H:i:s', $time);
    }

    /** @return list<string> */
    private function linkRoutes(): array
    {
        return ['questions.create', 'rubriques.index', 'scholars.index', 'contact', 'qa.index', 'blog.index', 'forum.index', 'ideas.index', 'auth.register'];
    }
}
