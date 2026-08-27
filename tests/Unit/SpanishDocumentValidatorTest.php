<?php

namespace Tests\Unit;

use App\Services\SpanishDocumentValidator as Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SpanishDocumentValidatorTest extends TestCase
{
    /**
     * Construye un NAF válido calculando sus dígitos de control.
     */
    private static function buildNaf(int $province, int $sequential, string $suffix = 'T'): string
    {
        $base = $sequential < 10000000
            ? $province * 10000000 + $sequential
            : (int) (str_pad((string) $province, 2, '0', STR_PAD_LEFT) . str_pad((string) $sequential, 8, '0', STR_PAD_LEFT));

        $control = str_pad((string) ($base % 97), 2, '0', STR_PAD_LEFT);

        return str_pad((string) $province, 2, '0', STR_PAD_LEFT)
            . str_pad((string) $sequential, 8, '0', STR_PAD_LEFT)
            . $control
            . $suffix;
    }

    public function test_official_documented_example_is_valid(): void
    {
        // Ejemplo publicado por la Seguridad Social: 28 12345678 40
        $this->assertTrue(Validator::validateNaf('281234567840T'));
    }

    public function test_separators_are_ignored(): void
    {
        $this->assertTrue(Validator::validateNaf('28/12345678-40 T'));
        $this->assertTrue(Validator::validateNaf('28 12345678 40 T'));
    }

    public function test_sequential_below_ten_million_uses_the_special_rule(): void
    {
        // Con secuencial corto no se concatena: provincia * 10.000.000 + secuencial
        $naf = self::buildNaf(28, 1234567);

        $this->assertTrue(Validator::validateNaf($naf));

        // Concatenar sin más daría otros dígitos de control, y debe fallar
        $concatenated = (int) ('28' . '01234567');
        $wrongControl = str_pad((string) ($concatenated % 97), 2, '0', STR_PAD_LEFT);

        $this->assertFalse(Validator::validateNaf('2801234567' . $wrongControl . 'T'));
    }

    public function test_wrong_control_digits_are_rejected(): void
    {
        $this->assertFalse(Validator::validateNaf('281234567841T'));
    }

    public function test_holder_suffix_is_required(): void
    {
        $this->assertFalse(Validator::validateNaf('281234567840'), 'Sin letra final no es válido');
        $this->assertFalse(Validator::validateNaf('281234567840B'), 'Un beneficiario no puede darse de alta');
    }

    public function test_holder_suffix_can_be_skipped_when_not_required(): void
    {
        $this->assertTrue(Validator::validateNaf('281234567840', requireHolder: false));
        $this->assertTrue(Validator::validateNaf('281234567840B', requireHolder: false));
    }

    public function test_malformed_input_is_rejected(): void
    {
        $this->assertFalse(Validator::validateNaf('28123456784T'), 'Faltan dígitos');
        $this->assertFalse(Validator::validateNaf('2812345678401T'), 'Sobran dígitos');
        $this->assertFalse(Validator::validateNaf('AB1234567840T'), 'Provincia no numérica');
        $this->assertFalse(Validator::validateNaf('001234567840T'), 'Provincia 00 no existe');
        $this->assertFalse(Validator::validateNaf(''), 'Cadena vacía');
    }

    public function test_beneficiary_detection(): void
    {
        $this->assertTrue(Validator::isBeneficiaryNaf('281234567840B'));
        $this->assertFalse(Validator::isBeneficiaryNaf('281234567840T'));
        $this->assertFalse(Validator::isBeneficiaryNaf('281234567840'));
    }

    #[DataProvider('provinceProvider')]
    public function test_valid_nafs_across_provinces(int $province): void
    {
        $naf = self::buildNaf($province, 45678901);

        $this->assertTrue(Validator::validateNaf($naf), "NAF de la provincia {$province} debería ser válido");
    }

    public static function provinceProvider(): array
    {
        return [
            'Álava' => [1],
            'Barcelona' => [8],
            'Madrid' => [28],
            'Valencia' => [46],
            'Ceuta' => [51],
            'Melilla' => [52],
        ];
    }

    public function test_dni_and_nie_validation_still_works(): void
    {
        $this->assertTrue(Validator::validateDni('12345678Z'));
        $this->assertFalse(Validator::validateDni('12345678A'));
        $this->assertTrue(Validator::validateNie('X1234567L'));
        $this->assertFalse(Validator::validateNie('X1234567A'));
    }

    public function test_iban_validation_still_works(): void
    {
        $this->assertTrue(Validator::validateIban('ES9121000418450200051332'));
        $this->assertFalse(Validator::validateIban('ES9121000418450200051333'));
    }
}
