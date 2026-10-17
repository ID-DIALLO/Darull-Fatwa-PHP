<?php

declare(strict_types=1);

/*
 * Restaure une sauvegarde créée par l'administration ou le cron (storage/backups/*.sql.gz, ou un .sql) :
 *
 *   php bin/restore-backup.php storage/backups/darul-fatwa-20261017-010000-cron.sql.gz
 *   php bin/restore-backup.php <fichier> --database=darul_fatwa_restore   (vers une autre base : test de restauration)
 *   php bin/restore-backup.php <fichier> --force                          (sans confirmation)
 *
 * ATTENTION : la base cible est remplacée table par table (DROP TABLE IF EXISTS puis CREATE/INSERT).
 */

require __DIR__ . '/_cli.php';

use App\Core\App;
use App\Services\Installer;

$options = cli_options($argv);
$file = $argv[1] ?? '';
if ($file === '' || str_starts_with($file, '--')) {
    cli_fail('Usage : php bin/restore-backup.php <fichier.sql.gz> [--database=nom] [--force]');
    exit(1);
}
if (!is_file($file)) {
    $file = is_file($basePath . '/' . $file) ? $basePath . '/' . $file : (is_file($basePath . '/storage/backups/' . $file) ? $basePath . '/storage/backups/' . $file : $file);
}
if (!is_file($file)) {
    cli_fail('Fichier introuvable : ' . $file);
    exit(1);
}

$app = App::create($basePath);
$config = (array) $app->config('database');
$database = (string) ($options['database'] ?? $config['database'] ?? '');

cli_title('Restauration');
cli_line('  Fichier : ' . $file . ' (' . round(filesize($file) / 1024) . ' ko)');
cli_line('  Base    : ' . $database . ' sur ' . ($config['host'] ?? 'localhost'));
if (!isset($options['force']) && !cli_confirm('Toutes les tables de cette base seront remplacées. Continuer ?', false)) {
    cli_warn('Restauration annulée.');
    exit(0);
}

try {
    $dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $config['host'] ?? '127.0.0.1', $config['port'] ?? 3306);
    $pdo = new PDO($dsn, (string) $config['username'], (string) $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', $database) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec('USE `' . str_replace('`', '', $database) . '`');
} catch (PDOException $e) {
    cli_db_error($e, $config);
    exit(1);
}

// Décompression dans un fichier temporaire, puis exécution instruction par instruction
$sqlFile = $file;
if (str_ends_with($file, '.gz')) {
    $sqlFile = tempnam(sys_get_temp_dir(), 'df-restore-') . '.sql';
    $in = gzopen($file, 'rb');
    $out = fopen($sqlFile, 'wb');
    if ($in === false || $out === false) {
        cli_fail('Impossible de décompresser la sauvegarde.');
        exit(1);
    }
    while (!gzeof($in)) {
        fwrite($out, (string) gzread($in, 1 << 20));
    }
    gzclose($in);
    fclose($out);
}

$installer = new Installer($basePath);
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
$statements = $installer->runSqlFile($pdo, $sqlFile);
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
if ($sqlFile !== $file) {
    @unlink($sqlFile);
}
$tables = (int) $pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();
cli_ok($statements . ' instruction(s) exécutée(s), ' . $tables . ' table(s) dans « ' . $database . ' ».');
