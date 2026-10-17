<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Core\Logger;

/**
 * Niveau 2 (V2) : WhatsApp Business Platform – Cloud API de Meta (§4.3). Architecture préparée :
 * les liens fonctionnent comme au niveau 1 ; l'envoi automatique sera implémenté avec les modèles
 * de messages approuvés, le jeton d'accès et le webhook signé.
 */
final class CloudApiGateway implements WhatsAppGateway
{
    public function __construct(private readonly LinkGateway $fallback = new LinkGateway())
    {
    }

    public function mode(): string
    {
        return 'cloud_api';
    }

    public function conversationLink(string $phone, string $text): ?string
    {
        return $this->fallback->conversationLink($phone, $text);
    }

    public function sendText(string $phone, string $text, array $context = []): bool
    {
        // V2 : POST https://graph.facebook.com/v20.0/{phone_number_id}/messages avec le jeton d'accès.
        Logger::warning('WhatsApp Cloud API non encore activée (V2) : message non envoyé', ['phone' => mask_phone($phone)]);

        return false;
    }

    public function canSend(): bool
    {
        return false;
    }
}
