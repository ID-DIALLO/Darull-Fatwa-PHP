<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Rubrique;
use App\Services\Admin\ScholarAdminService;

/** Fiches professeurs (§4.6.3). */
final class ScholarsController extends AdminController
{
    protected ?string $permission = 'scholars.manage';

    public function index(Request $request): Response
    {
        return $this->view('admin/scholars/index', ['scholars' => ScholarAdminService::all()]);
    }

    public function edit(Request $request, string $id): Response
    {
        $scholar = ScholarAdminService::find($this->id($id));
        if ($scholar === null) {
            throw new HttpException(404);
        }

        return $this->view('admin/scholars/form', ['scholar' => $scholar, 'rubriques' => $this->rubriqueTree()]);
    }

    public function update(Request $request, string $id): Response
    {
        $userId = $this->id($id);
        if (ScholarAdminService::find($userId) === null) {
            throw new HttpException(404);
        }
        $this->validate($request, ['max_open_questions' => 'required|integer|between:1,500', 'sort_order' => 'integer|between:0,999', 'photo' => 'file|mimes:jpg,jpeg,png,webp|max_size:5120']);
        ScholarAdminService::save($userId, $request->all(), $request->file('photo'), (int) Auth::id());

        return $this->done('admin.scholars');
    }

    /** Rubriques et sous-rubriques pour les cases à cocher. @return list<array<string, mixed>> */
    private function rubriqueTree(): array
    {
        $tree = [];
        foreach (Rubrique::allActive() as $parent) {
            $tree[] = $parent + ['depth' => 0];
            foreach (Rubrique::children((int) $parent['id']) as $child) {
                $tree[] = $child + ['depth' => 1];
            }
        }

        return $tree;
    }
}
