<?php

declare(strict_types=1);

use App\Controllers\Front\HomeController;
use App\Core\Router;

/*
 * Routes de l'application (§11). Les routes publiques sont déclarées avec localized() :
 * segments en français pour /fr/, en anglais pour /en/ et /ar/. Toutes les routes sont nommées.
 */
return static function (Router $router): void {
    // « / » → langue détectée (cookie, profil, navigateur, français par défaut)
    $router->get('/', [HomeController::class, 'root'])->name('root');

    // Accueil : /fr, /ar, /en (Phase 0 : page de test du noyau)
    $router->localized('GET', '', [HomeController::class, 'index'], 'home');
};
