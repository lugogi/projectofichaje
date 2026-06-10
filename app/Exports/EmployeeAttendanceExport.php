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
        $rows[] = ['Horas exportadas', $this->report['exportacion']['formato_incluido'], '', '', ''];
        $rows[] = ['Horas omitidas (extra)', $this->report['exportacion']['formato_omitido'], '', '', ''];

        return $rows;
    }
}
