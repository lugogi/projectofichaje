<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Datos del responsable del tratamiento
    |--------------------------------------------------------------------------
    |
    | Aparecen en la cláusula de protección de datos que firma el candidato.
    | Sustituye estos valores por los de la empresa antes de producción.
    |
    */

    'controller' => [
        'name' => env('PRIVACY_CONTROLLER_NAME', 'Salamandra'),
        'legal_id' => env('PRIVACY_CONTROLLER_LEGAL_ID', 'B-00000000'),
        'address' => env('PRIVACY_CONTROLLER_ADDRESS', 'Dirección pendiente de configurar'),
        'email' => env('PRIVACY_CONTROLLER_EMAIL', 'privacidad@salamandra.local'),
        'dpo_email' => env('PRIVACY_DPO_EMAIL', null),
    ],

    /*
    | Versión del texto de consentimiento. Súbela cada vez que cambie la
    | redacción: queda guardada con cada solicitud para saber qué aceptó
    | exactamente cada persona.
    */
    'consent_version' => env('PRIVACY_CONSENT_VERSION', '2026-08'),

    /*
    | Enlaces normativos que se muestran en la cláusula.
    */
    'links' => [
        'gdpr' => 'https://eur-lex.europa.eu/legal-content/ES/TXT/?uri=CELEX:32016R0679',
        'lopdgdd' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2018-16673',
        'aepd' => 'https://www.aepd.es/',
    ],
];
