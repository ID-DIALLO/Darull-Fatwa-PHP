<?php

declare(strict_types=1);

// Configuration générale de l'application (valeurs issues du fichier .env).
return [
    'name'           => env('APP_NAME', 'Darul Fatwa'),
    'env'            => env('APP_ENV', 'production'),
    'debug'          => (bool) env('APP_DEBUG', false),
    'url'            => rtrim((string) env('APP_URL', 'http://localhost:8000'), '/'),
    'key'            => env('APP_KEY', ''),
    'timezone'       => env('APP_TIMEZONE', 'Africa/Bamako'),
    'default_locale' => env('APP_DEFAULT_LOCALE', 'fr'),
    'locales'        => array_values(array_filter(array_map('trim', explode(',', (string) env('APP_LOCALES', 'fr,ar,en'))))),
    'rtl_locales'    => ['ar'],

    // Locales ICU utilisées pour le formatage des dates (§7.2)
    'intl_locales'   => ['fr' => 'fr_FR', 'ar' => 'ar', 'en' => 'en_GB'],

    // Anti-robot Cloudflare Turnstile (§4.1) : inactif tant que les clés sont vides
    'turnstile' => [
        'site_key'   => (string) env('TURNSTILE_SITE_KEY', ''),
        'secret_key' => (string) env('TURNSTILE_SECRET_KEY', ''),
    ],

    // Sessions (§12.1) : inactivité 2 h, 30 min pour l'administration
    'session' => [
        'name'               => 'df_session',
        'idle_minutes'       => 120,
        'admin_idle_minutes' => 30,
    ],

    // Middlewares exécutés sur toutes les requêtes HTTP (dans cet ordre)
    'global_middleware' => [
        \App\Middleware\SecurityHeadersMiddleware::class,
        \App\Middleware\MaintenanceMiddleware::class,
        \App\Middleware\LocaleMiddleware::class,
        \App\Middleware\CsrfMiddleware::class,
    ],

    // Alias utilisables dans la définition des routes : 'auth', 'role:admin,superadmin', 'throttle:5,60'
    'middleware_aliases' => [
        'auth'     => \App\Middleware\AuthMiddleware::class,
        'guest'    => \App\Middleware\GuestMiddleware::class,
        'role'     => \App\Middleware\RoleMiddleware::class,
        'throttle' => \App\Middleware\ThrottleMiddleware::class,
    ],
];
