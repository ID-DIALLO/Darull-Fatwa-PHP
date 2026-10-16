<?php

declare(strict_types=1);

// Limites d'envoi de fichiers (§4.2, §12.1). L'audio est toujours stocké hors de public/.
return [
    'voice_max_seconds'   => (int) env('VOICE_MAX_SECONDS', 300),
    'audio_max_mb'        => (int) env('UPLOAD_AUDIO_MAX_MB', 10),
    'questions_per_day'   => (int) env('QUESTIONS_PER_DAY', 5),
    'question_late_hours' => (int) env('QUESTION_LATE_HOURS', 72),
    'audio_mimes'         => ['audio/mpeg', 'audio/mp3', 'audio/webm', 'audio/ogg', 'audio/mp4', 'audio/x-m4a', 'audio/aac', 'audio/wav', 'audio/x-wav'],
    'audio_extensions'    => ['mp3', 'webm', 'ogg', 'oga', 'm4a', 'mp4', 'aac', 'wav'],
    'image_mimes'         => ['image/jpeg', 'image/png', 'image/webp'],
    'image_max_mb'        => 5,
];
