<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

/**
 * Interface unique d'intégration WhatsApp (§4.3) : niveau 1 « link » (wa.me, V1) et niveau 2 « cloud_api » (V2).
 * Le reste du code ne dépend que de cette interface.
 */
interface WhatsAppGateway
{
    /** « link » ou « cloud_api ». */
    public function mode(): string;

    /** Lien ouvrant une conversation WhatsApp avec un message prérempli (null si le numéro est invalide). */
    public function conversationLink(string $phone, string $text): ?string;

    /**
     * Envoi automatique d'un message texte (niveau 2 uniquement). Retourne false si le mode ne le permet pas.
     *
     * @param array<string, mixed> $context question_id, user_id, created_by… (journal whatsapp_messages)
     */
    public function sendText(string $phone, string $text, array $context = []): bool;

    /** L'envoi automatique est-il disponible ? */
    public function canSend(): bool;
}
