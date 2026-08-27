<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class EmployeeAttendanceExport implements FromArray, WithHeadings, WithTitle
{
    /**
     * @param  array<string, mixed>  $report
     */
    public function __construct(private array $report) {}

    public function title(): string
    {
        return 'Registro horario';
    }

    public function headings(): array
    {
        return ['Fecha', 'Tipo', 'Hora', 'Zona', 'Notas'];
    }

    public function array(): array
    {
        $rows = [];
        $extra = $this->report['horas_extra'] ?? [];

        foreach ($this->report['exportacion']['fichajes'] as $fichaje) {
            $rows[] = [
                $fichaje['fecha'],
                $fichaje['label'],
                $fichaje['hora'],
                $fichaje['zona'] ?? '—',
                $fichaje['nota'] ?? '',
            ];
        }

        $rows[] = ['', '', '', '', ''];
        $rows[] = ['Resumen contractual', '', '', '', ''];
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
}
