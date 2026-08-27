<?php

/**
 * Ayuda para pruebas: comprueba un NAF y calcula sus dígitos de control correctos.
 * Uso: php scripts/naf-helper.php 123456789101
 */

function controlDigits(int $province, int $sequential): string
{
    $base = $sequential < 10000000
        ? $province * 10000000 + $sequential
        : (int) (str_pad((string) $province, 2, '0', STR_PAD_LEFT) . str_pad((string) $sequential, 8, '0', STR_PAD_LEFT));

    return str_pad((string) ($base % 97), 2, '0', STR_PAD_LEFT);
}

$input = $argv[1] ?? null;

if ($input) {
    $clean = strtoupper(preg_replace('/[\s\/.-]/', '', $input));
    $clean = preg_replace('/[A-Z]$/', '', $clean);

    if (! preg_match('/^\d{12}$/', $clean)) {
        echo "El número debe tener 12 dígitos (más la letra final opcional).\n";
        exit(1);
    }

    $province = (int) substr($clean, 0, 2);
    $sequential = (int) substr($clean, 2, 8);
    $given = substr($clean, 10, 2);
    $expected = controlDigits($province, $sequential);

    echo "Analizando: {$input}\n";
    echo "  Provincia:        {$province}\n";
    echo "  Nº secuencial:    {$sequential}\n";
    echo "  Control indicado: {$given}\n";
    echo "  Control correcto: {$expected}\n";
    echo $given === $expected
        ? "  => Los dígitos de control son correctos.\n"
        : "  => INCORRECTO. El número válido sería: " . substr($clean, 0, 10) . $expected . "T\n";

    exit(0);
}

echo "NAFs válidos para pruebas:\n\n";

$ejemplos = [
    [28, 12345678, 'Madrid (ejemplo oficial)'],
    [8, 45678901, 'Barcelona'],
    [46, 33221100, 'Valencia'],
    [41, 98765432, 'Sevilla'],
    [28, 1234567, 'Madrid, secuencial corto'],
];

foreach ($ejemplos as [$prov, $seq, $desc]) {
    $naf = str_pad((string) $prov, 2, '0', STR_PAD_LEFT)
        . str_pad((string) $seq, 8, '0', STR_PAD_LEFT)
        . controlDigits($prov, $seq)
        . 'T';

    echo '  ' . str_pad($naf, 16) . " {$desc}\n";
}
