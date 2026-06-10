<?php

namespace App\Services;

use App\Events\ClockRecorded;
use App\Models\Employee;
use App\Models\ManagerEmployee;
use App\Models\TimeRecord;
use Illuminate\Support\Collection;

class ClockNotificationService
{
    public function __construct(
        private NotificationService $notifications,
        private RealtimeBroadcastService $realtime,
    ) {}

    public function notifyReviewers(TimeRecord $record, Employee $employee): void
    {
        $recipients = $this->recipientsFor($employee);

        if ($recipients->isEmpty()) {
            return;
        }

        $typeLabel = $record->type === TimeRecord::TYPE_CLOCK_IN ? 'entrada' : 'salida';
        $hora = $record->recorded_at->format('H:i');

        foreach ($recipients as $reviewer) {
            $homeRoute = $reviewer->isAdmin() ? 'admin.index' : 'manager.index';

            $this->notifications->notify(
                $reviewer,
                'Nuevo fichaje',
                "{$employee->name} ha registrado {$typeLabel} a las {$hora}.",
                'clock_recorded',
                route($homeRoute),
            );
        }

        $this->realtime->send(new ClockRecorded($record, $employee));
    }

    /**
     * @return Collection<int, Employee>
     */
    private function recipientsFor(Employee $employee): Collection
    {
        $ids = collect();

        $ids = $ids->merge(
            Employee::query()
                ->where('role', Employee::ROLE_ADMIN)
                ->where('employment_status', 1)
                ->whereNull('deleted_at')
                ->where('id', '!=', $employee->id)
                ->pluck('id')
        );

        $ids = $ids->merge(
            Employee::query()
                ->where('role', Employee::ROLE_MANAGER)
                ->where('notify_all_checkins', true)
                ->where('employment_status', 1)
                ->whereNull('deleted_at')
                ->where('id', '!=', $employee->id)
                ->pluck('id')
        );

        $ids = $ids->merge(
            ManagerEmployee::query()
                ->active()
                ->where('employee_id', $employee->id)
                ->pluck('manager_id')
        );

        $uniqueIds = $ids->unique()->filter()->values();

        if ($uniqueIds->isEmpty()) {
            return collect();
        }

        return Employee::query()->whereIn('id', $uniqueIds)->get();
    }
}
