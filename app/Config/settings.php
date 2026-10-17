<?php

declare(strict_types=1);

/*
 * Paramètres modifiables dans l'administration (§4.6.3), par groupe.
 * type : text | textarea | email | url | int | bool | select:a,b | secret (chiffré, jamais affiché).
 * superadmin : groupe réservé au super-administrateur (§2.1).
 */
return [
    'general' => [
        'icon' => 'globe2',
        'fields' => [
            'site_name'            => ['type' => 'text', 'rules' => 'required|max:80'],
            'site_slogan_fr'       => ['type' => 'text', 'rules' => 'max:160'],
            'site_slogan_ar'       => ['type' => 'text', 'rules' => 'max:160', 'dir' => 'rtl'],
            'site_slogan_en'       => ['type' => 'text', 'rules' => 'max:160'],
            'prophet_honorific_fr' => ['type' => 'text', 'rules' => 'max:20'],
            'maintenance_mode'     => ['type' => 'bool'],
        ],
    ],
    'contact' => [
        'icon' => 'envelope',
        'fields' => [
            'contact_email'   => ['type' => 'email', 'rules' => 'required|email'],
            'contact_phone'   => ['type' => 'text', 'rules' => 'max:30'],
            'contact_address' => ['type' => 'text', 'rules' => 'max:160'],
            'opening_hours'   => ['type' => 'text', 'rules' => 'max:160'],
        ],
    ],
    'social' => [
        'icon' => 'share',
        'fields' => [
            'social_facebook'  => ['type' => 'url', 'rules' => 'url|max:255'],
            'social_youtube'   => ['type' => 'url', 'rules' => 'url|max:255'],
            'social_instagram' => ['type' => 'url', 'rules' => 'url|max:255'],
            'social_telegram'  => ['type' => 'url', 'rules' => 'url|max:255'],
            'social_x'         => ['type' => 'url', 'rules' => 'url|max:255'],
            'social_tiktok'    => ['type' => 'url', 'rules' => 'url|max:255'],
        ],
    ],
    'questions' => [
        'icon' => 'chat-left-dots',
        'fields' => [
            'voice_max_seconds'   => ['type' => 'int', 'rules' => 'required|integer|between:30,1800'],
            'audio_max_mb'        => ['type' => 'int', 'rules' => 'required|integer|between:1,50'],
            'questions_per_day'   => ['type' => 'int', 'rules' => 'required|integer|between:1,100'],
            'question_late_hours' => ['type' => 'int', 'rules' => 'required|integer|between:1,720'],
            'auto_close_days'     => ['type' => 'int', 'rules' => 'required|integer|between:1,365'],
            'auto_assign'         => ['type' => 'bool'],
        ],
    ],
    'home' => [
        'icon' => 'house',
        'fields' => [
            'stats_answered' => ['type' => 'int', 'rules' => 'integer|min:0'],
            'stats_members'  => ['type' => 'int', 'rules' => 'integer|min:0'],
            'stats_articles' => ['type' => 'int', 'rules' => 'integer|min:0'],
            'stats_scholars' => ['type' => 'int', 'rules' => 'integer|min:0'],
        ],
    ],
    'whatsapp' => [
        'icon' => 'whatsapp',
        'superadmin' => true,
        'fields' => [
            'whatsapp_number'          => ['type' => 'text', 'rules' => 'max:20'],
            'whatsapp_mode'            => ['type' => 'select:link,cloud_api'],
            'whatsapp_phone_number_id' => ['type' => 'text', 'rules' => 'max:40'],
            'whatsapp_access_token'    => ['type' => 'secret'],
            'whatsapp_app_secret'      => ['type' => 'secret'],
            'whatsapp_verify_token'    => ['type' => 'secret'],
        ],
    ],
    'mail' => [
        'icon' => 'send',
        'superadmin' => true,
        'fields' => [
            'mail_host'         => ['type' => 'text', 'rules' => 'max:190'],
            'mail_port'         => ['type' => 'int', 'rules' => 'integer|between:1,65535'],
            'mail_encryption'   => ['type' => 'select:tls,ssl,none'],
            'mail_username'     => ['type' => 'text', 'rules' => 'max:190'],
            'mail_password'     => ['type' => 'secret'],
            'mail_from_address' => ['type' => 'email', 'rules' => 'email'],
            'mail_from_name'    => ['type' => 'text', 'rules' => 'max:80'],
        ],
    ],
    'security' => [
        'icon' => 'shield-lock',
        'superadmin' => true,
        'fields' => [
            'turnstile_site_key'   => ['type' => 'text', 'rules' => 'max:120'],
            'turnstile_secret_key' => ['type' => 'secret'],
        ],
    ],
];
