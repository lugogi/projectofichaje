<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\WorkSession;
use Carbon\Carbon;

class TeamReportService
{
    public function __construct(
        private ExpectedWorkHoursService $expectedHours,
        private AttendanceService $attendance,
        private SolicitudesReviewService $reviews,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function dashboardStats(): array
    {
        $activeEmployees = Employee::query()
            ->where('role', '!=', Employee::ROLE_ADMIN)
            ->where('employment_status', 1)
            ->whereNull('deleted_at')
            ->count();

        $workingNow = WorkSession::query()
            ->where('status', WorkSession::STATUS_OPEN)
            ->distinct('employee_id')
            ->count('employee_id');

        $clockedToday = WorkSession::query()
            ->whereDate('clocked_in_at', today())
            ->distinct('employee_id')
            ->count('employee_id');

        $zonesActive = \App\Models\ClockZone::query()->where('active', true)->count();

        return [
            'empleados_activos' => $activeEmployees,
            'trabajando_ahora' => $workingNow,
            'ficharon_hoy' => $clockedToday,
            'salas_activas' => $zonesActive,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function monthlyTeamReport(?Carbon $month = null): array
    {
        $month ??= now();

        $employees = Employee::query()
            ->where('role', Employee::ROLE_EMPLOYEE)
            ->where('employment_status', 1)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();

        $rows = [];

        foreach ($employees as $employee) {
            $summary = $this->expectedHours->monthlySummary($employee, $month);
            $extraMinutes = max(0, $summary['diferencia_minutos']);

            $rows[] = [
                'id' => $employee->id,
                'name' => $employee->name,
                'employee_code' => $employee->employee_code,
                'esperado' => [
                    'minutos' => $summary['minutos_esperados'],
                    'formato' => $summary['formato_esperado'],
                    'decimal' => $summary['decimal_esperado'],
                ],
                'trabajado' => $summary['trabajado'],
                'extra' => [
                    'minutos' => $extraMinutes,
                    'formato' => $this->attendance->formatDuration($extraMinutes),
                    'decimal' => round($extraMinutes / 60, 2),
                    'tiene_extra' => $extraMinutes > 0,
                ],
                'porcentaje_cumplido' => $summary['porcentaje_cumplido'],
                'dias_laborables' => $summary['dias_laborables'],
            ];
        }

        return $rows;
    }

    public function pendingReviewsCount(Employee $admin): int
    {
        return $this->reviews->pendingCount($admin);
    }
}
