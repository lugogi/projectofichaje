<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeDaySchedule;
use App\Models\Holiday;
use App\Models\ScheduleException;
use App\Services\AbsenceScheduleService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Calcula las horas que el empleado debería trabajar en un mes según:
 * - Contrato semanal (p. ej. 40 h)
 * - Horario asignado (employee_day_schedule)
 * - Festivos del calendario laboral (holidays)
 * - Excepciones personales (schedule_exceptions)
 */
class ExpectedWorkHoursService
{
    public function __construct(private AttendanceService $attendance) {}

    /**
     * Resumen del objetivo mensual frente a lo fichado.
     *
     * @return array<string, mixed>
     */
    public function monthlySummary(Employee $employee, ?Carbon $month = null, bool $hideOvertime = false): array
    {
        $month ??= now();
        $start = $month->copy()->startOfMonth()->startOfDay();
        $end = $month->copy()->endOfMonth()->endOfDay();

        $weeklyHours = (int) config('fichaje.horas_semanales_contrato', 40);
        $standardDailyMinutes = (int) round(($weeklyHours / 5) * 60);

        $schedules = EmployeeDaySchedule::where('employee_id', $employee->id)
            ->where('active', true)
            ->get();

        $exceptionTypes = ScheduleException::where('employee_id', $employee->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->mapWithKeys(fn (ScheduleException $e) => [
                $e->date->toDateString() => $e->type,
            ]);

        $calendarHolidayDates = collect();
        $calendarHolidays = collect();

        if ($employee->work_calendar_id) {
            $calendarHolidays = Holiday::where('work_calendar_id', $employee->work_calendar_id)
                ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                ->orderBy('date')
                ->get();

            $calendarHolidayDates = $calendarHolidays->map(
                fn (Holiday $h) => $h->date->toDateString()
            );
        }

        $expectedMinutes = 0;
        $workDays = 0;
        $holidayDays = 0;
        $festivos = [];

        $useDefaultWeekdays = $schedules->isEmpty();

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dateStr = $date->toDateString();
            $schedule = $useDefaultWeekdays
                ? null
                : $this->findScheduleForDate($schedules, $date);

            if ($useDefaultWeekdays) {
                if (! $this->isDefaultWorkday($date)) {
                    continue;
                }
            } elseif (! $schedule) {
                continue;
            }

            if ($calendarHolidayDates->contains($dateStr)) {
                $holidayDays++;
                $holiday = $calendarHolidays->first(
                    fn (Holiday $h) => $h->date->toDateString() === $dateStr
                );
                $festivos[] = [
                    'fecha' => $dateStr,
                    'fecha_label' => $date->locale('es')->isoFormat('D MMM'),
                    'nombre' => $holiday?->name ?? 'Festivo',
                ];
                continue;
            }

            $exceptionType = $exceptionTypes[$dateStr] ?? null;

            if (AbsenceScheduleService::isNonWorkAbsenceType($exceptionType)) {
                $holidayDays++;
                $festivos[] = [
                    'fecha' => $dateStr,
                    'fecha_label' => $date->locale('es')->isoFormat('D MMM'),
                    'nombre' => app(AbsenceScheduleService::class)->typeLabel($exceptionType),
                ];
                continue;
            }

            $workDays++;
            $expectedMinutes += $schedule
                ? $this->expectedMinutesForDay($schedule, $standardDailyMinutes)
                : $standardDailyMinutes;
        }

        $workedMinutes = $this->attendance->minutesForRange($employee, $start, $end);
        $remainingMinutes = max(0, $expectedMinutes - $workedMinutes);
        $percentage = $expectedMinutes > 0
            ? min(100, round(($workedMinutes / $expectedMinutes) * 100, 1))
            : 0;

        $employee->loadMissing('workCalendar');

        if ($hideOvertime) {
            $percentage = min(100, $percentage);
        }

        return [
            'mes' => $month->format('Y-m'),
            'mes_label' => $month->locale('es')->isoFormat('MMMM YYYY'),
            'ocultar_extra' => $hideOvertime,
            'horas_semanales_contrato' => $weeklyHours,
            'horas_diarias_contrato' => round($weeklyHours / 5, 1),
            'minutos_esperados' => $expectedMinutes,
            'formato_esperado' => $this->attendance->formatDuration($expectedMinutes),
            'decimal_esperado' => round($expectedMinutes / 60, 2),
            'dias_laborables' => $workDays,
            'dias_festivos' => $holidayDays,
            'festivos' => $festivos,
            'calendario' => $employee->workCalendar?->name,
            'sin_horario' => false,
            'usa_horario_por_defecto' => $useDefaultWeekdays,
            'trabajado' => [
                'minutos' => $workedMinutes,
                'formato' => $this->attendance->formatDuration($workedMinutes),
                'decimal' => round($workedMinutes / 60, 2),
            ],
            'restante' => [
                'minutos' => $remainingMinutes,
                'formato' => $this->attendance->formatDuration($remainingMinutes),
                'decimal' => round($remainingMinutes / 60, 2),
            ],
            'diferencia_minutos' => $hideOvertime ? 0 : $workedMinutes - $expectedMinutes,
            'porcentaje_cumplido' => $percentage,
            'adelantado' => $hideOvertime ? false : $workedMinutes > $expectedMinutes,
        ];
    }

    /**
     * @param  Collection<int, EmployeeDaySchedule>  $schedules
     */
    private function findScheduleForDate(Collection $schedules, Carbon $date): ?EmployeeDaySchedule
    {
        $weekday = (int) $date->dayOfWeek;
        $dateStr = $date->toDateString();

        return $schedules->first(function (EmployeeDaySchedule $schedule) use ($weekday, $dateStr) {
            if (! $schedule->start_date || ! $schedule->end_date) {
                return false;
            }

            return (int) $schedule->weekday === $weekday
                && $dateStr >= $schedule->start_date->toDateString()
                && $dateStr <= $schedule->end_date->toDateString();
        });
    }

    private function expectedMinutesForDay(
        EmployeeDaySchedule $schedule,
        int $standardDailyMinutes,
    ): int {
        $scheduleMinutes = $this->scheduleDurationMinutes($schedule);

        if ($scheduleMinutes <= 0) {
            return $standardDailyMinutes;
        }

        // Jornada parcial: se respeta el horario configurado.
        if ($scheduleMinutes < $standardDailyMinutes) {
            return $scheduleMinutes;
        }

        // Contrato 40 h/semana: 8 h por día laborable (aunque el horario marque 8–17 con pausa).
        return $standardDailyMinutes;
    }

    /** Lunes a viernes cuando no hay horario personal configurado. */
    private function isDefaultWorkday(Carbon $date): bool
    {
        return $date->isWeekday();
    }

    private function scheduleDurationMinutes(EmployeeDaySchedule $schedule): int
    {
        if (! $schedule->start_time || ! $schedule->end_time) {
            return 0;
        }

        $start = Carbon::parse($schedule->start_time);
        $end = Carbon::parse($schedule->end_time);

        if ($end->lte($start)) {
            return 0;
        }

        return (int) $start->diffInMinutes($end);
    }
}
