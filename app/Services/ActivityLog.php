<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Core\View;

/**
 * Journal des actions sensibles (table activity_logs, §12.1) : qui, quoi, sur quoi, quand, depuis où.
 * Toujours appelé pour : connexion, inscription, changement de mot de passe, suppression de compte,
 * changement de statut d'une question, actions d'administration…
 */
final class ActivityLog
{
    /** @param array<string, mixed> $details */
    public static function record(string $action, ?string $subjectType = null, ?int $subjectId = null, array $details = [], ?int $userId = null): void
    {
        $request = View::shared('request');
        $ip = $request instanceof Request ? $request->ip() : null;
        $agent = $request instanceof Request ? $request->userAgent() : null;

        try {
            Database::execute(
                'INSERT INTO activity_logs (user_id, action, subject_type, subject_id, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [
                    $userId ?? Auth::id(),
                    mb_substr($action, 0, 80),
                    $subjectType !== null ? mb_substr($subjectType, 0, 40) : null,
                    $subjectId,
                    $details === [] ? null : json_encode($details, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    $ip,
                    $agent !== '' ? $agent : null,
                ],
            );
        } catch (\Throwable $e) {
            // Le journal ne doit jamais faire échouer l'action elle-même.
            Logger::warning('Journal d\'activité indisponible : ' . $e->getMessage(), ['action' => $action]);
        }
    }
}
