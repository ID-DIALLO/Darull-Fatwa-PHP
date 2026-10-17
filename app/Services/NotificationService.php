<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Lang;
use App\Core\Logger;
use App\Models\Notification;
use App\Models\User;

/**
 * Notifications (§4.12) : in-app (cloche) + e-mail via la file message_queue, dans la langue du destinataire.
 * Les libellés viennent de lang/{locale}/notifications.php (clé = type, paramètres = data).
 * WhatsApp automatique : V2 (Cloud API) ; en V1 le lien wa.me est proposé dans l'interface.
 */
final class NotificationService
{
    /**
     * @param array<string, scalar|null> $data Paramètres du message (reference, rubrique…)
     * @param string|null $url Chemin relatif de la page concernée (dans la langue du destinataire si possible)
     * @param string|null $emailTemplate Gabarit emails/{template}.php ; null = pas d'e-mail
     * @param array<string, mixed> $emailData Données supplémentaires pour l'e-mail
     */
    public static function notify(int $userId, string $type, array $data = [], ?string $url = null, ?string $emailTemplate = 'notification', array $emailData = []): void
    {
        $user = User::findWithRole($userId);
        if ($user === null || $user['status'] !== 'active') {
            return;
        }
        try {
            Notification::create([
                'user_id' => $userId,
                'type'    => mb_substr($type, 0, 60),
                'data'    => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'url'     => $url !== null ? mb_substr($url, 0, 255) : null,
            ]);
        } catch (\Throwable $e) {
            Logger::exception($e);
        }

        if ($emailTemplate !== null && !empty($user['email']) && (int) $user['notify_email'] === 1) {
            $locale = (string) $user['locale'];
            QueueService::push('email', (string) $user['email'], $emailTemplate, $locale, $emailData + [
                'name'    => User::publicName($user),
                'type'    => $type,
                'title'   => Lang::get('notifications.' . $type . '.title', $data, $locale),
                'message' => Lang::get('notifications.' . $type . '.message', $data, $locale),
                'url'     => $url !== null ? url($url) : url('/' . $locale),
                'data'    => $data,
            ]);
        }
    }

    /** Même notification pour plusieurs destinataires (ex. tous les administrateurs). @param list<int> $userIds */
    public static function notifyMany(array $userIds, string $type, array $data = [], ?string $url = null, ?string $emailTemplate = 'notification'): void
    {
        foreach (array_unique($userIds) as $id) {
            self::notify((int) $id, $type, $data, $url, $emailTemplate);
        }
    }

    /** Libellé affiché dans la cloche / la page des notifications (langue courante). @param array<string, mixed> $row */
    public static function render(array $row): array
    {
        $data = is_array($row['data'] ?? null) ? $row['data'] : [];
        $type = (string) $row['type'];

        return [
            'title'   => Lang::has('notifications.' . $type . '.title') ? __('notifications.' . $type . '.title', $data) : $type,
            'message' => Lang::has('notifications.' . $type . '.message') ? __('notifications.' . $type . '.message', $data) : '',
            'icon'    => Lang::has('notifications.' . $type . '.icon') ? __('notifications.' . $type . '.icon') : 'bell',
        ];
    }
}
