<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\ValidationException;
use App\Models\User;
use App\Services\ActivityLog;
use App\Services\LoginThrottle;
use App\Services\PhoneNumber;

/**
 * Connexion (e-mail ou téléphone + mot de passe), « se souvenir de moi », déconnexion (§4.1).
 */
final class LoginController extends Controller
{
    public function show(Request $request): Response
    {
        return $this->view('auth/login', [
            'redirect' => $this->safeTarget($request->query('redirect')),
        ]);
    }

    public function login(Request $request): Response
    {
        $data = $this->validate($request, [
            'identifier' => 'required|string|max:190',
            'password'   => 'required|string|max:255',
            'remember'   => 'nullable|boolean',
        ]);
        $identifier = trim((string) $data['identifier']);
        if (!str_contains($identifier, '@')) {
            $identifier = (string) PhoneNumber::normalize($identifier);
        }

        // Blocage temporaire après 5 échecs en 15 minutes (par identifiant et par IP)
        $retryAfter = LoginThrottle::retryAfter($identifier, $request->ip());
        if ($retryAfter > 0) {
            throw new HttpException(429, __('auth.throttle.blocked', ['minutes' => (int) ceil($retryAfter / 60)]), ['Retry-After' => (string) $retryAfter]);
        }

        $user = User::findByIdentifier($identifier);
        if ($user === null || !Auth::verifyPassword($user, (string) $data['password'])) {
            LoginThrottle::record($identifier, $request->ip(), false);

            throw new ValidationException(['identifier' => [__('auth.login.failed')]]);
        }
        if ($user['status'] !== 'active') {
            LoginThrottle::record($identifier, $request->ip(), false);

            throw new ValidationException(['identifier' => [__($user['status'] === 'banned' ? 'auth.login.banned' : 'auth.login.suspended')]]);
        }

        LoginThrottle::record($identifier, $request->ip(), true);
        Auth::login((int) $user['id'], in_array($data['remember'] ?? null, ['1', 1, true, 'on'], true));
        User::update((int) $user['id'], ['last_login_at' => gmdate('Y-m-d H:i:s'), 'last_login_ip' => $request->ip()]);
        ActivityLog::record('user.login', 'user', (int) $user['id'], ['remember' => (bool) ($data['remember'] ?? false)], (int) $user['id']);

        $target = $this->safeTarget($request->input('redirect')) ?? $this->safeTarget(Session::pull('_intended')) ?? route('account.dashboard', [], (string) $user['locale']);

        return $this->redirect($target)->with('success', __('auth.login.welcome_back', ['name' => User::publicName($user)]));
    }

    public function logout(Request $request): Response
    {
        $id = Auth::id();
        Auth::logout($request);
        if ($id !== null) {
            ActivityLog::record('user.logout', 'user', $id, [], $id);
        }

        return $this->redirectToRoute('home')->with('success', __('auth.logout.success'));
    }

    /** N'accepte qu'un chemin relatif de notre site (pas d'URL externe). */
    private function safeTarget(mixed $target): ?string
    {
        if (!is_string($target) || $target === '' || !str_starts_with($target, '/') || str_starts_with($target, '//') || str_contains($target, "\n")) {
            return null;
        }

        return $target;
    }
}
