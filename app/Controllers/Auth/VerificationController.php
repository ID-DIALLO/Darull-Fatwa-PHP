<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\User;
use App\Services\ActivityLog;
use App\Services\MailService;
use App\Services\TokenService;

/**
 * Vérification de l'adresse e-mail par lien (§4.1). Le téléphone sera vérifié par OTP WhatsApp en V2.
 */
final class VerificationController extends Controller
{
    public const VERIFY_TTL_HOURS = 48;

    public function verify(Request $request, string $token): Response
    {
        $userId = TokenService::consume($token, TokenService::EMAIL_VERIFY);
        $target = Auth::check() ? route('account.dashboard') : route('auth.login');
        if ($userId === null) {
            return $this->redirect($target)->with('error', __('auth.verify.invalid'));
        }
        Database::execute('UPDATE users SET email_verified_at = COALESCE(email_verified_at, UTC_TIMESTAMP()) WHERE id = ?', [$userId]);
        ActivityLog::record('user.email_verified', 'user', $userId, [], $userId);
        Auth::reset();

        return $this->redirect($target)->with('success', __('auth.verify.success'));
    }

    public function resend(Request $request): Response
    {
        $user = Auth::user();
        if ($user === null || empty($user['email'])) {
            return $this->redirectToRoute('account.profile');
        }
        if ($user['email_verified_at'] !== null) {
            return $this->back($request, route('account.dashboard'))->with('info', __('auth.verify.already'));
        }
        self::sendVerification($user);

        return $this->back($request, route('account.dashboard'))->with('success', __('auth.verify.resent'));
    }

    /**
     * Envoie le lien de vérification dans la langue de l'utilisateur.
     *
     * @param array<string, mixed> $user
     */
    public static function sendVerification(array $user): bool
    {
        if (empty($user['email'])) {
            return false;
        }
        $token = TokenService::create((int) $user['id'], TokenService::EMAIL_VERIFY, self::VERIFY_TTL_HOURS * 3600);

        return MailService::send((string) $user['email'], 'verify_email', [
            'name'  => User::publicName($user),
            'url'   => route('auth.verify', ['token' => $token], (string) $user['locale'], true),
            'hours' => self::VERIFY_TTL_HOURS,
        ], (string) $user['locale'], (string) $user['full_name']);
    }
}
