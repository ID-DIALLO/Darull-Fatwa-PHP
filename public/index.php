<?php

declare(strict_types=1);

/*
 * Darul Fatwa — contrôleur frontal unique : toutes les requêtes passent par ici.
 */

// Serveur PHP intégré (php -S) : les fichiers existants (CSS, images…) sont servis directement.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
    if ($file !== __FILE__ && is_file($file)) {
        return false;
    }
}

$basePath = dirname(__DIR__);

if (!is_file($basePath . '/vendor/autoload.php')) {
    http_response_code(503);
    echo 'Dépendances manquantes : lancez « composer install ».';
    exit;
}

require $basePath . '/vendor/autoload.php';

App\Core\App::create($basePath)->run();
