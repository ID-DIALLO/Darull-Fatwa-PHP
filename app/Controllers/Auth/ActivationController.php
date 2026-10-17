<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Models\User;
use App\Services\LoginThrottle;
use App\Services\StaffInvitationService;

/**
 * Activation d'un compte créé par l'administration : la personne ouvre le lien reçu, saisit son identifiant
 * (e-mail ou téléphone) et le code à 6 chiffres transmis par l'administrateur, puis choisit son mot de passe.
 */
final class ActivationController extends Controller
{
    public function show(Request $request, string $token): Response
    {
        $user = StaffInvitationService::userForToken($token);
        if ($user === null) {
            return $this->redirectToRoute('auth.login')->with('error', __('auth.activate.invalid_link'));
        }

        return $this->view('auth/activate', ['token' => $token, 'role' => (string) $user['role']]);
    }

    public function activate(Request $request, string $token): Response
    {
        if (StaffInvitationService::userForToken($token) === null) {
            return $this->redirectToRoute('auth.login')->with('error', __('auth.activate.invalid_link'));
        }
        $data = $this->validate($request, [
            'identifier' => 'required|string|max:190',
            'code'       => 'required|string|regex:/^\s*\d{3}\s?\d{3}\s*$/',
            'password'   => ['required', 'string', 'min:10', 'max:200', 'regex:/^(?=.*[A-Za-z])(?=.*\d).+$/', 'confirmed'],
        ]);
        $identifier = trim((string) $data['identifier']);
        $retryAfter = LoginThrottle::retryAfter('activate:' . $identifier, $request->ip());
        if ($retryAfter > 0) {
            throw new HttpException(429, __('auth.throttle.blocked', ['minutes' => (int) ceil($retryAfter / 60)]), ['Retry-After' => (string) $retryAfter]);
        }
        $user = StaffInvitationService::verify($token, $identifier, (string) $data['code']);
        if ($user === null) {
            LoginThrottle::record('activate:' . $identifier, $request->ip(), false);

            throw new ValidationException(['code' => [__('auth.activate.mismatch')]]);
        }
        LoginThrottle::record('activate:' . $identifier, $request->ip(), true);
        StaffInvitationService::accept($token, $user, (string) $data['password']);

        return $this->redirectToRoute(StaffInvitationService::homeRoute((string) $user['role']))
            ->with('success', __('auth.activate.success', ['name' => User::publicName($user)]));
    }
}
