<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Restricción de fichaje por IP
    |--------------------------------------------------------------------------
    |
    | Si está activa, solo se permite fichar desde una IP autorizada: debe existir
    | una zona_fichaje activa (del centro de trabajo del empleado) cuya columna 'ip'
    | coincida con la IP desde la que llega la petición.
    |
    | La 'ip' de la zona puede ser:
    |   - Una IP exacta:      "203.0.113.5"
    |   - Un rango CIDR IPv4: "192.168.1.0/24"
    |
    | Déjalo en false durante el desarrollo local (la IP siempre es 127.0.0.1).
    | Ponlo en true en producción.
    |
    */
    'restriccion_ip' => env('FICHAJE_RESTRICCION_IP', false),

    /*
    |--------------------------------------------------------------------------
    | Horas semanales del contrato
    |--------------------------------------------------------------------------
    |
    | Jornada estándar para calcular el objetivo mensual (40 h → 8 h/día laborable).
    |
    */
    'horas_semanales_contrato' => env('FICHAJE_HORAS_SEMANALES', 40),
];
