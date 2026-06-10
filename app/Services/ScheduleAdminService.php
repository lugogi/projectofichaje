<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeDaySchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ScheduleAdminService
{
    public function __construct(private AuditLogService $audit) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function weeklySchedule(Employee $employee): array
    {
        $schedules = EmployeeDaySchedule::query()
            ->where('employee_id', $employee->id)
            ->where('active', true)
            ->orderBy('weekday')
            ->get()
            ->keyBy('weekday');

        $days = [];
        foreach ($this->weekdayLabels() as $weekday => $label) {
            $schedule = $schedules->get($weekday);
            $days[] = [
                'weekday' => $weekday,
                'label' => $label,
                'active' => $schedule !== null,
                'start_time' => $schedule ? substr((string) $schedule->start_time, 0, 5) : '08:00',
                'end_time' => $schedule ? substr((string) $schedule->end_time, 0, 5) : '17:00',
                'schedule_id' => $schedule?->id,
            ];
        }

        return $days;
    }

    /**
     * @param  list<array{weekday: int, active: bool, start_time: string, end_time: string}>  $days
     */
    public function updateWeeklySchedule(
        Employee $employee,
        array $days,
        Employee $actor,
        ?Request $request = null,
    ): void {
        DB::transaction(function () use ($employee, $days, $actor, $request) {
            EmployeeDaySchedule::query()
                ->where('employee_id', $employee->id)
                ->delete();

            foreach ($days as $day) {
                if (! ($day['active'] ?? false)) {
                    continue;
                }

                EmployeeDaySchedule::create([
                    'id' => (string) Str::ulid(),
                    'employee_id' => $employee->id,
                    'weekday' => (int) $day['weekday'],
                    'start_time' => $day['start_time'] . ':00',
                    'end_time' => $day['end_time'] . ':00',
                    'active' => true,
                    'start_date' => today()->toDateString(),
                    'end_date' => '2099-12-31',
                ]);
            }

            $this->audit->log(
                $actor,
                'schedule.updated',
                'employee',
                $employee->id,
                ['days' => $days],
                null,
                $request,
            );
        });
    }

    /**
     * @return array<int, string>
     */
    public function weekdayLabels(): array
    {
        return [
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
            0 => 'Domingo',
        ];
    }
}
