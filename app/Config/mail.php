<?php

declare(strict_types=1);

return [
    'host'       => env('MAIL_HOST', 'localhost'),
    'port'       => (int) env('MAIL_PORT', 587),
    'encryption' => env('MAIL_ENCRYPTION', 'tls'),
    'username'   => env('MAIL_USERNAME', ''),
    'password'   => env('MAIL_PASSWORD', ''),
    'from'       => [
        'address' => env('MAIL_FROM_ADDRESS', 'contact@darulfatwa.com'),
        'name'    => env('MAIL_FROM_NAME', 'Darul Fatwa'),
    ],
];
