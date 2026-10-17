<?php

declare(strict_types=1);

namespace App\Controllers\Account;

use App\Controllers\Auth\RegisterController;
use App\Controllers\Auth\VerificationController;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Core\Validator;
use App\Models\User;
use App\Services\AccountService;
use App\Services\ActivityLog;
use App\Services\ImageService;
use App\Services\MailService;
use App\Services\PhoneNumber;
use App\Services\TokenService;

/**
 * Profil, sécurité (mot de passe, appareils), export et suppression du compte (§4.1, §4.4, §12.2).
 */
final class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        return $this->view('account/profile', [
            'user'      => Auth::user(),
            'countries' => RegisterController::countries(),
        ]);
    }

    public function update(Request $request): Response
    {
        $user = Auth::user();
        $id = (int) $user['id'];
        $input = $request->all();
        $input['phone'] = PhoneNumber::normalize((string) ($input['phone_e164'] ?? '') !== '' ? (string) $input['phone_e164'] : (string) ($input['phone'] ?? ''));
        $whatsappSame = in_array($input['whatsapp_same'] ?? '0', ['1', 'on', 1, true], true);
        $input['whatsapp'] = $whatsappSame ? $input['phone'] : PhoneNumber::normalize((string) ($input['whatsapp_e164'] ?? '') !== '' ? (string) $input['whatsapp_e164'] : (string) ($input['whatsapp'] ?? ''));
        $input['email'] = isset($input['email']) && is_string($input['email']) ? mb_strtolower(trim($input['email'])) : null;
        $input['avatar'] = $request->file('avatar');

        $validator = Validator::make($input, [
            'full_name'         => 'required|string|min:2|max:120',
            'display_name'      => 'nullable|string|min:2|max:60',
            'email'             => "required_without:phone|nullable|email|max:190|unique:users,email,{$id}",
            'phone'             => "required_without:email|nullable|phone|unique:users,phone,{$id}",
            'whatsapp'          => 'nullable|phone',
            'locale'            => 'required|in:' . implode(',', Lang::locales()),
            'country'           => 'nullable|in:' . implode(',', array_keys(RegisterController::countries())),
            'city'              => 'nullable|string|max:80',
            'gender'            => 'nullable|in:m,f',
            'preferred_channel' => 'required|in:platform,whatsapp,both',
            'notify_email'      => 'nullable|boolean',
            'notify_whatsapp'   => 'nullable|boolean',
            'avatar'            => 'nullable|file|mimes:jpg,jpeg,png,webp|max_size:5120',
        ], [
            'email.required_without' => __('auth.register.contact_required'),
            'phone.required_without' => __('auth.register.contact_required'),
        ]);
        if ($validator->fails()) {
            throw new ValidationException($validator->errors());
        }
        $data = $validator->validated();

        $update = [
            'full_name'         => trim((string) $data['full_name']),
            'display_name'      => trim((string) ($data['display_name'] ?? '')) !== '' ? trim((string) $data['display_name']) : null,
            'email'             => $data['email'] !== '' ? $data['email'] : null,
            'phone'             => $data['phone'],
            'whatsapp'          => $data['whatsapp'],
            'locale'            => $data['locale'],
            'country'           => !empty($data['country']) ? strtoupper((string) $data['country']) : null,
            'city'              => trim((string) ($data['city'] ?? '')) !== '' ? trim((string) $data['city']) : null,
            'gender'            => in_array($data['gender'] ?? '', ['m', 'f'], true) ? $data['gender'] : null,
            'preferred_channel' => $data['preferred_channel'],
            'notify_email'      => isset($data['notify_email']) ? 1 : 0,
            'notify_whatsapp'   => isset($data['notify_whatsapp']) ? 1 : 0,
        ];

        $messages = [['success', __('account.profile.updated')]];
        $emailChanged = $update['email'] !== null && $update['email'] !== $user['email'];
        if ($emailChanged || $update['email'] === null) {
            $update['email_verified_at'] = null;
        }

        if (in_array($input['remove_avatar'] ?? '0', ['1', 'on'], true) && !empty($user['avatar_path'])) {
            ImageService::delete((string) $user['avatar_path']);
            $update['avatar_path'] = null;
        }
        if (is_array($data['avatar'] ?? null) && ($data['avatar']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $update['avatar_path'] = ImageService::store($data['avatar'], 'avatars', 256, 256, true);
            if (!empty($user['avatar_path'])) {
                ImageService::delete((string) $user['avatar_path']);
            }
        }

        User::update($id, $update);
        ActivityLog::record('user.profile_updated', 'user', $id, ['email_changed' => $emailChanged]);
        Auth::reset();

        if ($emailChanged) {
            $fresh = User::findWithRole($id);
            if ($fresh !== null && VerificationController::sendVerification($fresh)) {
                $messages[] = ['info', __('account.profile.email_changed')];
            }
        }

        $response = $this->redirect(route('account.profile', [], (string) $data['locale']));
        foreach ($messages as [$type, $message]) {
            $response->with($type, $message);
        }

        return $response;
    }

    public function security(Request $request): Response
    {
        return $this->view('account/security', ['user' => Auth::user()]);
    }

    public function updatePassword(Request $request): Response
    {
        $user = Auth::user();
        $data = $this->validate($request, [
            'current_password' => 'required|string|max:255',
            'password'         => 'required|string|min:8|max:255|confirmed',
        ]);
        if (!Auth::verifyPassword($user, (string) $data['current_password'])) {
            throw new ValidationException(['current_password' => [__('account.security.wrong_password')]]);
        }
        $id = (int) $user['id'];
        User::update($id, ['password_hash' => Auth::hashPassword((string) $data['password'])]);
        Auth::refreshSessionHash($id);
        ActivityLog::record('user.password_changed', 'user', $id);
        if (!empty($user['email'])) {
            MailService::send((string) $user['email'], 'password_changed', ['name' => User::publicName($user)], (string) $user['locale'], (string) $user['full_name']);
        }

        return $this->redirectToRoute('account.security')->with('success', __('account.security.changed'));
    }

    public function logoutEverywhere(Request $request): Response
    {
        $id = (int) Auth::id();
        TokenService::deleteAll($id, TokenService::REMEMBER);
        ActivityLog::record('user.sessions_revoked', 'user', $id);

        return $this->redirectToRoute('account.security')->with('success', __('account.security.logged_out_everywhere'));
    }

    public function data(Request $request): Response
    {
        return $this->view('account/data', ['user' => Auth::user()]);
    }

    public function export(Request $request): Response
    {
        $id = (int) Auth::id();
        $json = json_encode(AccountService::export($id), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        ActivityLog::record('user.data_exported', 'user', $id);

        return (new Response($json, 200, [
            'Content-Type'        => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="darul-fatwa-export-' . gmdate('Y-m-d') . '.json"',
            'Cache-Control'       => 'no-store',
        ]));
    }

    public function destroy(Request $request): Response
    {
        $user = Auth::user();
        $data = $this->validate($request, [
            'password' => 'required|string|max:255',
            'confirm'  => 'accepted',
        ]);
        if (!Auth::verifyPassword($user, (string) $data['password'])) {
            throw new ValidationException(['password' => [__('account.security.wrong_password')]]);
        }
        $id = (int) $user['id'];
        if (!empty($user['email'])) {
            MailService::send((string) $user['email'], 'account_deleted', ['name' => User::publicName($user)], (string) $user['locale'], (string) $user['full_name']);
        }
        AccountService::delete($id);
        ActivityLog::record('user.deleted', 'user', $id, ['self' => true], $id);
        Auth::logout($request);

        return $this->redirectToRoute('home')->with('success', __('account.data.deleted'));
    }
}
