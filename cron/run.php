<?php

declare(strict_types=1);

/*
 * Tâches planifiées — à exécuter chaque minute par le cron du serveur :
 *   * * * * * php /chemin/vers/darul-fatwa/cron/run.php >> /dev/null 2>&1
 *
 * Phase 0 : rotation des journaux (30 jours). Les autres tâches (files d'e-mails/WhatsApp, rappels de retard,
 * clôture automatique, sitemap, statistiques, sauvegardes) arrivent avec leurs phases respectives.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

$basePath = dirname(__DIR__);
require $basePath . '/vendor/autoload.php';

use App\Core\App;
use App\Core\Logger;

App::create($basePath);

// Une seule exécution à la fois
$lock = fopen($basePath . '/storage/cache/cron.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0);
}

$minute = (int) date('i');
$hour = (int) date('G');

// Chaque jour à 03:00 : rotation des journaux applicatifs
if ($hour === 3 && $minute === 0) {
    Logger::rotate(30);
}

flock($lock, LOCK_UN);
fclose($lock);
