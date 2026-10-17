<?php

// Transactional emails — English
return [
    'common' => [
        'greeting'        => 'Assalamu alaykum :name,',
        'signature'       => 'The Darul Fatwa team',
        'footer'          => 'You are receiving this email because a Darul Fatwa account is linked to this address.',
        'button_fallback' => 'If the button does not work, copy this link into your browser:',
        'ignore'          => 'If you did not make this request, simply ignore this email.',
        'visit'           => 'Visit the website',
    ],
    'verify_email' => [
        'subject' => 'Verify your email address — Darul Fatwa',
        'title'   => 'Welcome to Darul Fatwa',
        'body'    => 'Thank you for creating your account. Confirm your email address to receive the answers to your questions.',
        'button'  => 'Verify my email address',
        'expires' => 'This link is valid for :hours hours.',
    ],
    'reset_password' => [
        'subject' => 'Reset your password — Darul Fatwa',
        'title'   => 'New password',
        'body'    => 'You asked to reset your password. Click the button below to choose a new one.',
        'button'  => 'Choose a new password',
        'expires' => 'This link is valid for :minutes minutes and can only be used once.',
    ],
    'password_changed' => [
        'subject' => 'Your password has been changed — Darul Fatwa',
        'title'   => 'Password changed',
        'body'    => 'The password of your account has just been changed. If you did not make this change, reset your password immediately and contact us.',
    ],
    'account_deleted' => [
        'subject' => 'Your account has been deleted — Darul Fatwa',
        'title'   => 'Account deleted',
        'body'    => 'Your Darul Fatwa account and your private questions have been deleted, as you requested. May Allah make things easy for you.',
    ],
];
