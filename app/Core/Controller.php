<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Contrôleur de base : view(), json(), redirect(), validate(), authorize().
 */
abstract class Controller
{
    /** @param array<string, mixed> $data */
    protected function view(string $template, array $data = [], int $status = 200): Response
    {
        return Response::html(View::make($template, $data), $status);
    }

    protected function json(mixed $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    protected function redirect(string $url, int $status = 302): Response
    {
        return Response::redirect($url, $status);
    }

    /** @param array<string, scalar> $params */
    protected function redirectToRoute(string $name, array $params = []): Response
    {
        return Response::redirect(route($name, $params));
    }

    protected function back(Request $request, string $fallback = '/'): Response
    {
        $referer = (string) $request->header('Referer');
        $host = parse_url($referer, PHP_URL_HOST);

        // On ne redirige que vers notre propre site
        return Response::redirect($referer !== '' && $host === $request->host() ? $referer : $fallback);
    }

    /**
     * Valide la requête ; en cas d'erreur : JSON 422 pour les appels API,
     * sinon retour au formulaire avec les erreurs et les anciennes saisies.
     *
     * @param array<string, string|list<string>> $rules
     * @param array<string, string> $messages
     * @return array<string, mixed> Données validées
     * @throws ValidationException
     */
    protected function validate(Request $request, array $rules, array $messages = []): array
    {
        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            throw new ValidationException($validator->errors());
        }

        return $validator->validated();
    }

    /** @throws HttpException 403 si la permission manque */
    protected function authorize(string $permission): void
    {
        if (!Auth::can($permission)) {
            throw new HttpException(403);
        }
    }
}
