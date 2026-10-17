<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Rubrique;
use App\Services\Admin\PublicationService;

/** Q/R publiques anonymisées (§4.8) : liste, édition multilingue, statut, suppression. */
final class QaController extends AdminController
{
    protected ?string $permission = 'qa.manage';

    public function index(Request $request): Response
    {
        $filters = ['status' => (string) $request->query('status', ''), 'rubrique' => (string) $request->query('rubrique', ''), 'q' => (string) $request->query('q', '')];

        return $this->view('admin/qa/index', ['filters' => $filters, 'result' => PublicationService::list($filters, $this->page($request)), 'rubriques' => Rubrique::allActive()]);
    }

    public function edit(Request $request, string $id): Response
    {
        $publication = PublicationService::find($this->id($id));
        if ($publication === null) {
            throw new HttpException(404);
        }

        return $this->view('admin/qa/form', ['publication' => $publication, 'rubriques' => Rubrique::allActive()]);
    }

    public function update(Request $request, string $id): Response
    {
        $publicationId = $this->id($id);
        $this->validate($request, ['status' => 'required|in:draft,published,archived', 'rubrique_id' => 'required|integer|exists:rubriques,id']);
        try {
            PublicationService::update($publicationId, $request->all(), (int) Auth::id());
        } catch (\InvalidArgumentException) {
            throw new HttpException(404);
        }

        return $this->done('admin.qa.edit', ['id' => $publicationId]);
    }

    public function destroy(Request $request, string $id): Response
    {
        PublicationService::delete($this->id($id), (int) Auth::id());

        return $this->done('admin.qa', [], __('admin.common.deleted'));
    }
}
