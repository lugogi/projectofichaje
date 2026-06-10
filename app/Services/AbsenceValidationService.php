<?php

namespace App\Services;

use App\Models\AbsenceRequest;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class AbsenceValidationService
{
    public function assertNoOverlap(
        string $employeeId,
        Carbon $start,
        Carbon $end,
        ?string $exceptAbsenceId = null,
    ): void {
        $query = AbsenceRequest::query()
            ->where('employee_id', $employeeId)
            ->where('status', AbsenceRequest::STATUS_APPROVED)
            ->where('start_date', '<=', $end->toDateString())
            ->where('end_date', '>=', $start->toDateString());

        if ($exceptAbsenceId) {
            $query->where('id', '!=', $exceptAbsenceId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'start_date' => 'Ya existe una ausencia aprobada que coincide con estas fechas.',
            ]);
        }
    }
}
