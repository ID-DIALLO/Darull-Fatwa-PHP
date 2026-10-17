<?php

declare(strict_types=1);

/*
 * Tâches planifiées — à exécuter chaque minute par le cron du serveur :
 *   * * * * * php /chemin/vers/darul-fatwa/cron/run.php >> /dev/null 2>&1
 *
 * Chaque minute : file d'envoi (e-mails). Chaque jour à 03:00 : rotation des journaux (30 jours),
 * nettoyage des jetons expirés, des anciennes tentatives de connexion et des limites de débit.
 * Les autres tâches (rappels de retard, clôture automatique, sitemap, sauvegardes) arrivent avec leurs phases.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

$basePath = dirname(__DIR__);
require $basePath . '/vendor/autoload.php';

use App\Core\App;
use App\Core\Database;
use App\Core\Logger;
use App\Services\LoginThrottle;
use App\Services\QueueService;
use App\Services\TokenService;

App::create($basePath);

// Une seule exécution à la fois
$lock = fopen($basePath . '/storage/cache/cron.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0);
}

$minute = (int) date('i');
$hour = (int) date('G');

try {
    // Chaque minute : messages en attente (e-mails de notification)
    [$sent, $failed] = QueueService::process(25);
    if ($sent > 0 || $failed > 0) {
        Logger::info("File d'envoi : {$sent} envoyé(s), {$failed} en échec définitif");
    }

    // Chaque jour à 03:00 : ménage
    if ($hour === 3 && $minute === 0) {
        Logger::rotate(30);
        TokenService::purge();
        LoginThrottle::purge(30);
        QueueService::purge(30);
        Database::execute('DELETE FROM rate_limits WHERE reset_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)');
    }
} catch (\Throwable $e) {
    Logger::exception($e);
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}
