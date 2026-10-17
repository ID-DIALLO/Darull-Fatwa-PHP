<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\User;
use App\Services\ActivityLog;
use App\Services\MailService;
use App\Services\TokenService;

/**
 * Mot de passe oublié / réinitialisation (§4.1) : jeton haché, valable 60 minutes, usage unique.
 */
final class PasswordController extends Controller
{
    public const RESET_TTL_MINUTES = 60;

    public function forgot(Request $request): Response
    {
        return $this->view('auth/forgot');
    }

    public function sendLink(Request $request): Response
    {
        $data = $this->validate($request, ['email' => 'required|email|max:190']);
        $user = User::findByIdentifier((string) $data['email']);

        if ($user !== null && $user['status'] === 'active' && !empty($user['email'])) {
            $token = TokenService::create((int) $user['id'], TokenService::PASSWORD_RESET, self::RESET_TTL_MINUTES * 60);
            MailService::send((string) $user['email'], 'reset_password', [
                'name'    => User::publicName($user),
                'url'     => route('auth.reset', ['token' => $token], (string) $user['locale'], true),
                'minutes' => self::RESET_TTL_MINUTES,
            ], (string) $user['locale'], (string) $user['full_name']);
            ActivityLog::record('user.password_reset_requested', 'user', (int) $user['id'], [], (int) $user['id']);
        }

        // Même réponse qu'un compte existe ou non : pas d'énumération des adresses.
        return $this->redirectToRoute('auth.login')->with('info', __('auth.forgot.sent'));
    }

    public function reset(Request $request, string $token): Response
    {
        if (TokenService::find($token, TokenService::PASSWORD_RESET) === null) {
            return $this->redirectToRoute('auth.forgot')->with('error', __('auth.reset.invalid_token'));
        }

        return $this->view('auth/reset', ['token' => $token]);
    }

    public function update(Request $request, string $token): Response
    {
        $data = $this->validate($request, ['password' => 'required|string|min:8|max:255|confirmed']);
        $userId = TokenService::consume($token, TokenService::PASSWORD_RESET);
        if ($userId === null) {
            return $this->redirectToRoute('auth.forgot')->with('error', __('auth.reset.invalid_token'));
        }

        User::update($userId, ['password_hash' => Auth::hashPassword((string) $data['password'])]);
        TokenService::deleteAll($userId, TokenService::REMEMBER);
        ActivityLog::record('user.password_reset', 'user', $userId, [], $userId);
        Auth::login($userId);

        $user = User::findWithRole($userId);
        if ($user !== null && !empty($user['email'])) {
            MailService::send((string) $user['email'], 'password_changed', ['name' => User::publicName($user)], (string) $user['locale'], (string) $user['full_name']);
        }

        return $this->redirectToRoute('account.dashboard')->with('success', __('auth.reset.success'));
    }
}
