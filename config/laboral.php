<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Envío a la asesoría laboral
    |--------------------------------------------------------------------------
    |
    | Desde el exportador de plantilla se puede mandar el informe del mes
    | directamente a quien gestiona las nóminas. Mientras no haya correo,
    | el botón aparece igual y avisa de que aún no está configurado.
    |
    */

    'enabled' => (bool) env('LABORAL_ENABLED', false),
    'email' => env('LABORAL_EMAIL'),
    'name' => env('LABORAL_NAME', 'Asesoría laboral'),
];
