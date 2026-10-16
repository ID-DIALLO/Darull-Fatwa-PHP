<?php

declare(strict_types=1);

/*
 * Applique les migrations de database/migrations/ (fichiers AAAA_MM_JJ_description.sql) non encore exécutées.
 *
 *   php bin/migrate.php            applique les migrations en attente
 *   php bin/migrate.php --status   affiche l'état sans rien modifier
 *
 * Rappel : toute modification du schéma = nouveau fichier de migration + mise à jour de database/schema.sql.
 */

require __DIR__ . '/_cli.php';

use App\Core\App;
use App\Core\Database;
use App\Services\Installer;

$options = cli_options($argv);
App::create($basePath);
$installer = new Installer($basePath);

try {
    $pdo = Database::pdo();
} catch (PDOException $e) {
    cli_fail('Connexion à la base impossible : ' . $e->getMessage());
    exit(1);
}

$pending = $installer->pendingMigrations($pdo);

if (isset($options['status'])) {
    cli_title('Migrations');
    foreach ($installer->appliedMigrations($pdo) as $name) {
        cli_ok($name);
    }
    foreach ($pending as $file) {
        cli_warn(basename($file) . ' (en attente)');
    }
    if ($pending === [] && $installer->migrationFiles() === []) {
        cli_line('  Aucune migration.');
    }
    exit(0);
}

if ($pending === []) {
    cli_ok('Base à jour : aucune migration en attente.');
    exit(0);
}

try {
    $installer->migrate($pdo, static fn (string $name) => cli_ok('Appliquée : ' . $name));
} catch (PDOException $e) {
    cli_fail('Échec : ' . $e->getMessage());
    exit(1);
}
cli_ok(count($pending) . ' migration(s) appliquée(s).');
