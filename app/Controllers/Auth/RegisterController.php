<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\ValidationException;
use App\Core\Validator;
use App\Models\User;
use App\Services\AccountService;
use App\Services\ActivityLog;
use App\Services\PhoneNumber;
use App\Services\Turnstile;

/**
 * Inscription (§4.1) : téléphone ou e-mail obligatoire, +223 par défaut, anti-robot, consentement.
 */
final class RegisterController extends Controller
{
    public function show(Request $request): Response
    {
        return $this->view('auth/register', [
            'redirect'  => $this->safeTarget($request->query('redirect')),
            'countries' => self::countries(),
        ]);
    }

    public function register(Request $request): Response
    {
        if (!Turnstile::passes($request->all(), $request->ip())) {
            throw new ValidationException(['terms' => [__('auth.robot.failed')]]);
        }

        $input = $request->all();
        // Numéros : le champ caché E.164 d'intl-tel-input, sinon la saisie brute normalisée
        $input['phone'] = PhoneNumber::normalize((string) ($input['phone_e164'] ?? '') !== '' ? (string) $input['phone_e164'] : (string) ($input['phone'] ?? ''));
        $whatsappSame = in_array($input['whatsapp_same'] ?? '1', ['1', 'on', 1, true], true);
        $input['whatsapp'] = $whatsappSame ? $input['phone'] : PhoneNumber::normalize((string) ($input['whatsapp_e164'] ?? '') !== '' ? (string) $input['whatsapp_e164'] : (string) ($input['whatsapp'] ?? ''));
        $input['email'] = isset($input['email']) && is_string($input['email']) ? mb_strtolower(trim($input['email'])) : null;

        $validator = Validator::make($input, [
            'full_name'    => 'required|string|min:2|max:120',
            'display_name' => 'nullable|string|min:2|max:60',
            'email'        => 'required_without:phone|nullable|email|max:190|unique:users,email',
            'phone'        => 'required_without:email|nullable|phone|unique:users,phone',
            'whatsapp'     => 'nullable|phone',
            'password'     => 'required|string|min:8|max:255|confirmed',
            'locale'       => 'required|in:' . implode(',', Lang::locales()),
            'country'      => 'nullable|in:' . implode(',', array_keys(self::countries())),
            'city'         => 'nullable|string|max:80',
            'gender'       => 'nullable|in:m,f',
            'terms'        => 'accepted',
        ], [
            'email.required_without' => __('auth.register.contact_required'),
            'phone.required_without' => __('auth.register.contact_required'),
        ]);
        if ($validator->fails()) {
            throw new ValidationException($validator->errors());
        }
        $data = $validator->validated();

        $id = AccountService::register($data, $request->ip());
        ActivityLog::record('user.registered', 'user', $id, ['via' => 'web', 'with' => $data['phone'] !== null ? 'phone' : 'email'], $id);
        Auth::login($id);

        $messages = [__('auth.register.success', ['name' => trim((string) $data['full_name'])])];
        if (!empty($data['email'])) {
            $user = User::findWithRole($id);
            if ($user !== null && VerificationController::sendVerification($user)) {
                $messages[] = __('auth.register.verify_sent', ['email' => $data['email']]);
            }
        }

        $target = $this->safeTarget($request->input('redirect')) ?? $this->safeTarget(Session::pull('_intended')) ?? route('account.dashboard', [], (string) $data['locale']);
        $response = $this->redirect($target);
        foreach ($messages as $message) {
            $response->with('success', $message);
        }

        return $response;
    }

    /**
     * Pays proposés (codes ISO 3166-1) avec leur nom dans la langue courante (extension intl).
     *
     * @return array<string, string>
     */
    public static function countries(): array
    {
        $codes = (array) config('countries', []);
        $intl = (string) config('app.intl_locales.' . Lang::getLocale(), 'fr_FR');
        $list = [];
        foreach ($codes as $code) {
            $list[$code] = \Locale::getDisplayRegion('-' . $code, $intl);
        }
        $collator = new \Collator($intl);
        $collator->asort($list);

        return $list;
    }

    private function safeTarget(mixed $target): ?string
    {
        if (!is_string($target) || $target === '' || !str_starts_with($target, '/') || str_starts_with($target, '//') || str_contains($target, "\n")) {
            return null;
        }

        return $target;
    }
}
