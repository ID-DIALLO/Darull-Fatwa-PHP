<?php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Models\NewsletterSubscriber;
use App\Services\MailService;
use App\Services\Turnstile;

/**
 * Newsletter avec double confirmation par e-mail (§6). Le jeton de confirmation sert aussi au désabonnement.
 */
final class NewsletterController extends Controller
{
    public function subscribe(Request $request): Response
    {
        if (!Turnstile::passes($request->all(), $request->ip())) {
            throw new ValidationException(['newsletter_email' => [__('auth.robot.failed')]]);
        }
        $data = $this->validate($request, ['newsletter_email' => 'required|email|max:190']);
        $email = mb_strtolower(trim((string) $data['newsletter_email']));
        // La route /newsletter n'a pas de préfixe de langue : la langue vient du formulaire (page d'origine)
        $formLocale = $request->input('locale');
        $locale = Lang::isSupported(is_string($formLocale) ? $formLocale : null) ? (string) $formLocale : Lang::getLocale();
        $existing = NewsletterSubscriber::findByEmail($email);

        if ($existing !== null && $existing['status'] === 'active') {
            return $this->back($request, route('home'))->with('info', __('front.newsletter.already'));
        }

        $token = bin2hex(random_bytes(32));
        if ($existing === null) {
            NewsletterSubscriber::create(['email' => $email, 'locale' => $locale, 'status' => 'pending', 'token_hash' => hash('sha256', $token)]);
        } else {
            NewsletterSubscriber::update((int) $existing['id'], ['locale' => $locale, 'status' => 'pending', 'token_hash' => hash('sha256', $token)]);
        }
        MailService::send($email, 'newsletter_confirm', [
            'name'            => $email,
            'url'             => route('newsletter.confirm', ['token' => $token], $locale, true),
            'unsubscribe_url' => route('newsletter.unsubscribe', ['token' => $token], $locale, true),
        ], $locale);

        return $this->back($request, route('home'))->with('success', __('front.newsletter.subscribed'));
    }

    public function confirm(Request $request, string $token): Response
    {
        $subscriber = NewsletterSubscriber::findByToken($token);
        if ($subscriber === null) {
            return $this->redirectToRoute('home')->with('error', __('front.newsletter.invalid'));
        }
        Database::execute('UPDATE newsletter_subscribers SET status = ?, confirmed_at = COALESCE(confirmed_at, UTC_TIMESTAMP()) WHERE id = ?', ['active', (int) $subscriber['id']]);

        return $this->redirectToRoute('home')->with('success', __('front.newsletter.confirmed'));
    }

    public function unsubscribe(Request $request, string $token): Response
    {
        $subscriber = NewsletterSubscriber::findByToken($token);
        if ($subscriber === null) {
            return $this->redirectToRoute('home')->with('error', __('front.newsletter.invalid'));
        }
        NewsletterSubscriber::update((int) $subscriber['id'], ['status' => 'unsubscribed']);

        return $this->redirectToRoute('home')->with('success', __('front.newsletter.unsubscribed'));
    }
}
