<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Journal applicatif : storage/logs/app-AAAA-MM-JJ.log (un fichier par jour, rotation 30 jours).
 */
final class Logger
{
    private static string $directory = '';

    public static function setDirectory(string $directory): void
    {
        self::$directory = rtrim($directory, '/');
    }

    /** @param array<string, mixed> $context */
    public static function debug(string $message, array $context = []): void
    {
        self::log('debug', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public static function info(string $message, array $context = []): void
    {
        self::log('info', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public static function warning(string $message, array $context = []): void
    {
        self::log('warning', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public static function error(string $message, array $context = []): void
    {
        self::log('error', $message, $context);
    }

    public static function exception(\Throwable $e): void
    {
        self::error($e::class . ': ' . $e->getMessage(), [
            'file'  => $e->getFile() . ':' . $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);
    }

    /** @param array<string, mixed> $context */
    public static function log(string $level, string $message, array $context = []): void
    {
        if (self::$directory === '') {
            error_log('[' . $level . '] ' . $message);

            return;
        }
        if (!is_dir(self::$directory)) {
            @mkdir(self::$directory, 0775, true);
        }
        $line = sprintf(
            "[%s] %s: %s%s\n",
            gmdate('Y-m-d H:i:s'),
            strtoupper($level),
            str_replace(["\r", "\n"], ' ', $message),
            $context === [] ? '' : ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR),
        );
        @file_put_contents(self::$directory . '/app-' . gmdate('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
    }

    /** Supprime les journaux plus anciens que $days jours (appelé par le cron). */
    public static function rotate(int $days = 30): int
    {
        $deleted = 0;
        foreach (glob(self::$directory . '/app-*.log') ?: [] as $file) {
            if (filemtime($file) < time() - $days * 86400 && @unlink($file)) {
                $deleted++;
            }
        }

        return $deleted;
    }
}
