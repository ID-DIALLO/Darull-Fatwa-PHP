<?php

declare(strict_types=1);

// Intégration WhatsApp (§4.3) : niveau 1 « link » (wa.me), niveau 2 « cloud_api » (V2).
return [
    'mode'            => env('WHATSAPP_MODE', 'link'),
    'official_number' => env('WHATSAPP_OFFICIAL_NUMBER', ''),
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID', ''),
    'waba_id'         => env('WHATSAPP_WABA_ID', ''),
    'access_token'    => env('WHATSAPP_ACCESS_TOKEN', ''),
    'app_secret'      => env('WHATSAPP_APP_SECRET', ''),
    'verify_token'    => env('WHATSAPP_VERIFY_TOKEN', ''),
];
