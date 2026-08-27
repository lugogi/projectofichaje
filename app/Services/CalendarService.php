<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeDaySchedule;
use App\Models\Holiday;
use App\Models\ScheduleException;
use App\Models\TimeRecord;
use App\Models\WorkSession;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CalendarService
{
    public function __construct(
        private AbsenceScheduleService $absences,
        private EmployeeAccessService $access,
        private AttendanceService $attendance,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function monthPayload(Employee $actor, int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth();

        $personal = $this->personalMonth($actor, $start, $end);

        return [
            ...$personal,
            'equipo' => $this->canSeeTeam($actor)
                ? $this->teamMonth($actor, $start, $end)
                : ['habilitado' => false],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dayPayload(Employee $actor, Carbon $date): array
    {
        $personal = $this->personalDay($actor, $date);

        return [
            ...$personal,
            'equipo' => $this->canSeeTeam($actor)
                ? $this->teamDay($actor, $date)
                : ['habilitado' => false],
        ];
    }

    public function canSeeTeam(Employee $actor): bool
    {
        return $actor->isAdmin() || $actor->isManager();
    }

    /**
     * @return array<string, mixed>
     */
    private function personalMonth(Employee $actor, Carbon $start, Carbon $end): array
    {
        $schedules = EmployeeDaySchedule::where('employee_id', $actor->id)
            ->where('active', true)
            ->get();

        $exceptionTypes = $this->exceptionTypes($actor->id, $start, $end);
        $holidays = $this->holidaysForCalendar($actor->work_calendar_id, $start, $end);
        $holidayDates = collect($holidays)->pluck('date')->flip()->all();

        $workDays = [];
        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
            if ($this->isScheduledWorkDay($cursor, $schedules, $exceptionTypes, $holidayDates)) {
                $workDays[] = $cursor->toDateString();
            }
        }

        $clockedDates = TimeRecord::where('employee_id', $actor->id)
            ->active()
            ->whereBetween('recorded_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->get()
            ->map(fn (TimeRecord $t) => $t->recorded_at->toDateString())
            ->unique()
            ->values()
            ->all();

        $today = today()->toDateString();

        $absences = $this->absences
            ->absencesInRange($actor, $start, $end)
            ->map(fn (array $a) => [
                'date' => $a['date'],
                'type' => $a['type'],
                'label' => $a['label'],
            ])
            ->values()
            ->all();

        $missedDays = collect($workDays)
            ->filter(fn (string $date) => $date < $today && ! in_array($date, $clockedDates, true))
            ->values()
            ->all();

        return [
            'work_days' => $workDays,
            'holidays' => $holidays,
            'records' => collect($clockedDates)->map(fn (string $date) => ['date' => $date])->all(),
            'clocked_dates' => $clockedDates,
            'missed_days' => $missedDays,
            'today' => $today,
            'absences' => $absences,
            'team_absences' => $this->absences->teamAbsencesInRange($actor, $start, $end)->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function personalDay(Employee $actor, Carbon $date): array
    {
        $records = TimeRecord::where('employee_id', $actor->id)
            ->active()
            ->whereDate('recorded_at', $date)
            ->with('clockZone')
            ->orderBy('recorded_at')
            ->get()
            ->map(fn (TimeRecord $record) => $this->mapRecord($record))
            ->all();

        return [
            'records' => $records,
            'absence' => $this->absences->absenceOnDate($actor, $date),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function teamMonth(Employee $actor, Carbon $start, Carbon $end): array
    {
        $employees = $this->teamMembers($actor);

        if ($employees->isEmpty()) {
            return [
                'habilitado' => true,
                'plantilla' => 0,
                'dias' => [],
            ];
        }

        $ids = $employees->pluck('id');
        $schedulesByEmployee = EmployeeDaySchedule::whereIn('employee_id', $ids)
            ->where('active', true)
            ->get()
            ->groupBy('employee_id');

        $exceptionsByEmployee = ScheduleException::whereIn('employee_id', $ids)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->groupBy('employee_id');

        $calendarIds = $employees->pluck('work_calendar_id')->filter()->unique();
        $holidaysByCalendar = Holiday::whereIn('work_calendar_id', $calendarIds)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->groupBy('work_calendar_id');

        $clockedPairs = TimeRecord::whereIn('employee_id', $ids)
            ->active()
            ->whereBetween('recorded_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->get(['employee_id', 'recorded_at'])
            ->mapWithKeys(fn (TimeRecord $r) => [
                $r->employee_id.'|'.$r->recorded_at->toDateString() => true,
            ]);

        $openByDate = WorkSession::whereIn('employee_id', $ids)
            ->where('status', WorkSession::STATUS_OPEN)
            ->whereBetween('clocked_in_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->get()
            ->groupBy(fn (WorkSession $s) => $s->clocked_in_at->toDateString());

        $absencesByDate = $this->absences
            ->teamAbsencesInRange($actor, $start, $end)
            ->groupBy('date');

        $today = today()->toDateString();
        $dias = [];

        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
            $dateStr = $cursor->toDateString();
            $laborables = 0;
            $fichados = 0;
            $ausentes = 0;
            $enCurso = 0;
            $absencesToday = collect($absencesByDate->get($dateStr, []))->keyBy('employee_id');
            $openToday = collect($openByDate->get($dateStr, []))->pluck('employee_id')->flip();

            foreach ($employees as $employee) {
                if ($this->notHiredYet($employee, $cursor)) {
                    continue;
                }

                if ($absencesToday->has($employee->id)) {
                    $ausentes++;
                    continue;
                }

                $schedules = $schedulesByEmployee->get($employee->id, collect());
                $exceptionTypes = $this->exceptionMap($exceptionsByEmployee->get($employee->id, collect()));
                $holidayDates = $this->holidayDateMap($holidaysByCalendar->get($employee->work_calendar_id, collect()));

                if (! $this->isScheduledWorkDay($cursor, $schedules, $exceptionTypes, $holidayDates)) {
                    if ($clockedPairs->has($employee->id.'|'.$dateStr)) {
                        $fichados++;
                    }
                    continue;
                }

                $laborables++;

                if ($clockedPairs->has($employee->id.'|'.$dateStr)) {
                    $fichados++;
                }

                if ($openToday->has($employee->id)) {
                    $enCurso++;
                }
            }

            $sinFichar = $dateStr <= $today
                ? max(0, $laborables - $fichados)
                : 0;

            $dias[$dateStr] = [
                'laborables' => $laborables,
                'fichados' => $fichados,
                'ausentes' => $ausentes,
                'sin_fichar' => $sinFichar,
                'en_curso' => $enCurso,
            ];
        }

        return [
            'habilitado' => true,
            'plantilla' => $employees->count(),
            'dias' => $dias,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function teamDay(Employee $actor, Carbon $date): array
    {
        $employees = $this->teamMembers($actor);
        $dateStr = $date->toDateString();
        $today = today()->toDateString();

        if ($employees->isEmpty()) {
            return [
                'habilitado' => true,
                'fecha' => $dateStr,
                'fecha_label' => $date->locale('es')->isoFormat('dddd D [de] MMMM'),
                'resumen' => $this->emptyDaySummary(),
                'personas' => [],
            ];
        }

        $ids = $employees->pluck('id');

        $schedulesByEmployee = EmployeeDaySchedule::whereIn('employee_id', $ids)
            ->where('active', true)
            ->get()
            ->groupBy('employee_id');

        $exceptionsByEmployee = ScheduleException::whereIn('employee_id', $ids)
            ->whereDate('date', $dateStr)
            ->get()
            ->groupBy('employee_id');

        $calendarIds = $employees->pluck('work_calendar_id')->filter()->unique();
        $holidaysByCalendar = Holiday::whereIn('work_calendar_id', $calendarIds)
            ->whereDate('date', $dateStr)
            ->get()
            ->groupBy('work_calendar_id');

        $sessionsByEmployee = WorkSession::whereIn('employee_id', $ids)
            ->whereDate('clocked_in_at', $dateStr)
            ->with(['clockInRecord.clockZone', 'clockOutRecord'])
            ->orderBy('clocked_in_at')
            ->get()
            ->groupBy('employee_id');

        $recordsByEmployee = TimeRecord::whereIn('employee_id', $ids)
            ->active()
            ->whereDate('recorded_at', $dateStr)
            ->with('clockZone')
            ->orderBy('recorded_at')
            ->get()
            ->groupBy('employee_id');

        $absences = $this->absences
            ->teamAbsencesInRange($actor, $date, $date)
            ->keyBy('employee_id');

        $personas = $employees->map(function (Employee $employee) use (
            $date,
            $dateStr,
            $today,
            $schedulesByEmployee,
            $exceptionsByEmployee,
            $holidaysByCalendar,
            $sessionsByEmployee,
            $recordsByEmployee,
            $absences,
        ) {
            $absence = $absences->get($employee->id);
            $jornadas = $this->mapJornadas(
                $sessionsByEmployee->get($employee->id, collect()),
            );
            $records = $recordsByEmployee->get($employee->id, collect())
                ->map(fn (TimeRecord $r) => $this->mapRecord($r))
                ->values()
                ->all();

            $minutos = collect($jornadas)->sum('duracion_minutos');
            $laborable = ! $this->notHiredYet($employee, $date)
                && $this->isScheduledWorkDay(
                    $date,
                    $schedulesByEmployee->get($employee->id, collect()),
                    $this->exceptionMap($exceptionsByEmployee->get($employee->id, collect())),
                    $this->holidayDateMap($holidaysByCalendar->get($employee->work_calendar_id, collect())),
                );

            $estado = $this->personStatus(
                $absence !== null,
                $laborable,
                $jornadas,
                $records,
                $dateStr,
                $today,
            );

            return [
                'id' => $employee->id,
                'nombre' => $employee->name,
                'iniciales' => $this->initials($employee->name),
                'codigo' => $employee->employee_code,
                'puesto' => $employee->position,
                'departamento' => $employee->department,
                'estado' => $estado,
                'ausencia' => $absence ? [
                    'type' => $absence['type'],
                    'label' => $absence['label'],
                ] : null,
                'jornadas' => $jornadas,
                'registros' => $records,
                'minutos' => $minutos,
                'horas' => $this->attendance->formatDuration($minutos),
            ];
        })
            ->sortBy(fn (array $p) => $this->statusOrder($p['estado']).$p['nombre'])
            ->values()
            ->all();

        $counts = collect($personas)->countBy('estado');

        return [
            'habilitado' => true,
            'fecha' => $dateStr,
            'fecha_label' => $date->copy()->locale('es')->isoFormat('dddd D [de] MMMM'),
            'resumen' => [
                'total' => count($personas),
                'completos' => (int) $counts->get('completo', 0),
                'en_curso' => (int) $counts->get('en_curso', 0),
                'sin_fichar' => (int) $counts->get('sin_fichar', 0),
                'ausentes' => (int) $counts->get('ausencia', 0),
                'laborables' => collect($personas)->whereIn('estado', [
                    'completo', 'en_curso', 'sin_fichar', 'laborable',
                ])->count(),
            ],
            'personas' => $personas,
        ];
    }

    /**
     * @return Collection<int, Employee>
     */
    private function teamMembers(Employee $actor): Collection
    {
        $ids = $this->access->exportableEmployees($actor)->pluck('id');

        if ($ids->isEmpty()) {
            return collect();
        }

        return Employee::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  Collection<int, WorkSession>  $sessions
     * @return list<array<string, mixed>>
     */
    private function mapJornadas(Collection $sessions): array
    {
        return $sessions->map(function (WorkSession $session) {
            $abierta = $session->status === WorkSession::STATUS_OPEN;
            $end = $abierta ? now() : $session->clocked_out_at;
            $minutes = $end ? (int) $session->clocked_in_at->diffInMinutes($end) : 0;

            return [
                'id' => $session->id,
                'entrada' => $session->clocked_in_at->format('H:i'),
                'salida' => $abierta ? null : $session->clocked_out_at?->format('H:i'),
                'abierta' => $abierta,
                'duracion_minutos' => $minutes,
                'duracion' => $this->attendance->formatDuration($minutes),
                'zona' => $session->clockInRecord?->clockZone?->name,
                'es_correccion' => $session->clockInRecord?->origin === 'correction'
                    || $session->clockOutRecord?->origin === 'correction',
            ];
        })->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function mapRecord(TimeRecord $record): array
    {
        return [
            'id' => $record->id,
            'type' => (int) $record->type,
            'work_date' => $record->recorded_at->format('Y-m-d'),
            'recorded_at' => $record->recorded_at->format('H:i'),
            'clock_method' => $record->clock_method,
            'origin' => $record->origin,
            'es_correccion' => $record->origin === 'correction',
            'zona' => $record->clockZone?->name,
            'device_id' => $record->device_id,
            'ip' => $record->ip,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $jornadas
     * @param  list<array<string, mixed>>  $records
     */
    private function personStatus(
        bool $hasAbsence,
        bool $laborable,
        array $jornadas,
        array $records,
        string $dateStr,
        string $today,
    ): string {
        if ($hasAbsence) {
            return 'ausencia';
        }

        $hasOpen = collect($jornadas)->contains('abierta', true);
        $hasClosed = collect($jornadas)->contains('abierta', false);
        $hasAny = $jornadas !== [] || $records !== [];

        if ($hasOpen) {
            return 'en_curso';
        }

        if ($hasClosed || $hasAny) {
            return 'completo';
        }

        if (! $laborable) {
            return 'no_laborable';
        }

        if ($dateStr > $today) {
            return 'laborable';
        }

        return 'sin_fichar';
    }

    private function statusOrder(string $estado): string
    {
        return match ($estado) {
            'sin_fichar' => '1',
            'en_curso' => '2',
            'ausencia' => '3',
            'completo' => '4',
            'laborable' => '5',
            default => '6',
        };
    }

    /**
     * @return array<string, int>
     */
    private function emptyDaySummary(): array
    {
        return [
            'total' => 0,
            'completos' => 0,
            'en_curso' => 0,
            'sin_fichar' => 0,
            'ausentes' => 0,
            'laborables' => 0,
        ];
    }

    /**
     * @return list<array{date: string, name: string}>
     */
    private function holidaysForCalendar(?string $calendarId, Carbon $start, Carbon $end): array
    {
        if (! $calendarId) {
            return [];
        }

        return Holiday::where('work_calendar_id', $calendarId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->map(fn (Holiday $h) => [
                'date' => $h->date->toDateString(),
                'name' => $h->name,
            ])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function exceptionTypes(string $employeeId, Carbon $start, Carbon $end): array
    {
        return ScheduleException::where('employee_id', $employeeId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->reduce(function (array $carry, ScheduleException $exception) {
                $carry[$exception->date->toDateString()] = $exception->type;

                return $carry;
            }, []);
    }

    /**
     * @param  Collection<int, ScheduleException>  $exceptions
     * @return array<string, string>
     */
    private function exceptionMap(Collection $exceptions): array
    {
        return $exceptions->reduce(function (array $carry, ScheduleException $exception) {
            $carry[$exception->date->toDateString()] = $exception->type;

            return $carry;
        }, []);
    }

    /**
     * @param  Collection<int, Holiday>  $holidays
     * @return array<string, true>
     */
    private function holidayDateMap(Collection $holidays): array
    {
        return $holidays
            ->mapWithKeys(fn (Holiday $h) => [$h->date->toDateString() => true])
            ->all();
    }

    /**
     * @param  Collection<int, EmployeeDaySchedule>  $schedules
     * @param  array<string, string>  $exceptionTypes
     * @param  array<string, mixed>  $holidayDates
     */
    public function isScheduledWorkDay(
        Carbon|\DateTime $date,
        Collection $schedules,
        array $exceptionTypes,
        array $holidayDates = [],
    ): bool {
        $dateStr = $date instanceof Carbon ? $date->toDateString() : $date->format('Y-m-d');
        $weekday = $date instanceof Carbon ? (int) $date->format('w') : (int) $date->format('w');

        if (isset($holidayDates[$dateStr])) {
            return false;
        }

        $exceptionType = $exceptionTypes[$dateStr] ?? null;

        if (AbsenceScheduleService::isNonWorkAbsenceType($exceptionType)) {
            return false;
        }

        if ($schedules->isEmpty()) {
            return $weekday >= 1 && $weekday <= 5;
        }

        $inRange = $schedules->contains(function ($schedule) use ($dateStr, $weekday) {
            if (! $schedule->start_date) {
                return false;
            }

            $starts = $dateStr >= $schedule->start_date->format('Y-m-d');
            $ends = $schedule->end_date === null
                || $dateStr <= $schedule->end_date->format('Y-m-d');

            return (int) $schedule->weekday === $weekday && $schedule->active && $starts && $ends;
        });

        if ($inRange) {
            return true;
        }

        $earliestStart = $schedules
            ->filter(fn ($s) => $s->start_date)
            ->min(fn ($s) => $s->start_date->format('Y-m-d'));

        if ($earliestStart && $dateStr >= $earliestStart) {
            return $schedules->contains(
                fn ($schedule) => (int) $schedule->weekday === $weekday && $schedule->active,
            );
        }

        return false;
    }

    private function notHiredYet(Employee $employee, Carbon $date): bool
    {
        return $employee->hire_date !== null && $date->lt($employee->hire_date);
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $first = mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1));
        $last = count($parts) > 1
            ? mb_strtoupper(mb_substr($parts[array_key_last($parts)], 0, 1))
            : '';

        return $first.$last ?: '?';
    }
}
