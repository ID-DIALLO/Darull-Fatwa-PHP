<?php

declare(strict_types=1);

/*
 * Tâches planifiées — à exécuter chaque minute par le cron du serveur :
 *   * * * * * php /chemin/vers/darul-fatwa/cron/run.php >> /dev/null 2>&1
 *
 * Chaque minute : file d'envoi (e-mails). Chaque jour : rappels de retard (08:00), clôture automatique (02:30),
 * rotation des journaux, nettoyage des jetons, tentatives de connexion et limites de débit (03:00).
 * Sauvegarde de la base chaque jour (01:00). Le sitemap arrive en Phase 8.
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
use App\Services\Maintenance;
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

    // Chaque jour à 08:00 (heure locale) : rappels des questions en retard (§4.12)
    if ($hour === 8 && $minute === 0) {
        $late = Maintenance::remindLateQuestions();
        if ($late > 0) {
            Logger::info("Rappels : {$late} question(s) en retard signalée(s)");
        }
    }

    // Chaque jour à 02:30 : clôture automatique des questions répondues depuis 30 jours (§4.2.3)
    if ($hour === 2 && $minute === 30) {
        $closed = Maintenance::autoCloseAnswered();
        if ($closed > 0) {
            Logger::info("Clôture automatique : {$closed} question(s)");
        }
    }

    // Chaque jour à 01:00 : sauvegarde de la base (rétention 14 jours, §12.5)
    if ($hour === 1 && $minute === 0) {
        $file = \App\Services\Admin\BackupService::create(null, 'cron');
        $purged = \App\Services\Admin\BackupService::purge();
        Logger::info("Sauvegarde automatique : {$file}" . ($purged > 0 ? " ({$purged} ancienne(s) supprimée(s))" : ''));
    }

    // Chaque jour à 03:00 : ménage
    if ($hour === 3 && $minute === 0) {
        Logger::rotate(30);
        TokenService::purge();
        LoginThrottle::purge(30);
        QueueService::purge(30);
        \App\Services\Admin\ExportService::purge(24);
        Database::execute('DELETE FROM rate_limits WHERE reset_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)');
    }
} catch (\Throwable $e) {
    Logger::exception($e);
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}
