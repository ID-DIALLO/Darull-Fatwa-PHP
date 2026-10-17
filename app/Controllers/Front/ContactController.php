<?php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Controller;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Core\Validator;
use App\Models\ContactMessage;
use App\Services\ActivityLog;
use App\Services\MailService;
use App\Services\PhoneNumber;
use App\Services\Turnstile;

/**
 * Formulaire de contact (§4.11) : anti-robot, 3 envois par heure et par IP (middleware throttle),
 * enregistrement en base, e-mail à l'administration et accusé de réception à l'expéditeur.
 */
final class ContactController extends Controller
{
    public function show(Request $request): Response
    {
        return $this->view('front/contact', ['subjects' => ContactMessage::SUBJECTS]);
    }

    public function send(Request $request): Response
    {
        if (!Turnstile::passes($request->all(), $request->ip())) {
            throw new ValidationException(['message' => [__('auth.robot.failed')]]);
        }
        $input = $request->all();
        $input['phone'] = PhoneNumber::normalize((string) ($input['phone_e164'] ?? '') !== '' ? (string) $input['phone_e164'] : (string) ($input['phone'] ?? ''));
        $input['email'] = isset($input['email']) && is_string($input['email']) ? mb_strtolower(trim($input['email'])) : null;

        $validator = Validator::make($input, [
            'name'    => 'required|string|min:2|max:120',
            'email'   => 'required_without:phone|nullable|email|max:190',
            'phone'   => 'required_without:email|nullable|phone',
            'subject' => 'required|in:' . implode(',', ContactMessage::SUBJECTS),
            'message' => 'required|string|min:10|max:5000',
        ], [
            'email.required_without' => __('front.contact.contact_help'),
            'phone.required_without' => __('front.contact.contact_help'),
        ]);
        if ($validator->fails()) {
            throw new ValidationException($validator->errors());
        }
        $data = $validator->validated();
        $locale = Lang::getLocale();

        $id = ContactMessage::create([
            'name'       => trim((string) $data['name']),
            'email'      => $data['email'] !== '' ? $data['email'] : null,
            'phone'      => $data['phone'],
            'subject'    => $data['subject'],
            'message'    => trim((string) $data['message']),
            'locale'     => $locale,
            'status'     => 'new',
            'ip_address' => $request->ip(),
        ]);
        ActivityLog::record('contact.received', 'contact_message', $id, ['subject' => $data['subject']]);

        $payload = [
            'name'          => trim((string) $data['name']),
            'email'         => $data['email'],
            'phone'         => $data['phone'],
            'subject_label' => __('front.contact.subjects.' . $data['subject'], [], Lang::getDefault()),
            'message'       => trim((string) $data['message']),
            'admin_url'     => route_exists('admin.contact.show') ? route('admin.contact.show', ['id' => $id], null, true) : url('/admin'),
        ];
        $adminEmail = setting('contact_email') ?? (string) config('mail.from.address');
        if ($adminEmail !== '' && str_contains($adminEmail, '@')) {
            MailService::send($adminEmail, 'contact_received', $payload, Lang::getDefault());
        }
        if (!empty($data['email'])) {
            MailService::send((string) $data['email'], 'contact_ack', $payload + ['subject_label' => __('front.contact.subjects.' . $data['subject'])], $locale, trim((string) $data['name']));
        }

        return $this->redirectToRoute('contact')->with('success', __('front.contact.sent'));
    }
}
