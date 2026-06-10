<?php

namespace App\Services;

use App\Models\AbsenceRequest;
use App\Models\Employee;
use App\Models\ScheduleException;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AbsenceScheduleService
{
    public const REASON_PREFIX = 'absence_request:';

    public function __construct(private EmployeeAccessService $access) {}

    public static function isNonWorkAbsenceType(?string $type): bool
    {
        return in_array($type, [
            'holiday',
            AbsenceRequest::TYPE_VACATION,
            AbsenceRequest::TYPE_MEDICAL_LEAVE,
            AbsenceRequest::TYPE_FREE_DAY,
        ], true);
    }

    public function syncApproved(AbsenceRequest $absence): void
    {
        $this->clearForAbsence($absence->id);

        if ($absence->status !== AbsenceRequest::STATUS_APPROVED) {
            return;
        }

        $date = $absence->start_date->copy();

        while ($date->lte($absence->end_date)) {
            ScheduleException::create([
                'employee_id' => $absence->employee_id,
                'date' => $date->toDateString(),
                'type' => $absence->type,
                'reason' => self::REASON_PREFIX . $absence->id,
            ]);
            $date->addDay();
        }
    }

    public function clearForAbsence(string $absenceRequestId): void
    {
        ScheduleException::query()
            ->where('reason', self::REASON_PREFIX . $absenceRequestId)
            ->delete();
    }

    /**
     * @return array{type: string, label: string, absence_id: string}|null
     */
    public function absenceOnDate(Employee $employee, Carbon $date): ?array
    {
        return $this->absencesInRange($employee, $date, $date)->first();
    }

    /**
     * @return Collection<int, array{date: string, type: string, label: string, absence_id: string}>
     */
    public function absencesInRange(Employee $employee, Carbon $start, Carbon $end): Collection
    {
        $approved = AbsenceRequest::query()
            ->where('employee_id', $employee->id)
            ->where('status', AbsenceRequest::STATUS_APPROVED)
            ->where('start_date', '<=', $end->toDateString())
            ->where('end_date', '>=', $start->toDateString())
            ->get();

        $days = collect();

        foreach ($approved as $absence) {
            $cursor = $absence->start_date->copy()->max($start);
            $until = $absence->end_date->copy()->min($end);

            while ($cursor->lte($until)) {
                $days->push([
                    'date' => $cursor->toDateString(),
                    'type' => $absence->type,
                    'label' => $absence->type_label,
                    'absence_id' => $absence->id,
                ]);
                $cursor->addDay();
            }
        }

        return $days->unique('date')->values();
    }

    /**
     * Ausencias del equipo visibles para admin/encargado.
     *
     * @return Collection<int, array{date: string, employee_id: string, employee_name: string, type: string, label: string}>
     */
    public function teamAbsencesInRange(Employee $viewer, Carbon $start, Carbon $end): Collection
    {
        if (! $viewer->isAdmin() && ! $viewer->isManager()) {
            return collect();
        }

        $employeeIds = $this->access->exportableEmployees($viewer)->pluck('id');

        if ($employeeIds->isEmpty()) {
            return collect();
        }

        $approved = AbsenceRequest::query()
            ->with('employee')
            ->whereIn('employee_id', $employeeIds)
            ->where('status', AbsenceRequest::STATUS_APPROVED)
            ->where('start_date', '<=', $end->toDateString())
            ->where('end_date', '>=', $start->toDateString())
            ->get();

        $entries = collect();

        foreach ($approved as $absence) {
            $cursor = $absence->start_date->copy()->max($start);
            $until = $absence->end_date->copy()->min($end);

            while ($cursor->lte($until)) {
                $entries->push([
                    'date' => $cursor->toDateString(),
                    'employee_id' => $absence->employee_id,
                    'employee_name' => $absence->employee->name,
                    'type' => $absence->type,
                    'label' => $absence->type_label,
                ]);
                $cursor->addDay();
            }
        }

        return $entries->sortBy(['date', 'employee_name'])->values();
    }

    public function typeLabel(string $type): string
    {
        return match ($type) {
            AbsenceRequest::TYPE_VACATION => 'Vacaciones',
            AbsenceRequest::TYPE_MEDICAL_LEAVE => 'Baja médica',
            AbsenceRequest::TYPE_FREE_DAY => 'Día libre',
            default => $type,
        };
    }
}
