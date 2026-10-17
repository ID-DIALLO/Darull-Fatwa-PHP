<?php

declare(strict_types=1);

/*
 * Fichier SQL d'installation complet pour un import dans phpMyAdmin (hébergement sans accès SSH) :
 *   php bin/export-sql.php            → database/dist/darul_fatwa_install.sql
 *
 * Contenu : schéma (database/schema.sql) + données initiales (database/seeds/*.sql) + marquage des migrations
 * existantes comme appliquées (le schéma est toujours à jour). Aucun compte n'est créé : utilisez ensuite
 * la page /setup (avec SETUP_TOKEN dans .env) ou php bin/create-admin.php.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

$base = dirname(__DIR__);
$dir = $base . '/database/dist';
if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
}
$target = $dir . '/darul_fatwa_install.sql';

$out = "-- Darul Fatwa – دار الفتوى — installation complète de la base de données\n";
$out .= "-- Généré par bin/export-sql.php le " . gmdate('Y-m-d') . " ; importez ce fichier dans une base VIDE (phpMyAdmin > Importer).\n";
$out .= "-- Ne contient aucun compte : créez le premier administrateur via /setup ou php bin/create-admin.php.\n\n";
$out .= "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\nSET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n";

$strip = static fn (string $sql): string => trim(preg_replace('/^\s*(CREATE\s+DATABASE|USE)\b[^;]*;\s*$/mi', '', $sql) ?? $sql);

$out .= "-- ---------------------------------------------------------------------\n-- Schéma\n-- ---------------------------------------------------------------------\n";
$out .= $strip((string) file_get_contents($base . '/database/schema.sql')) . "\n\n";

$seeds = glob($base . '/database/seeds/*.sql') ?: [];
sort($seeds);
foreach ($seeds as $seed) {
    $out .= "-- ---------------------------------------------------------------------\n-- Données initiales : " . basename($seed) . "\n-- ---------------------------------------------------------------------\n";
    $out .= $strip((string) file_get_contents($seed)) . "\n\n";
}

$migrations = glob($base . '/database/migrations/*.sql') ?: [];
sort($migrations);
$out .= "-- ---------------------------------------------------------------------\n-- Migrations déjà incluses dans le schéma\n-- ---------------------------------------------------------------------\n";
$out .= "CREATE TABLE IF NOT EXISTS schema_migrations (\n  migration  VARCHAR(190) NOT NULL,\n  batch      INT UNSIGNED NOT NULL DEFAULT 0,\n  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n  PRIMARY KEY (migration)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n";
foreach ($migrations as $migration) {
    $out .= "INSERT IGNORE INTO schema_migrations (migration, batch) VALUES ('" . addslashes(basename($migration)) . "', 0);\n";
}
$out .= "\nSET FOREIGN_KEY_CHECKS = 1;\n";

file_put_contents($target, $out);
printf("%s écrit (%.0f ko, %d seeds, %d migrations)\n", str_replace($base . '/', '', $target), strlen($out) / 1024, count($seeds), count($migrations));
