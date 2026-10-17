<?php

declare(strict_types=1);

// Envoi des e-mails (§4.12). MAIL_MAILER : smtp (production) ou log (développement : storage/logs/mail-*.log).
return [
    'mailer'     => env('MAIL_MAILER', 'smtp'),
    'host'       => env('MAIL_HOST', ''),
    'port'       => (int) env('MAIL_PORT', 587),
    'encryption' => env('MAIL_ENCRYPTION', 'tls'),
    'username'   => env('MAIL_USERNAME', ''),
    'password'   => env('MAIL_PASSWORD', ''),
    'from'       => [
        'address' => env('MAIL_FROM_ADDRESS', 'contact@darulfatwa.com'),
        'name'    => env('MAIL_FROM_NAME', 'Darul Fatwa'),
    ],
];
