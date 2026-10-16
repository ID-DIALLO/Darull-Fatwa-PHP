<?php

declare(strict_types=1);

/*
 * Installation de Darul Fatwa : vérifications, fichier .env, base de données (schéma + données initiales),
 * compte super-administrateur.
 *
 *   php bin/install.php                 installation interactive
 *   php bin/install.php --fresh         SUPPRIME toutes les tables puis réinstalle (confirmation demandée)
 *   php bin/install.php --fresh --force sans confirmation
 *   php bin/install.php --admin-name="…" --admin-email="…" --admin-password="…"   sans questions
 *   php bin/install.php --no-admin      sans création de compte
 */

require __DIR__ . '/_cli.php';

use App\Core\App;
use App\Core\Database;
use App\Core\Lang;
use App\Services\Installer;

$options = cli_options($argv);
$installer = new Installer($basePath);

cli_line(cli_color('Darul Fatwa – دار الفتوى · Installation', 'cyan'));

// 1. Prérequis
cli_title('1. Vérification du serveur');
$blocking = false;
foreach (Installer::requirements() as $check) {
    if ($check['ok']) {
        cli_ok($check['label']);
    } elseif ($check['required']) {
        cli_fail($check['label']);
        $blocking = true;
    } else {
        cli_warn($check['label']);
    }
}
if ($blocking) {
    cli_fail('Prérequis manquants : installation interrompue.');
    exit(1);
}

// 2. Fichier .env
cli_title('2. Configuration (.env)');
if ($installer->ensureEnvFile()) {
    cli_ok('Fichier .env créé à partir de .env.example');
    if (!isset($options['admin-password'])) {
        cli_line('  Paramètres de connexion à la base de données :');
        $installer->writeEnv([
            'DB_HOST'     => cli_ask('Hôte', '127.0.0.1'),
            'DB_PORT'     => cli_ask('Port', '3306'),
            'DB_DATABASE' => cli_ask('Nom de la base', 'darul_fatwa'),
            'DB_USERNAME' => cli_ask('Utilisateur', 'root'),
            'DB_PASSWORD' => cli_secret('Mot de passe'),
        ]);
    }
} else {
    cli_ok('Fichier .env existant conservé');
}
if ($installer->ensureAppKey()) {
    cli_ok('Clé secrète APP_KEY générée');
}

$app = App::create($basePath);
Lang::setLocale('fr');
$dbConfig = (array) $app->config('database');

// 3. Base de données
cli_title('3. Base de données');
if ($installer->createDatabase($dbConfig)) {
    cli_ok('Base « ' . $dbConfig['database'] . ' » prête (utf8mb4_unicode_ci)');
} else {
    cli_warn('Création de la base impossible (droits insuffisants) : elle doit exister (panneau d\'hébergement).');
}

try {
    $pdo = Database::pdo();
} catch (PDOException $e) {
    cli_fail('Connexion impossible : ' . $e->getMessage());
    cli_line('  Vérifiez DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME et DB_PASSWORD dans .env');
    exit(1);
}
cli_ok('Connecté à ' . $installer->serverVersion($pdo));

$tables = $installer->existingTables($pdo);
if ($tables !== []) {
    if (!isset($options['fresh'])) {
        cli_fail('La base contient déjà ' . count($tables) . ' tables : Darul Fatwa semble déjà installé.');
        cli_line('  • Pour appliquer les nouvelles migrations : php bin/migrate.php');
        cli_line('  • Pour créer un administrateur           : php bin/create-admin.php');
        cli_line('  • Pour TOUT effacer et réinstaller       : php bin/install.php --fresh');
        exit(1);
    }
    cli_warn('Option --fresh : les ' . count($tables) . ' tables existantes et TOUTES leurs données vont être supprimées.');
    if (!isset($options['force']) && !cli_confirm('Confirmez-vous la suppression définitive ?')) {
        cli_line('  Installation annulée.');
        exit(1);
    }
    $installer->dropAllTables($pdo);
    cli_ok('Tables supprimées');
}

try {
    $installer->installSchema($pdo);
} catch (PDOException $e) {
    cli_fail('Erreur SQL : ' . $e->getMessage());
    exit(1);
}
cli_ok('Schéma créé : ' . count($installer->existingTables($pdo)) . ' tables');
cli_ok('Données initiales : ' . implode(', ', array_map('basename', $installer->seedFiles())));

// 4. Super-administrateur (jamais dans un fichier SQL)
if (!isset($options['no-admin'])) {
    cli_title('4. Compte super-administrateur');
    $data = cli_admin_data($installer, $options);
    $id = $installer->createAdmin($data, 'superadmin');
    cli_ok('Super-administrateur créé (n° ' . $id . ')');
}

// 5. Dossiers de stockage
foreach (['audio', 'private', 'cache', 'logs', 'exports', 'backups'] as $dir) {
    $path = $basePath . '/storage/' . $dir;
    if (!is_dir($path)) {
        mkdir($path, 0775, true);
    }
    if (!is_writable($path)) {
        cli_warn('storage/' . $dir . ' n\'est pas accessible en écriture');
    }
}

cli_title('Installation terminée');
cli_line('  Lancez le site en local : php -S localhost:8000 -t public public/index.php');
cli_line('  Puis ouvrez            : ' . $app->config('app.url') . '/fr');
