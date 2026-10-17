<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Core\Database;
use App\Core\Logger;
use App\Services\PhoneNumber;

/**
 * Niveau 1 (V1, gratuit, sans API) : liens wa.me préremplis ; l'envoi réel est fait par la personne
 * depuis son téléphone, puis « marqué comme envoyé » (journal whatsapp_messages).
 */
final class LinkGateway implements WhatsAppGateway
{
    public function mode(): string
    {
        return 'link';
    }

    public function conversationLink(string $phone, string $text): ?string
    {
        $normalized = PhoneNumber::normalize($phone);
        if (!PhoneNumber::isValid($normalized)) {
            return null;
        }

        return 'https://wa.me/' . PhoneNumber::forWhatsApp((string) $normalized) . ($text !== '' ? '?text=' . rawurlencode($text) : '');
    }

    public function sendText(string $phone, string $text, array $context = []): bool
    {
        Logger::info('WhatsApp niveau 1 : envoi automatique indisponible (lien wa.me uniquement)', ['phone' => mask_phone($phone)]);

        return false;
    }

    public function canSend(): bool
    {
        return false;
    }

    /**
     * Journalise un message envoyé manuellement depuis le téléphone (« marqué comme envoyé sur WhatsApp »).
     *
     * @param array{question_id?: int|null, user_id?: int|null, created_by?: int|null, type?: string, media_id?: int|null} $context
     */
    public static function logManual(string $direction, string $phone, string $body, array $context = []): int
    {
        Database::execute(
            'INSERT INTO whatsapp_messages (direction, mode, phone, user_id, question_id, type, body, media_id, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $direction === 'in' ? 'in' : 'out',
                'link',
                mb_substr((string) PhoneNumber::normalize($phone), 0, 20),
                $context['user_id'] ?? null,
                $context['question_id'] ?? null,
                $context['type'] ?? 'link',
                mb_substr($body, 0, 65000),
                $context['media_id'] ?? null,
                'sent',
                $context['created_by'] ?? null,
            ],
        );

        return Database::lastInsertId();
    }
}
