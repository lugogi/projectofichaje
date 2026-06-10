<?php

namespace App\Services;

use App\Models\ClockZone;
use App\Models\Employee;
use App\Models\TimeRecord;
use App\Models\WorkSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    public function __construct(
        private WorkSessionSyncService $sessions,
        private TimeRecordChainService $chain,
    ) {}

    public function lastActiveRecord(Employee $employee, ?Carbon $date = null): ?TimeRecord
    {
        $query = TimeRecord::query()
            ->where('employee_id', $employee->id)
            ->active()
            ->orderByDesc('recorded_at')
            ->orderByDesc('created_at');

        if ($date) {
            $query->whereDate('recorded_at', $date);
        }

        return $query->first();
    }

    public function openSession(Employee $employee): ?WorkSession
    {
        return WorkSession::query()
            ->where('employee_id', $employee->id)
            ->where('status', WorkSession::STATUS_OPEN)
            ->whereDate('clocked_in_at', today())
            ->latest('clocked_in_at')
            ->first();
    }

    public function isWorkingToday(Employee $employee): bool
    {
        $last = $this->lastActiveRecord($employee, today());

        return $last !== null && $last->type === TimeRecord::TYPE_CLOCK_IN;
    }

    public function nextAction(Employee $employee): int
    {
        return $this->isWorkingToday($employee)
            ? TimeRecord::TYPE_CLOCK_OUT
            : TimeRecord::TYPE_CLOCK_IN;
    }

    public function clock(Employee $employee, array $context = []): TimeRecord
    {
        $absenceToday = app(AbsenceScheduleService::class)->absenceOnDate($employee, now());

        if ($absenceToday) {
            throw ValidationException::withMessages([
                'fichaje' => "Hoy tienes {$absenceToday['label']} aprobada. No debes fichar durante este periodo.",
            ]);
        }

        $ip = $context['ip'] ?? null;
        $zone = $this->resolveAuthorizedZone($employee, $ip);

        if (config('fichaje.restriccion_ip') && ! $zone) {
            throw ValidationException::withMessages([
                'fichaje' => 'No puedes fichar desde esta red. Debes estar conectado al WiFi de una de las salas autorizadas.',
            ]);
        }

        return DB::transaction(function () use ($employee, $context, $ip, $zone) {
            $type = $this->nextAction($employee);
            $last = $this->lastActiveRecord($employee, today());

            if ($last && $last->type === $type) {
                $mensaje = $type === TimeRecord::TYPE_CLOCK_IN
                    ? 'Ya tienes una entrada abierta hoy. Debes fichar salida antes de volver a entrar.'
                    : 'Tu último fichaje de hoy ya es una salida. Debes fichar entrada primero.';

                throw ValidationException::withMessages([
                    'fichaje' => $mensaje,
                ]);
            }

            $now = now();

            $record = $this->chain->append([
                'employee_id' => $employee->id,
                'type' => $type,
                'recorded_at' => $now,
                'clock_method' => 'manual',
                'validation_method' => $zone ? 'ip' : 'none',
                'clock_zone_id' => $zone?->id,
                'origin' => 'web',
                'ip' => $ip,
                'user_agent' => $context['user_agent'] ?? null,
                'is_suspicious' => false,
                'corrected' => false,
            ]);

            $this->sessions->syncForDate($employee->id, $now->copy()->startOfDay());

            return $record;
        });
    }

    public function resolveAuthorizedZone(Employee $employee, ?string $ip): ?ClockZone
    {
        if (! $ip || ! $employee->company_id) {
            return null;
        }

        $zones = ClockZone::where('active', 1)
            ->where('company_id', $employee->company_id)
            ->get();

        foreach ($zones as $zone) {
            if ($zone->ip && $this->ipMatches($ip, $zone->ip)) {
                return $zone;
            }
        }

        return null;
    }

    private function ipMatches(string $ip, string $pattern): bool
    {
        if (! str_contains($pattern, '/')) {
            return $ip === $pattern;
        }

        [$subnet, $bits] = explode('/', $pattern, 2);
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);

        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        $mask = -1 << (32 - (int) $bits);

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }

    public function employeeAttendanceData(Employee $employee): array
    {
        $this->sessions->syncForDate($employee->id, today());

        $openSession = $this->openSession($employee);
        $lastRecord = $this->lastActiveRecord($employee, today());
        $isWorking = $this->isWorkingToday($employee);
        $nextAction = $this->nextAction($employee);
        $ausenciaHoy = app(AbsenceScheduleService::class)->absenceOnDate($employee, today());

        $hoyMin = $this->minutesForRange($employee, today(), today(), $openSession);
        $semanaMin = $this->minutesForRange(
            $employee,
            now()->startOfWeek(Carbon::MONDAY),
            now()->endOfWeek(Carbon::SUNDAY),
            $openSession
        );
        $mesMin = $this->minutesForRange(
            $employee,
            now()->startOfMonth(),
            now()->endOfMonth(),
            $openSession
        );

        $openSince = $isWorking && $lastRecord
            ? $lastRecord->recorded_at
            : $openSession?->clocked_in_at;

        return [
            'estado' => $isWorking ? 'trabajando' : 'fuera',
            'ausencia_hoy' => $ausenciaHoy,
            'puede_fichar' => $ausenciaHoy === null,
            'proxima_accion' => $nextAction,
            'es_entrada' => $nextAction === TimeRecord::TYPE_CLOCK_IN,
            'sesion_abierta_desde' => $openSince?->format('H:i'),
            'sesion_abierta_minutos' => $openSince
                ? (int) $openSince->diffInMinutes(now())
                : 0,
            'resumen' => [
                'hoy' => $this->durationPayload($hoyMin),
                'semana' => $this->durationPayload($semanaMin),
                'mes' => $this->durationPayload($mesMin),
            ],
            'sesiones_hoy' => $this->todaySessions($employee, $openSession),
            'registros_hoy' => $this->todayRecords($employee),
            'historial' => $this->dailyHistory($employee, 14, $openSession),
        ];
    }

    public function minutesForRange(
        Employee $employee,
        Carbon $from,
        Carbon $to,
        ?WorkSession $openSession = null,
    ): int {
        $openSession ??= $this->openSession($employee);
        $fromStart = $from->copy()->startOfDay();
        $toEnd = $to->copy()->endOfDay();

        $minutes = 0;

        $closed = WorkSession::where('employee_id', $employee->id)
            ->where('status', WorkSession::STATUS_CLOSED)
            ->whereBetween('clocked_in_at', [$fromStart, $toEnd])
            ->get();

        foreach ($closed as $session) {
            if ($session->clocked_out_at) {
                $minutes += (int) $session->clocked_in_at->diffInMinutes($session->clocked_out_at);
            }
        }

        if (
            $openSession
            && $openSession->clocked_in_at->between($fromStart, $toEnd)
        ) {
            $minutes += (int) $openSession->clocked_in_at->diffInMinutes(now());
        }

        return $minutes;
    }

    public function formatDuration(int $minutes): string
    {
        if ($minutes < 1) {
            return '0 min';
        }

        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        if ($hours === 0) {
            return "{$mins} min";
        }

        if ($mins === 0) {
            return "{$hours} h";
        }

        return "{$hours} h {$mins} min";
    }

    private function durationPayload(int $minutes): array
    {
        return [
            'minutos' => $minutes,
            'formato' => $this->formatDuration($minutes),
            'decimal' => round($minutes / 60, 2),
        ];
    }

    public function todaySessions(Employee $employee, ?WorkSession $openSession = null): array
    {
        $openSession ??= $this->openSession($employee);

        return WorkSession::where('employee_id', $employee->id)
            ->whereDate('clocked_in_at', today())
            ->with(['clockInRecord.clockZone', 'clockOutRecord'])
            ->orderBy('clocked_in_at')
            ->get()
            ->map(function (WorkSession $session) use ($openSession) {
                $isOpen = $session->status === WorkSession::STATUS_OPEN;
                $end = $isOpen ? now() : $session->clocked_out_at;
                $minutes = $end
                    ? (int) $session->clocked_in_at->diffInMinutes($end)
                    : 0;

                $esCorreccion = $session->clockInRecord?->origin === 'correction'
                    || $session->clockOutRecord?->origin === 'correction';

                return [
                    'id' => $session->id,
                    'estado' => $isOpen ? 'abierta' : 'cerrada',
                    'entrada' => $session->clocked_in_at->format('H:i'),
                    'salida' => $isOpen ? null : $session->clocked_out_at?->format('H:i'),
                    'duracion_minutos' => $minutes,
                    'duracion' => $this->formatDuration($minutes),
                    'zona' => $session->clockInRecord?->clockZone?->name,
                    'activa' => $openSession && $openSession->id === $session->id,
                    'es_correccion' => $esCorreccion,
                ];
            })
            ->values()
            ->all();
    }

    public function todayRecords(Employee $employee): array
    {
        return TimeRecord::where('employee_id', $employee->id)
            ->active()
            ->recordedToday()
            ->with('clockZone')
            ->orderBy('recorded_at')
            ->get()
            ->map(fn (TimeRecord $record) => [
                'id' => $record->id,
                'tipo' => $record->type,
                'label' => $record->label,
                'hora' => $record->recorded_at->format('H:i:s'),
                'hora_corta' => $record->recorded_at->format('H:i'),
                'zona' => $record->clockZone?->name,
                'metodo' => $record->validation_method === 'ip'
                    ? 'Red autorizada'
                    : ($record->validation_method === 'none' ? 'Web' : ucfirst($record->validation_method)),
                'es_correccion' => $record->origin === 'correction',
                'origen' => $record->origin === 'correction' ? 'Corrección aprobada' : 'Fichaje',
            ])
            ->all();
    }

    public function dailyHistory(Employee $employee, int $days = 14, ?WorkSession $openSession = null): array
    {
        $openSession ??= $this->openSession($employee);
        $history = [];

        for ($i = 0; $i < $days; $i++) {
            $date = today()->subDays($i);
            $minutes = $this->minutesForRange($employee, $date, $date, $openSession);
            $sessionCount = WorkSession::where('employee_id', $employee->id)
                ->whereDate('clocked_in_at', $date)
                ->count();

            $history[] = [
                'fecha' => $date->toDateString(),
                'fecha_label' => $date->locale('es')->isoFormat('ddd D MMM'),
                'es_hoy' => $date->isToday(),
                'minutos' => $minutes,
                'duracion' => $this->formatDuration($minutes),
                'decimal' => round($minutes / 60, 2),
                'sesiones' => $sessionCount,
            ];
        }

        return $history;
    }
}
