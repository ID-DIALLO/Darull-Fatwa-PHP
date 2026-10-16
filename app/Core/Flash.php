<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Messages flash affichés une seule fois (après une redirection).
 * Types : success, error, warning, info.
 */
final class Flash
{
    public static function add(string $type, string $message): void
    {
        $pending = (array) Session::get('_flash_new', []);
        $messages = (array) ($pending['_messages'] ?? []);
        $messages[] = ['type' => $type, 'message' => $message];
        Session::flash('_messages', $messages);
    }

    public static function success(string $message): void
    {
        self::add('success', $message);
    }

    public static function error(string $message): void
    {
        self::add('error', $message);
    }

    /** @return list<array{type: string, message: string}> */
    public static function all(): array
    {
        return (array) Session::getFlash('_messages', []);
    }
}
