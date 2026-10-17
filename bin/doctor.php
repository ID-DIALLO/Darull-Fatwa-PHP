<?php

declare(strict_types=1);

/*
 * Vérification d'une installation (développement ou production) :
 *   php bin/doctor.php
 *
 * Contrôle PHP et ses extensions, le fichier .env (clé, debug, URL), la base (connexion, tables, migrations),
 * les dossiers de stockage, les ressources construites, le cron (dernière exécution), le courrier et HTTPS.
 * Code de sortie 1 si un point bloquant est en échec.
 */

require __DIR__ . '/_cli.php';

use App\Core\App;
use App\Core\Database;
use App\Services\Installer;

$options = cli_options($argv);
$blocking = 0;
$warnings = 0;

$check = static function (string $label, bool $ok, string $detail = '', bool $required = true) use (&$blocking, &$warnings): void {
    $text = $label . ($detail !== '' ? ' — ' . $detail : '');
    if ($ok) {
        cli_ok($text);
    } elseif ($required) {
        cli_fail($text);
        $blocking++;
    } else {
        cli_warn($text);
        $warnings++;
    }
};

cli_line(cli_color('Darul Fatwa · Vérification de l\'installation', 'cyan'));

cli_title('1. Serveur');
foreach (Installer::requirements() as $req) {
    $check($req['label'], (bool) $req['ok'], '', (bool) $req['required']);
}
$check('Extension zip (archives audio, export XLSX)', extension_loaded('zip'), '', false);
$check('Extension gd (images WebP, image de partage)', extension_loaded('gd') && function_exists('imagewebp'), '', false);
$check('upload_max_filesize ≥ 12M', (int) ini_get('upload_max_filesize') >= 12 || str_ends_with((string) ini_get('upload_max_filesize'), 'G'), (string) ini_get('upload_max_filesize'), false);
$check('post_max_size ≥ 14M', (int) ini_get('post_max_size') >= 14 || str_ends_with((string) ini_get('post_max_size'), 'G'), (string) ini_get('post_max_size'), false);

cli_title('2. Configuration (.env)');
$check('Fichier .env présent', is_file($basePath . '/.env'));
if (!is_file($basePath . '/.env')) {
    cli_fail('Copiez .env.example en .env (ou lancez php bin/install.php) puis relancez.');
    exit(1);
}
$app = App::create($basePath);
$env = (string) $app->config('app.env');
$debug = (bool) $app->config('app.debug');
$url = (string) $app->config('app.url');
$key = (string) $app->config('app.key');
$check('APP_KEY définie', $key !== '' && strlen(base64_decode(str_replace('base64:', '', $key), true) ?: '') >= 32, $key === '' ? 'vide : php bin/install.php la génère' : 'OK');
$check('APP_URL en HTTPS (indispensable pour le micro)', str_starts_with($url, 'https://') || str_contains($url, 'localhost') || str_contains($url, '127.0.0.1'), $url, $env === 'production');
$check('APP_DEBUG désactivé en production', !($env === 'production' && $debug), $env . ($debug ? ' / debug' : ''), $env === 'production');
$check('Courrier sortant configuré (SMTP ou paramètres admin)', (string) env('MAIL_HOST', '') !== '' || \App\Models\Setting::hasSecret('mail_password') || (string) \App\Models\Setting::get('mail_host', '') !== '', '', false);

cli_title('3. Base de données');
try {
    $pdo = Database::pdo();
    $installer = new Installer($basePath);
    $tables = $installer->existingTables($pdo);
    $check('Connexion', true, $installer->serverVersion($pdo));
    $check('Tables installées', count($tables) >= 40, count($tables) . ' tables' . (count($tables) < 40 ? ' : php bin/install.php ou import de database/dist/darul_fatwa_install.sql' : ''));
    if (count($tables) >= 40) {
        $pending = $installer->pendingMigrations($pdo);
        $check('Migrations à jour', $pending === [], $pending === [] ? '' : count($pending) . ' en attente : php bin/migrate.php');
        $admins = (int) Database::value("SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug IN ('admin','superadmin') AND u.status = 'active' AND u.deleted_at IS NULL");
        $check('Au moins un compte administrateur', $admins > 0, $admins > 0 ? $admins . ' compte(s)' : 'php bin/create-admin.php ou /setup');
        $scholars = (int) Database::value("SELECT COUNT(*) FROM scholar_profiles");
        $check('Professeurs créés', $scholars > 0, $scholars . ' profil(s)', false);
        $ftMin = (int) (Database::fetch("SHOW VARIABLES LIKE 'innodb_ft_min_token_size'")['Value'] ?? 3);
        $check('innodb_ft_min_token_size ≤ 3 (recherche sur mots courts)', $ftMin <= 3, (string) $ftMin, false);
    }
} catch (\PDOException $e) {
    $check('Connexion', false, $e->getMessage());
}

cli_title('4. Fichiers et dossiers');
foreach (['logs', 'cache', 'audio', 'backups', 'exports'] as $dir) {
    $path = storage_path($dir);
    $check('storage/' . $dir . ' accessible en écriture', is_dir($path) && is_writable($path), $path);
}
$check('public/uploads accessible en écriture', is_writable(public_path('uploads')), '', false);
$check('public/uploads/.htaccess (pas de PHP exécutable)', is_file(public_path('uploads/.htaccess')));
$check('.env hors de la racine web ou protégé', !is_file(public_path('.env')), is_file(public_path('.env')) ? 'un fichier .env se trouve dans public/ !' : '');
$manifest = public_path('assets/dist/manifest.json');
$built = is_file($manifest);
$check('Ressources construites (php bin/build-assets.php)', $built || $env !== 'production', $built ? date('Y-m-d H:i', (int) filemtime($manifest)) : 'absentes', false);
if ($built) {
    $newest = max(array_map('filemtime', array_merge(glob(public_path('assets/css/*.css')) ?: [], glob(public_path('assets/js/*.js')) ?: [])) ?: [0]);
    $check('Ressources construites à jour', $newest <= filemtime($manifest) + 1, $newest > filemtime($manifest) + 1 ? 'des sources sont plus récentes : relancez php bin/build-assets.php' : '', false);
}

cli_title('5. Tâches planifiées et sauvegardes');
$lock = storage_path('cache/cron.lock');
$last = is_file($lock) ? (int) filemtime($lock) : 0;
$check('Cron exécuté récemment (cron/run.php chaque minute)', $last > time() - 600, $last > 0 ? 'dernière exécution ' . date('Y-m-d H:i', $last) : 'jamais exécuté', $env === 'production');
$backups = glob(storage_path('backups/*.sql.gz')) ?: [];
rsort($backups);
$check('Sauvegarde de la base disponible', $backups !== [], $backups !== [] ? basename($backups[0]) : 'aucune (créée chaque nuit à 01:00 par le cron)', false);
$logs = glob(storage_path('logs/app-*.log')) ?: [];
rsort($logs);
if ($logs !== []) {
    $errors = (int) preg_match_all('/\] (ERROR|CRITICAL):/', (string) file_get_contents($logs[0]));
    $check('Aucune erreur dans le journal du jour', $errors === 0, basename($logs[0]) . ($errors > 0 ? " : {$errors} erreur(s)" : ''), false);
}

cli_line();
if ($blocking > 0) {
    cli_fail($blocking . ' point(s) bloquant(s)' . ($warnings > 0 ? ", {$warnings} avertissement(s)" : '') . '.');
    exit(1);
}
cli_ok('Installation correcte' . ($warnings > 0 ? " ({$warnings} avertissement(s) à examiner)" : '') . '.');
