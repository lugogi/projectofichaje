<?php

namespace App\Services;

class SpanishDocumentValidator
{
    private const DNI_LETTERS = 'TRWAGMYFPDXBNJZSQVHLCKE';

    public static function validateDni(string $dni): bool
    {
        $dni = strtoupper(preg_replace('/[\s-]/', '', $dni));

        if (! preg_match('/^(\d{8})([A-Z])$/', $dni, $matches)) {
            return false;
        }

        return self::DNI_LETTERS[(int) $matches[1] % 23] === $matches[2];
    }

    public static function validateNie(string $nie): bool
    {
        $nie = strtoupper(preg_replace('/[\s-]/', '', $nie));

        if (! preg_match('/^([XYZ])(\d{7})([A-Z])$/', $nie, $matches)) {
            return false;
        }

        $prefix = match ($matches[1]) {
            'X' => '0',
            'Y' => '1',
            'Z' => '2',
            default => '',
        };

        $number = $prefix . $matches[2];

        return self::DNI_LETTERS[(int) $number % 23] === $matches[3];
    }

    public static function validateDocument(string $type, string $number): bool
    {
        $number = strtoupper(trim($number));

        return match ($type) {
            'dni', 'nif' => self::validateDni($number),
            'nie' => self::validateNie($number),
            'ss' => preg_match('/^[0-9]{12}$/', $number) === 1,
            default => false,
        };
    }

    /**
     * Normaliza un NAF quitando separadores: "28/12345678-40 T" -> "281234567840T".
     */
    public static function normalizeNaf(string $naf): string
    {
        return strtoupper(preg_replace('/[\s\/.-]/', '', $naf));
    }

    /**
     * Valida el Número de Afiliación a la Seguridad Social.
     *
     * Estructura: 2 dígitos de provincia + 8 de número secuencial + 2 de control.
     * Los dígitos de control son el resto de dividir entre 97 los diez primeros,
     * con una salvedad: si el secuencial es menor de 10.000.000 no se concatena,
     * sino que la provincia se multiplica por 10.000.000 y se suma.
     *
     * @param bool $requireHolder Exige la "T" final que identifica al titular
     */
    public static function validateNaf(string $naf, bool $requireHolder = true): bool
    {
        $clean = self::normalizeNaf($naf);

        if ($requireHolder) {
            if (! str_ends_with($clean, 'T')) {
                return false;
            }

            $clean = substr($clean, 0, -1);
        } elseif (preg_match('/[A-Z]$/', $clean)) {
            $clean = substr($clean, 0, -1);
        }

        if (! preg_match('/^\d{12}$/', $clean)) {
            return false;
        }

        $province = (int) substr($clean, 0, 2);
        $sequential = (int) substr($clean, 2, 8);
        $control = (int) substr($clean, 10, 2);

        if ($province < 1 || $province > 56) {
            return false;
        }

        $base = $sequential < 10000000
            ? $province * 10000000 + $sequential
            : (int) (substr($clean, 0, 2) . substr($clean, 2, 8));

        return $base % 97 === $control;
    }

    /**
     * Indica si el NAF corresponde a un beneficiario (acaba en otra letra que no es T).
     */
    public static function isBeneficiaryNaf(string $naf): bool
    {
        $clean = self::normalizeNaf($naf);

        return (bool) preg_match('/[A-SU-Z]$/', $clean);
    }

    public static function validateIban(string $iban): bool
    {
        $iban = strtoupper(preg_replace('/\s+/', '', $iban));

        if (! preg_match('/^ES\d{22}$/', $iban)) {
            return false;
        }

        $rearranged = substr($iban, 4) . substr($iban, 0, 4);
        $numeric = '';

        foreach (str_split($rearranged) as $char) {
            $numeric .= ctype_alpha($char) ? (ord($char) - 55) : $char;
        }

        $remainder = 0;
        foreach (str_split($numeric) as $digit) {
            $remainder = ($remainder * 10 + (int) $digit) % 97;
        }

        return $remainder === 1;
    }

    public static function spanishBankName(string $iban): ?string
    {
        $iban = strtoupper(preg_replace('/\s+/', '', $iban));

        if (! str_starts_with($iban, 'ES') || strlen($iban) < 8) {
            return null;
        }

        $code = substr($iban, 4, 4);

        $banks = [
            '0049' => 'Banco Santander',
            '0182' => 'BBVA',
            '2100' => 'CaixaBank',
            '0081' => 'Banco Sabadell',
            '2038' => 'Bankinter',
            '0128' => 'Bankia / CaixaBank',
            '0075' => 'Banco Popular',
            '0239' => 'EVO Banco',
            '1465' => 'ING',
            '0086' => 'Banco Cooperativo Español',
            '0030' => 'Banco Español de Crédito',
            '0019' => 'Deutsche Bank',
            '0061' => 'Banca March',
            '0073' => 'Openbank',
            '0234' => 'Pibank',
            '0138' => 'Banco Mediolanum',
            '0220' => 'Banco Finantia Sofinloc',
            '0216' => 'Targobank',
            '3058' => 'Cajamar',
            '3025' => 'Caja de Ingenieros',
            '3035' => 'Laboral Kutxa',
            '2085' => 'Ibercaja',
            '2080' => 'Abanca',
            '0078' => 'Banco Pueyo',
            '0238' => 'Banco Pastor',
        ];

        return $banks[$code] ?? null;
    }
}
