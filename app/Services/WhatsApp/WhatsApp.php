<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Core\Lang;
use App\Models\Setting;

/**
 * Fabrique de la passerelle WhatsApp selon le paramètre « whatsapp_mode » (ou WHATSAPP_MODE) et messages préremplis.
 */
final class WhatsApp
{
    private static ?WhatsAppGateway $gateway = null;

    public static function gateway(): WhatsAppGateway
    {
        if (self::$gateway === null) {
            $mode = Setting::get('whatsapp_mode') ?? (string) config('whatsapp.mode', 'link');
            self::$gateway = $mode === 'cloud_api' ? new CloudApiGateway() : new LinkGateway();
        }

        return self::$gateway;
    }

    public static function setGateway(?WhatsAppGateway $gateway): void
    {
        self::$gateway = $gateway;
    }

    /** Numéro officiel de la plateforme (null tant qu'il n'est pas renseigné). */
    public static function officialNumber(): ?string
    {
        $number = Setting::get('whatsapp_number') ?? (string) config('whatsapp.official_number', '');

        return $number !== '' && !str_contains($number, 'X') ? $number : null;
    }

    /**
     * Message prérempli du professeur vers le membre : résumé + lien vers la réponse complète, dans la langue de la réponse.
     *
     * @param array<string, mixed> $question
     */
    public static function scholarReplyText(array $question, string $locale, ?string $summary = null): string
    {
        $locale = Lang::isSupported($locale) ? $locale : Lang::getDefault();
        $url = route('account.questions.show', ['reference' => $question['reference']], $locale, true);

        return Lang::get('scholar.whatsapp.reply_text', [
            'reference' => (string) $question['reference'],
            'summary'   => $summary !== null && $summary !== '' ? "\n\n" . $summary : '',
            'url'       => $url,
        ], $locale);
    }
}
