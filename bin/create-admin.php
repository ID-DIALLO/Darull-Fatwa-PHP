<?php

declare(strict_types=1);

/*
 * Crée un compte d'administration.
 *
 *   php bin/create-admin.php                    super-administrateur (questions interactives)
 *   php bin/create-admin.php --role=admin       administrateur
 *   php bin/create-admin.php --role=admin --admin-name="…" --admin-email="…" --admin-password="…"
 */

require __DIR__ . '/_cli.php';

use App\Core\App;
use App\Core\Database;
use App\Core\Lang;
use App\Services\Installer;

$options = cli_options($argv);
$role = (string) ($options['role'] ?? 'superadmin');
if (!in_array($role, ['superadmin', 'admin', 'moderator', 'scholar'], true)) {
    cli_fail('Rôle invalide : superadmin, admin, moderator ou scholar.');
    exit(1);
}

App::create($basePath);
Lang::setLocale('fr');
$installer = new Installer($basePath);

try {
    Database::pdo();
} catch (PDOException $e) {
    cli_db_error($e, (array) App::instance()->config('database'));
    exit(1);
}

cli_title('Nouveau compte « ' . $role . ' »');
$data = cli_admin_data($installer, $options);
$id = $installer->createAdmin($data, $role);
cli_ok('Compte créé (n° ' . $id . ').');
