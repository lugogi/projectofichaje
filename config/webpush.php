<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Claves VAPID
    |--------------------------------------------------------------------------
    |
    | Identifican al servidor ante el servicio de push del navegador.
    | Genera un par nuevo con: php artisan webpush:vapid
    |
    */

    'enabled' => env('WEBPUSH_ENABLED', true),

    /*
    | Apple exige que el subject sea un mailto: o una URL https válida.
    | Un http://localhost hace que APNs rechace el envío.
    */
    'subject' => env('WEBPUSH_SUBJECT', 'mailto:' . env('MAIL_FROM_ADDRESS', 'admin@fichatime.local')),

    'public_key' => env('VAPID_PUBLIC_KEY'),

    'private_key' => env('VAPID_PRIVATE_KEY'),

    /*
    | Tras este número de fallos consecutivos la suscripción se elimina.
    */
    'max_failures' => 3,

    /*
    | Tipos de evento que NO deben generar notificación push (solo campana).
    */
    'muted_event_types' => [],
];
