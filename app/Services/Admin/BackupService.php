<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Core\Database;
use App\Services\ActivityLog;

/**
 * Sauvegardes de la base (§12.5, §4.6.3) : export SQL complet (structure + données) compressé en gzip
 * dans storage/backups/, sans dépendre de mysqldump (absent sur mutualisé). Rétention : 14 jours.
 */
final class BackupService
{
    public const RETENTION_DAYS = 14;

    public static function directory(): string
    {
        $dir = storage_path('backups');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }

    /** Crée une sauvegarde et retourne le nom du fichier. */
    public static function create(?int $byUserId = null, string $origin = 'manual'): string
    {
        $pdo = Database::pdo();
        $name = 'darul-fatwa-' . gmdate('Ymd-His') . '-' . $origin . '.sql.gz';
        $path = self::directory() . '/' . $name;
        $gz = gzopen($path, 'wb6');
        if ($gz === false) {
            throw new \RuntimeException('Impossible d\'écrire la sauvegarde');
        }
        try {
            gzwrite($gz, "-- Darul Fatwa — sauvegarde du " . gmdate('Y-m-d H:i:s') . " UTC\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");
            $tables = array_map(static fn (array $r): string => (string) array_values($r)[0], Database::fetchAll('SHOW TABLES'));
            foreach ($tables as $table) {
                $create = Database::fetch('SHOW CREATE TABLE ' . Database::identifier($table));
                $createSql = (string) ($create['Create Table'] ?? '');
                gzwrite($gz, "DROP TABLE IF EXISTS " . Database::identifier($table) . ";\n" . $createSql . ";\n\n");
                $offset = 0;
                while (true) {
                    $rows = Database::fetchAll('SELECT * FROM ' . Database::identifier($table) . ' LIMIT 500 OFFSET ' . $offset);
                    if ($rows === []) {
                        break;
                    }
                    $columns = implode(', ', array_map([Database::class, 'identifier'], array_keys($rows[0])));
                    $values = [];
                    foreach ($rows as $row) {
                        $values[] = '(' . implode(', ', array_map(static fn ($v): string => $v === null ? 'NULL' : (is_int($v) || is_float($v) ? (string) $v : $pdo->quote((string) $v)), array_values($row))) . ')';
                    }
                    gzwrite($gz, 'INSERT INTO ' . Database::identifier($table) . ' (' . $columns . ") VALUES\n" . implode(",\n", $values) . ";\n");
                    $offset += 500;
                    if (count($rows) < 500) {
                        break;
                    }
                }
                gzwrite($gz, "\n");
            }
            gzwrite($gz, "SET FOREIGN_KEY_CHECKS = 1;\n");
        } finally {
            gzclose($gz);
        }
        ActivityLog::record('admin.backup.created', 'backup', null, ['file' => $name, 'origin' => $origin], $byUserId);

        return $name;
    }

    /** @return list<array{name: string, size: int, created_at: string}> du plus récent au plus ancien */
    public static function all(): array
    {
        $list = [];
        foreach (glob(self::directory() . '/*.sql.gz') ?: [] as $file) {
            $list[] = ['name' => basename($file), 'size' => (int) filesize($file), 'created_at' => gmdate('Y-m-d H:i:s', (int) filemtime($file))];
        }
        usort($list, static fn (array $a, array $b): int => strcmp($b['name'], $a['name']));

        return $list;
    }

    /** Chemin absolu d'une sauvegarde existante (nom strictement validé), sinon null. */
    public static function path(string $name): ?string
    {
        if (!preg_match('/^darul-fatwa-\d{8}-\d{6}-[a-z]+\.sql\.gz$/', $name)) {
            return null;
        }
        $path = self::directory() . '/' . $name;

        return is_file($path) ? $path : null;
    }

    public static function delete(string $name, ?int $byUserId = null): bool
    {
        $path = self::path($name);
        if ($path === null || !@unlink($path)) {
            return false;
        }
        ActivityLog::record('admin.backup.deleted', 'backup', null, ['file' => $name], $byUserId);

        return true;
    }

    /** Supprime les sauvegardes automatiques plus anciennes que la rétention. */
    public static function purge(int $days = self::RETENTION_DAYS): int
    {
        $count = 0;
        foreach (glob(self::directory() . '/*-cron.sql.gz') ?: [] as $file) {
            if (filemtime($file) < time() - $days * 86400 && @unlink($file)) {
                $count++;
            }
        }

        return $count;
    }

    /** Nombre d'archives audio hebdomadaires conservées (§12.1). */
    public const AUDIO_ARCHIVES_KEPT = 4;

    /**
     * Archive ZIP de tous les enregistrements (storage/audio) : « audio-AAAAMMJJ.zip » dans storage/backups/.
     * Retourne le nom du fichier, ou null si l'extension zip manque ou s'il n'y a rien à archiver.
     */
    public static function archiveAudio(): ?string
    {
        $source = storage_path('audio');
        if (!class_exists(\ZipArchive::class) || !is_dir($source)) {
            return null;
        }
        $name = 'audio-' . gmdate('Ymd') . '.zip';
        $path = self::directory() . '/' . $name;
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Impossible de créer l\'archive audio');
        }
        $count = 0;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getFilename() === '.gitkeep') {
                continue;
            }
            $zip->addFile($file->getPathname(), substr($file->getPathname(), strlen($source) + 1));
            $count++;
        }
        $zip->close();
        if ($count === 0) {
            @unlink($path);

            return null;
        }
        // Rotation : on ne garde que les dernières archives
        $archives = glob(self::directory() . '/audio-*.zip') ?: [];
        rsort($archives);
        foreach (array_slice($archives, self::AUDIO_ARCHIVES_KEPT) as $old) {
            @unlink($old);
        }
        ActivityLog::record('admin.backup.created', 'backup', null, ['file' => $name, 'origin' => 'cron', 'files' => $count], null);

        return $name;
    }
}
