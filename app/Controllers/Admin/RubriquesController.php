<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Cache;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Response;
use App\Models\Rubrique;
use App\Services\ActivityLog;
use App\Services\ImageService;
use App\Services\SlugService;
use App\Services\Translations;

/**
 * Rubriques (§3, §4.6.3) : CRUD, traductions, icône SVG, couleur, image, ordre, sous-rubriques.
 */
final class RubriquesController extends AdminController
{
    protected ?string $permission = 'rubriques.manage';

    public function index(Request $request): Response
    {
        $rows = Database::fetchAll(
            'SELECT r.*, COALESCE(t.name, d.name) AS name, p.code AS parent_code,
                    (SELECT COUNT(*) FROM questions q WHERE q.rubrique_id = r.id AND q.deleted_at IS NULL) AS questions_count,
                    (SELECT GROUP_CONCAT(l.locale ORDER BY l.locale) FROM rubrique_translations l WHERE l.rubrique_id = r.id) AS locales
             FROM rubriques r LEFT JOIN rubriques p ON p.id = r.parent_id
             LEFT JOIN rubrique_translations t ON t.rubrique_id = r.id AND t.locale = ? LEFT JOIN rubrique_translations d ON d.rubrique_id = r.id AND d.locale = ?
             ORDER BY COALESCE(r.parent_id, r.id), r.parent_id IS NOT NULL, r.sort_order, r.id',
            [Lang::getLocale(), Lang::getDefault()],
        );

        return $this->view('admin/rubriques/index', ['rubriques' => $rows]);
    }

    public function create(Request $request): Response
    {
        return $this->form(null);
    }

    public function edit(Request $request, string $id): Response
    {
        $rubrique = Rubrique::find($this->id($id));
        if ($rubrique === null) {
            throw new HttpException(404);
        }
        $rubrique['translations'] = Translations::all('rubrique_translations', 'rubrique_id', (int) $rubrique['id']);

        return $this->form($rubrique);
    }

    public function store(Request $request): Response
    {
        $data = $this->validated($request, null);
        $id = Rubrique::create($data['attributes']);
        $this->saveTranslations($id, $data['translations']);
        ActivityLog::record('admin.rubrique.created', 'rubrique', $id, ['code' => $data['attributes']['code']]);
        $this->flush();

        return $this->done('admin.rubriques');
    }

    public function update(Request $request, string $id): Response
    {
        $rubriqueId = $this->id($id);
        $current = Rubrique::find($rubriqueId);
        if ($current === null) {
            throw new HttpException(404);
        }
        $data = $this->validated($request, $rubriqueId);
        if (!empty($request->input('remove_image'))) {
            ImageService::delete($current['image_path']);
            $data['attributes']['image_path'] = null;
        } elseif (!isset($data['attributes']['image_path'])) {
            unset($data['attributes']['image_path']);
        } elseif ($current['image_path']) {
            ImageService::delete($current['image_path']);
        }
        Rubrique::update($rubriqueId, $data['attributes']);
        $this->saveTranslations($rubriqueId, $data['translations']);
        ActivityLog::record('admin.rubrique.updated', 'rubrique', $rubriqueId, ['code' => $data['attributes']['code']]);
        $this->flush();

        return $this->done('admin.rubriques');
    }

    /** Suppression possible seulement sans question ni sous-rubrique rattachée. */
    public function destroy(Request $request, string $id): Response
    {
        $rubriqueId = $this->id($id);
        $used = (int) Database::value('SELECT COUNT(*) FROM questions WHERE rubrique_id = ?', [$rubriqueId])
            + (int) Database::value('SELECT COUNT(*) FROM rubriques WHERE parent_id = ?', [$rubriqueId])
            + (int) Database::value('SELECT COUNT(*) FROM qa_publications WHERE rubrique_id = ?', [$rubriqueId]);
        if ($used > 0) {
            return $this->done('admin.rubriques', [], __('admin.rubriques.flash.in_use'), 'error');
        }
        Rubrique::destroy($rubriqueId);
        ActivityLog::record('admin.rubrique.deleted', 'rubrique', $rubriqueId);
        $this->flush();

        return $this->done('admin.rubriques', [], __('admin.common.deleted'));
    }

    /** Réordonnancement (liste d'identifiants dans l'ordre). */
    public function reorder(Request $request): Response
    {
        $order = array_values(array_filter(array_map('intval', (array) $request->input('order', []))));
        foreach ($order as $position => $rubriqueId) {
            Database::execute('UPDATE rubriques SET sort_order = ? WHERE id = ?', [$position + 1, $rubriqueId]);
        }
        $this->flush();

        return $request->wantsJson() ? $this->json(['ok' => true]) : $this->done('admin.rubriques');
    }

    /** @return array{attributes: array<string, mixed>, translations: array<string, array<string, mixed>>} */
    private function validated(Request $request, ?int $ignoreId): array
    {
        $rules = [
            'code' => 'required|regex:/^[a-z0-9-]{2,40}$/|unique:rubriques,code' . ($ignoreId !== null ? ',' . $ignoreId : ''),
            'parent_id' => 'integer', 'color' => 'regex:/^#[0-9A-Fa-f]{6}$/', 'sort_order' => 'integer|between:0,999',
            'icon' => 'max:120', 'image' => 'file|mimes:jpg,jpeg,png,webp|max_size:5120',
        ];
        foreach (Lang::locales() as $locale) {
            $rules['name.' . $locale] = ($locale === Lang::getDefault() ? 'required|' : '') . 'max:120';
        }
        $this->validate($request, $rules);
        $attributes = [
            'code'       => (string) $request->input('code'),
            'parent_id'  => ctype_digit((string) $request->input('parent_id')) && (int) $request->input('parent_id') !== $ignoreId && (int) $request->input('parent_id') > 0 ? (int) $request->input('parent_id') : null,
            'icon'       => Translations::text($request->input('icon'), 120),
            'color'      => Translations::text($request->input('color'), 7),
            'sort_order' => (int) $request->input('sort_order', 0),
            'is_active'  => $request->input('is_active') === '1' ? 1 : 0,
        ];
        $image = $request->file('image');
        if ($image !== null && ($image['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $attributes['image_path'] = ImageService::store($image, 'rubriques', 1200, 600, true);
        }
        $translations = [];
        $names = (array) $request->input('name', []);
        foreach (Lang::locales() as $locale) {
            $name = Translations::text($names[$locale] ?? null, 120);
            $translations[$locale] = [
                'name'              => $name,
                'slug'              => null, // calculé à l'enregistrement
                'short_description' => Translations::text(((array) $request->input('short_description', []))[$locale] ?? null, 255),
                'description'       => \App\Services\Html::purify((string) (((array) $request->input('description', []))[$locale] ?? ''), 'rich') ?: null,
                'meta_title'        => Translations::text(((array) $request->input('meta_title', []))[$locale] ?? null, 160),
                'meta_description'  => Translations::text(((array) $request->input('meta_description', []))[$locale] ?? null, 255),
            ];
        }

        return ['attributes' => $attributes, 'translations' => $translations];
    }

    /** @param array<string, array<string, mixed>> $translations */
    private function saveTranslations(int $id, array $translations): void
    {
        $existing = Translations::all('rubrique_translations', 'rubrique_id', $id);
        foreach ($translations as $locale => &$row) {
            if ($row['name'] === null) {
                continue;
            }
            $row['slug'] = isset($existing[$locale]) && $existing[$locale]['name'] === $row['name']
                ? $existing[$locale]['slug']
                : SlugService::unique((string) $row['name'], 'rubrique_translations', $locale, ['rubrique_id' => $id]);
        }
        unset($row);
        Translations::save('rubrique_translations', 'rubrique_id', $id, $translations, ['name']);
    }

    private function flush(): void
    {
        Cache::flush('rubriques.');
        Cache::flush('stats.');
    }

    /** @param array<string, mixed>|null $rubrique */
    private function form(?array $rubrique): Response
    {
        $icons = array_map(static fn (string $f): string => '/assets/img/rubriques/' . basename($f), glob(public_path('assets/img/rubriques/*.svg')) ?: []);

        return $this->view('admin/rubriques/form', [
            'rubrique' => $rubrique,
            'parents'  => Database::fetchAll('SELECT r.id, r.code, COALESCE(t.name, d.name) AS name FROM rubriques r LEFT JOIN rubrique_translations t ON t.rubrique_id = r.id AND t.locale = ? LEFT JOIN rubrique_translations d ON d.rubrique_id = r.id AND d.locale = ? WHERE r.parent_id IS NULL ORDER BY r.sort_order', [Lang::getLocale(), Lang::getDefault()]),
            'icons'    => $icons,
        ]);
    }
}
