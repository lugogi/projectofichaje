<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Libro Excel con una primera hoja de resumen de toda la plantilla exportada
 * (lo que suele necesitar la asesoría laboral) y una hoja de detalle por
 * trabajador.
 */
class TeamAttendanceExport implements WithMultipleSheets
{
    /**
     * @param  array<int, array<string, mixed>>  $reports
     * @param  array<string, mixed>  $context  Mes y filtros aplicados
     */
    public function __construct(private array $reports, private array $context = []) {}

    /**
     * @return array<int, object>
     */
    public function sheets(): array
    {
        $sheets = [new TeamAttendanceSummarySheet($this->reports, $this->context)];

        $usedTitles = ['Resumen plantilla'];

        foreach ($this->reports as $report) {
            $sheets[] = new TeamAttendanceEmployeeSheet($report, $usedTitles);
        }

        return $sheets;
    }
}

/**
 * Una fila por trabajador con sus totales del mes.
 */
class TeamAttendanceSummarySheet implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    /**
     * @param  array<int, array<string, mixed>>  $reports
     * @param  array<string, mixed>  $context
     */
    public function __construct(private array $reports, private array $context) {}

    public function title(): string
    {
        return 'Resumen plantilla';
    }

    public function headings(): array
    {
        return [
            'Código',
            'Trabajador',
            'Puesto',
            'Departamento',
            'Días laborables',
            'Horas contrato',
            'Horas trabajadas',
            'Horas nómina',
            'Horas extra',
            'Importe hora extra',
            'Importe horas extra €',
            '% cumplimiento',
        ];
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->reports as $report) {
            $rows[] = [
                $report['empleado']['codigo'] ?? '—',
                $report['empleado']['nombre'],
                $report['empleado']['puesto'] ?? '—',
                $report['empleado']['departamento'] ?? '—',
                $report['contrato']['dias_laborables'],
                $report['contrato']['decimal_esperado_mes'],
                $report['perfil']['decimal_fichado_real'],
                $report['exportacion']['decimal_incluido'],
                $report['horas_extra']['decimal'] ?? 0,
                $report['horas_extra']['tarifa_formato'] ?? 'Sin tarifa',
                $report['horas_extra']['importe'] ?? '—',
                $this->completionPercentage($report),
            ];
        }

        $empty = ['', '', '', '', '', '', '', '', '', '', '', ''];

        $rows[] = $empty;
        $rows[] = [
            'TOTAL',
            count($this->reports).' trabajador(es)',
            '',
            '',
            '',
            round(array_sum(array_column(array_column($this->reports, 'contrato'), 'decimal_esperado_mes')), 2),
            round(array_sum(array_column(array_column($this->reports, 'perfil'), 'decimal_fichado_real')), 2),
            round(array_sum(array_column(array_column($this->reports, 'exportacion'), 'decimal_incluido')), 2),
            round(array_sum(array_column(array_column($this->reports, 'horas_extra'), 'decimal')), 2),
            '',
            round((float) array_sum(array_map(
                fn ($report) => (float) ($report['horas_extra']['importe'] ?? 0),
                $this->reports,
            )), 2),
            '',
        ];

        $rows[] = $empty;
        $rows[] = ['Mes exportado', $this->context['mes_label'] ?? '', '', '', '', '', '', '', '', '', '', ''];

        if (! empty($this->context['filtros'])) {
            $rows[] = ['Filtros aplicados', $this->context['filtros'], '', '', '', '', '', '', '', '', '', ''];
        }

        $rows[] = ['Generado', now()->format('d/m/Y H:i'), '', '', '', '', '', '', '', '', '', ''];
        $rows[] = [
            'Nota',
            'Horas nómina = tope contractual del mes. Horas extra = fichadas de más, valoradas a la tarifa de cada trabajador.',
            '', '', '', '', '', '', '', '', '', '',
        ];

        $rows[] = $empty;
        $rows[] = ['Vacaciones y bajas laborales', '', '', '', '', '', '', '', '', '', '', ''];
        $rows[] = ['Nombre y apellidos', 'Periodo', 'Tipo', '', '', '', '', '', '', '', '', ''];

        $ausencias = $this->context['ausencias'] ?? [];

        if ($ausencias === []) {
            $rows[] = [
                'Ninguna vacación ni baja laboral aprobada en este mes.',
                '', '', '', '', '', '', '', '', '', '', '',
            ];
        } else {
            foreach ($ausencias as $ausencia) {
                $rows[] = [
                    $ausencia['nombre'],
                    $ausencia['periodo'],
                    $ausencia['tipo_label'],
                    '', '', '', '', '', '', '', '', '',
                ];
            }
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $lastDataRow = count($this->reports) + 1;

        return [
            1 => ['font' => ['bold' => true]],
            $lastDataRow + 2 => ['font' => ['bold' => true]],
        ];
    }

    private function completionPercentage(array $report): float
    {
        $expected = $report['contrato']['minutos_esperados_mes'] ?? 0;

        if ($expected <= 0) {
            return 0;
        }

        $worked = $report['perfil']['minutos_fichados_reales'] ?? 0;

        return round(($worked / $expected) * 100, 1);
    }
}

/**
 * Detalle de fichajes de un trabajador.
 */
class TeamAttendanceEmployeeSheet implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    private string $sheetTitle;

    /**
     * @param  array<string, mixed>  $report
     * @param  array<int, string>  $usedTitles  Se pasa por referencia para evitar hojas duplicadas
     */
    public function __construct(private array $report, private array &$usedTitles)
    {
        $this->sheetTitle = $this->uniqueTitle($report['empleado']['nombre']);
        $this->usedTitles[] = $this->sheetTitle;
    }

    public function title(): string
    {
        return $this->sheetTitle;
    }

    public function headings(): array
    {
        return ['Fecha', 'Tipo', 'Hora', 'Zona', 'Notas'];
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->report['exportacion']['fichajes'] as $fichaje) {
            $rows[] = [
                $fichaje['fecha'],
                $fichaje['label'],
                $fichaje['hora'],
                $fichaje['zona'] ?? '—',
                $fichaje['nota'] ?? '',
            ];
        }

        if ($rows === []) {
            $rows[] = ['Sin fichajes en el periodo', '', '', '', ''];
        }

        $extra = $this->report['horas_extra'] ?? [];

        $rows[] = ['', '', '', '', ''];
        $rows[] = ['Resumen contractual', '', '', '', ''];
        $rows[] = ['Trabajador', $this->report['empleado']['nombre'], '', '', ''];
        $rows[] = ['Puesto', $this->report['empleado']['puesto'] ?? '—', '', '', ''];
        $rows[] = ['Departamento', $this->report['empleado']['departamento'] ?? '—', '', '', ''];
        $rows[] = ['Mes', $this->report['periodo']['mes_label'], '', '', ''];
        $rows[] = ['Horas según contrato', $this->report['contrato']['formato_esperado_mes'], '', '', ''];
        $rows[] = ['Horas trabajadas', $this->report['perfil']['formato_fichado_real'], '', '', ''];
        $rows[] = ['Horas nómina', $this->report['exportacion']['formato_incluido'], '', '', ''];
        $rows[] = ['Horas extra', $extra['formato'] ?? '0h', '', '', ''];
        $rows[] = ['Importe hora extra', $extra['tarifa_formato'] ?? 'Sin tarifa', '', '', ''];
        $rows[] = ['Importe horas extra', $extra['importe_formato'] ?? 'Sin tarifa', '', '', ''];

        $ausencias = $this->report['ausencias'] ?? [];
        $rows[] = ['', '', '', '', ''];
        $rows[] = ['Vacaciones y bajas laborales', '', '', '', ''];

        if ($ausencias === []) {
            $rows[] = ['Ninguna vacación ni baja laboral en este mes.', '', '', '', ''];
        } else {
            $rows[] = ['Nombre y apellidos', 'Periodo', 'Tipo', '', ''];
            foreach ($ausencias as $ausencia) {
                $rows[] = [
                    $ausencia['nombre'],
                    $ausencia['periodo'],
                    $ausencia['tipo_label'],
                    '',
                    '',
                ];
            }
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    /**
     * Excel limita los nombres de hoja a 31 caracteres y no admite duplicados.
     */
    private function uniqueTitle(string $name): string
    {
        $base = substr(preg_replace('/[\\\\\/\*\?\:\[\]]/', '', str_replace(' ', '_', $name)), 0, 31);
        $title = $base;
        $suffix = 2;

        while (in_array($title, $this->usedTitles, true)) {
            $marker = '_'.$suffix;
            $title = substr($base, 0, 31 - strlen($marker)).$marker;
            $suffix++;
        }

        return $title;
    }
}
