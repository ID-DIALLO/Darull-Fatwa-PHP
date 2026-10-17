<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;

/**
 * Socle des contrôleurs d'administration (§4.6) : vérification de la permission du module,
 * validation avec retour au formulaire, pagination.
 */
abstract class AdminController extends Controller
{
    /** Permission requise pour tout le module (vérifiée à l'instanciation) ; null = accès admin simple. */
    protected ?string $permission = null;

    public function __construct()
    {
        $this->authorize('admin.access');
        if ($this->permission !== null) {
            $this->authorize($this->permission);
        }
    }

    protected function page(Request $request): int
    {
        return max(1, (int) $request->query('page', 1));
    }

    /**
     * Valide sans exception : retourne [données, erreurs].
     *
     * @param array<string, string|list<string>> $rules
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    protected function check(Request $request, array $rules): array
    {
        $validator = Validator::make($request->all(), $rules);

        return [$validator->validated(), $validator->fails() ? $validator->errors() : []];
    }

    /** Entier de route strictement positif, sinon 404. */
    protected function id(?string $value): int
    {
        if ($value === null || !ctype_digit($value) || (int) $value < 1) {
            throw new HttpException(404);
        }

        return (int) $value;
    }

    /** Réponse de succès : flash + redirection. */
    protected function done(string $route, array $params = [], ?string $message = null, string $type = 'success'): Response
    {
        \App\Core\Flash::add($type, $message ?? __('admin.common.saved'));

        return $this->redirectToRoute($route, $params);
    }
}
