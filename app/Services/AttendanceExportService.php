<?php

namespace App\Services;

use App\Models\AbsenceRequest;
use App\Models\Employee;
use App\Models\TimeRecord;
use App\Models\WorkSession;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AttendanceExportService
{
    public function __construct(
        private AttendanceService $attendance,
        private ExpectedWorkHoursService $expectedHours,
    ) {}

    /**
     * Datos del mes para perfil (horas reales + extra) y exportación (tope contractual).
     *
     * @return array<string, mixed>
     */
    public function monthlyReport(Employee $employee, ?Carbon $month = null, bool $hideOvertime = false): array
    {
        $month ??= now();
        $objetivo = $this->expectedHours->monthlySummary($employee, $month, $hideOvertime);

        $start = $month->copy()->startOfMonth()->startOfDay();
        $end = $month->copy()->endOfMonth()->endOfDay();

        $sessions = WorkSession::where('employee_id', $employee->id)
            ->whereBetween('clocked_in_at', [$start, $end])
            ->with(['clockInRecord.clockZone', 'clockOutRecord'])
            ->orderBy('clocked_in_at')
            ->get();

        $realMinutes = $this->attendance->minutesForRange($employee, $start, $end);
        $capMinutes = $objetivo['minutos_esperados'];
        $capped = $this->capSessions($sessions, $capMinutes);

        $exportMinutes = (int) collect($capped['jornadas'])->sum('duracion_minutos');
        $extraMinutes = max(0, $realMinutes - $capMinutes);

        $fichajes = $this->buildFichajesFromJornadas($capped['jornadas']);

        return [
            'periodo' => [
                'mes' => $month->format('Y-m'),
                'mes_label' => $month->locale('es')->isoFormat('MMMM YYYY'),
                'desde' => $start->toDateString(),
                'hasta' => $end->toDateString(),
            ],
            'empleado' => [
                'nombre' => $employee->name,
                'email' => $employee->email,
                'codigo' => $employee->employee_code,
                'puesto' => $employee->position,
                'departamento' => $employee->department,
            ],
            'contrato' => [
                'horas_semanales' => $objetivo['horas_semanales_contrato'],
                'minutos_esperados_mes' => $capMinutes,
                'formato_esperado_mes' => $objetivo['formato_esperado'],
                'decimal_esperado_mes' => $objetivo['decimal_esperado'],
                'dias_laborables' => $objetivo['dias_laborables'],
            ],
            'perfil' => $this->profileSection($realMinutes, $extraMinutes, $hideOvertime),
            'horas_extra' => $this->overtimeSection($employee, $extraMinutes),
            'ausencias' => $this->absencesSection($employee, $month),
            'exportacion' => [
                'minutos_incluidos' => $exportMinutes,
                'formato_incluido' => $this->attendance->formatDuration($exportMinutes),
                'decimal_incluido' => round($exportMinutes / 60, 2),
                'minutos_omitidos' => max(0, $realMinutes - $exportMinutes),
                'formato_omitido' => $this->attendance->formatDuration(max(0, $realMinutes - $exportMinutes)),
                'tope_aplicado' => $realMinutes > $capMinutes,
                'jornadas' => $capped['jornadas'],
                'fichajes' => $fichajes,
                'nota_legal' => 'Exportación limitada al máximo contractual del mes. Las horas adicionales fichadas no se incluyen en este documento.',
            ],
        ];
    }

    /**
     * @param  Collection<int, WorkSession>  $sessions
     * @return array{jornadas: list<array<string, mixed>>}
     */
    private function capSessions(Collection $sessions, int $capMinutes): array
    {
        $jornadas = [];
        $acumulado = 0;

        foreach ($sessions as $session) {
            if ($acumulado >= $capMinutes) {
                break;
            }

            $end = $session->status === WorkSession::STATUS_OPEN
                ? now()
                : $session->clocked_out_at;

            if (! $end || ! $session->clocked_in_at) {
                continue;
            }

            $sessionMinutes = (int) $session->clocked_in_at->diffInMinutes($end);
            $restante = $capMinutes - $acumulado;

            if ($sessionMinutes <= $restante) {
                $jornadas[] = $this->mapJornada($session, $sessionMinutes, $end, false);
                $acumulado += $sessionMinutes;
                continue;
            }

            if ($restante > 0) {
                $adjustedEnd = $session->clocked_in_at->copy()->addMinutes($restante);
                $jornadas[] = $this->mapJornada($session, $restante, $adjustedEnd, true);
                $acumulado = $capMinutes;
            }

            break;
        }

        return ['jornadas' => $jornadas];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapJornada(
        WorkSession $session,
        int $minutes,
        Carbon $exportedEnd,
        bool $parcial,
    ): array {
        return [
            'id' => $session->id,
            'fecha' => $session->clocked_in_at->format('Y-m-d'),
            'fecha_label' => $session->clocked_in_at->locale('es')->isoFormat('ddd D MMM YYYY'),
            'entrada' => $session->clocked_in_at->format('H:i'),
            'salida' => $exportedEnd->format('H:i'),
            'duracion_minutos' => $minutes,
            'duracion' => $this->attendance->formatDuration($minutes),
            'parcial' => $parcial,
            'zona' => $session->clockInRecord?->clockZone?->name,
            'entrada_registro_id' => $session->clock_in_record_id,
            'salida_registro_id' => $session->clock_out_record_id,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $jornadas
     * @return list<array<string, mixed>>
     */
    private function buildFichajesFromJornadas(array $jornadas): array
    {
        $fichajes = [];

        foreach ($jornadas as $jornada) {
            if ($jornada['entrada_registro_id']) {
                $entrada = TimeRecord::with('clockZone')->find($jornada['entrada_registro_id']);
                if ($entrada) {
                    $fichajes[] = $this->mapFichaje($entrada);
                }
            }

            if ($jornada['salida_registro_id'] && ! $jornada['parcial']) {
                $salida = TimeRecord::with('clockZone')->find($jornada['salida_registro_id']);
                if ($salida) {
                    $fichajes[] = $this->mapFichaje($salida);
                }
            } elseif ($jornada['parcial']) {
                $fichajes[] = [
                    'tipo' => TimeRecord::TYPE_CLOCK_OUT,
                    'label' => 'Salida (tope contractual)',
                    'fecha' => $jornada['fecha'],
                    'hora' => $jornada['salida'],
                    'zona' => $jornada['zona'],
                    'nota' => 'Hora ajustada al límite mensual del contrato',
                ];
            }
        }

        return $fichajes;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapFichaje(TimeRecord $record): array
    {
        return [
            'id' => $record->id,
            'tipo' => $record->type,
            'label' => $record->label,
            'fecha' => $record->recorded_at->format('Y-m-d'),
            'hora' => $record->recorded_at->format('H:i'),
            'zona' => $record->clockZone?->name,
            'nota' => null,
        ];
    }

    /**
     * Horas extra y su valoración económica.
     *
     * Ejemplo: si el contrato son 176 h y ha fichado 180, se pagan 176 como
     * horas de nómina y las 4 restantes se abonan a la tarifa del trabajador.
     *
     * @return array<string, mixed>
     */
    private function overtimeSection(Employee $employee, int $extraMinutes): array
    {
        $hours = round($extraMinutes / 60, 2);
        $rate = $employee->overtime_rate !== null ? (float) $employee->overtime_rate : null;
        $amount = $rate !== null ? round($hours * $rate, 2) : null;

        return [
            'minutos' => $extraMinutes,
            'formato' => $this->attendance->formatDuration($extraMinutes),
            'decimal' => $hours,
            'tarifa' => $rate,
            'tarifa_formato' => $rate !== null ? $this->money($rate) . '/h' : 'Sin tarifa',
            'importe' => $amount,
            'importe_formato' => $amount !== null ? $this->money($amount) : 'Sin tarifa',
            'sin_tarifa' => $rate === null,
        ];
    }

    /**
     * Vacaciones y bajas laborales aprobadas que solapan el mes exportado.
     *
     * @return list<array<string, mixed>>
     */
    private function absencesSection(Employee $employee, Carbon $month): array
    {
        $start = $month->copy()->startOfMonth()->toDateString();
        $end = $month->copy()->endOfMonth()->toDateString();

        return AbsenceRequest::query()
            ->where('employee_id', $employee->id)
            ->where('status', AbsenceRequest::STATUS_APPROVED)
            ->whereIn('type', [
                AbsenceRequest::TYPE_VACATION,
                AbsenceRequest::TYPE_MEDICAL_LEAVE,
            ])
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start)
            ->orderBy('start_date')
            ->get()
            ->map(fn (AbsenceRequest $absence) => $this->mapAbsence($employee, $absence))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function mapAbsence(Employee $employee, AbsenceRequest $absence): array
    {
        $dias = $absence->start_date->diffInDays($absence->end_date) + 1;

        return [
            'nombre' => $employee->name,
            'codigo' => $employee->employee_code,
            'tipo' => $absence->type,
            'tipo_label' => $absence->type === AbsenceRequest::TYPE_MEDICAL_LEAVE
                ? 'Baja laboral'
                : 'Vacaciones',
            'desde' => $absence->start_date->toDateString(),
            'hasta' => $absence->end_date->toDateString(),
            'periodo' => $this->formatPeriod($absence->start_date, $absence->end_date),
            'dias' => (int) $dias,
        ];
    }

    private function formatPeriod(Carbon $from, Carbon $to): string
    {
        if ($from->isSameDay($to)) {
            return $from->format('d/m/Y');
        }

        return $from->format('d/m/Y').' – '.$to->format('d/m/Y');
    }

    private function money(float $value): string
    {
        return number_format($value, 2, ',', '.') . ' €';
    }

    /**
     * @return array<string, mixed>
     */
    private function profileSection(int $realMinutes, int $extraMinutes, bool $hideOvertime): array
    {
        return [
            'minutos_fichados_reales' => $realMinutes,
            'formato_fichado_real' => $this->attendance->formatDuration($realMinutes),
            'decimal_fichado_real' => round($realMinutes / 60, 2),
            'minutos_extra' => $hideOvertime ? 0 : $extraMinutes,
            'formato_extra' => $hideOvertime ? '—' : $this->attendance->formatDuration($extraMinutes),
            'decimal_extra' => $hideOvertime ? 0 : round($extraMinutes / 60, 2),
            'tiene_horas_extra' => ! $hideOvertime && $extraMinutes > 0,
            'ocultar_extra' => $hideOvertime,
        ];
    }
}
