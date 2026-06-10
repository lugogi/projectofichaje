<?php

namespace App\Services;

use App\Models\AbsenceRequest;
use App\Models\CorrectionRequest;
use App\Models\Employee;
use App\Models\StoredFile;
use Illuminate\Support\Collection;

class SolicitudesReviewService
{
    public function __construct(private EmployeeAccessService $access) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listForReviewer(Employee $actor, ?string $statusFilter = null): array
    {
        $employeeIds = $this->reviewableEmployeeIds($actor);

        if ($employeeIds->isEmpty()) {
            return [];
        }

        $absences = AbsenceRequest::query()
            ->with(['employee', 'document'])
            ->whereIn('employee_id', $employeeIds)
            ->when($statusFilter, fn ($q) => $q->where('status', $statusFilter))
            ->get()
            ->map(fn (AbsenceRequest $r) => $this->formatAbsence($r));

        $corrections = $this->formatCorrectionGroups(
            CorrectionRequest::query()
                ->with(['requester', 'timeRecord'])
                ->whereIn('requested_by', $employeeIds)
                ->when($statusFilter, fn ($q) => $q->where('status', $statusFilter))
                ->get(),
        );

        $all = array_merge($corrections, $absences->all());
        usort($all, fn ($a, $b) => strtotime($b['created_at_sort']) - strtotime($a['created_at_sort']));

        return $all;
    }

    public function pendingCount(Employee $actor): int
    {
        $employeeIds = $this->reviewableEmployeeIds($actor);

        if ($employeeIds->isEmpty()) {
            return 0;
        }

        $absences = AbsenceRequest::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('status', AbsenceRequest::STATUS_PENDING)
            ->count();

        $correctionGroups = CorrectionRequest::query()
            ->whereIn('requested_by', $employeeIds)
            ->where('status', CorrectionRequest::STATUS_PENDING)
            ->get()
            ->groupBy(fn (CorrectionRequest $r) => $r->created_at->format('Y-m-d H:i:s') . '|' . $r->reason);

        return $absences + $correctionGroups->count();
    }

    public function reviewersFor(Employee $employee): Collection
    {
        $reviewers = Employee::query()
            ->where('role', Employee::ROLE_ADMIN)
            ->where('id', '!=', $employee->id)
            ->get();

        $managers = Employee::query()
            ->whereIn('id', function ($query) use ($employee) {
                $query->select('manager_id')
                    ->from('manager_employees')
                    ->where('employee_id', $employee->id)
                    ->where('start_date', '<=', today())
                    ->where(function ($q) {
                        $q->whereNull('end_date')->orWhere('end_date', '>=', today());
                    });
            })
            ->get();

        return $reviewers->merge($managers)->unique('id');
    }

    public function canDownloadAttachment(Employee $actor, StoredFile $file): bool
    {
        if ($file->uploaded_by === $actor->id) {
            return true;
        }

        if ($file->entity_type === 'absence_request') {
            $request = AbsenceRequest::find($file->entity_id);

            return $request
                && $this->access->canReviewFor($actor, $request->employee);
        }

        if ($file->entity_type === 'correction_request') {
            $request = CorrectionRequest::find($file->entity_id);

            return $request
                && $this->access->canReviewFor($actor, $request->requester);
        }

        return false;
    }

    public function correctionGroup(CorrectionRequest $correction): Collection
    {
        return CorrectionRequest::query()
            ->where('requested_by', $correction->requested_by)
            ->where('reason', $correction->reason)
            ->where('created_at', $correction->created_at)
            ->get();
    }

    private function reviewableEmployeeIds(Employee $actor): Collection
    {
        if ($actor->isAdmin()) {
            return Employee::query()
                ->where('id', '!=', $actor->id)
                ->pluck('id');
        }

        if ($actor->isManager()) {
            return $this->access->exportableEmployees($actor)->pluck('id');
        }

        return collect();
    }

    private function formatAbsence(AbsenceRequest $request): array
    {
        return [
            'id' => $request->id,
            'kind' => 'absence',
            'type_label' => $request->type_label,
            'employee_name' => $request->employee->name,
            'employee_email' => $request->employee->email,
            'period_label' => $request->start_date->format('d/m/Y')
                . ($request->end_date->toDateString() !== $request->start_date->toDateString()
                    ? ' - ' . $request->end_date->format('d/m/Y')
                    : ''),
            'reason' => $request->request_reason,
            'status' => $request->status,
            'status_label' => $this->statusLabel($request->status),
            'created_at' => $request->created_at->format('d/m/Y H:i'),
            'created_at_sort' => $request->created_at->toIso8601String(),
            'review_note' => $request->review_comment,
            'attachment' => $request->document ? [
                'id' => $request->document->id,
                'name' => $request->document->file_name,
                'url' => route('solicitudes.attachment', $request->document->id),
            ] : null,
        ];
    }

    /**
     * @param  Collection<int, CorrectionRequest>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function formatCorrectionGroups(Collection $rows): array
    {
        return $rows->groupBy(
            fn (CorrectionRequest $row) => $row->created_at->format('Y-m-d H:i:s') . '|' . $row->reason
        )->map(function ($group) {
            $ordered = $group->sortBy('new_datetime')->values();
            $first = $ordered->first();
            $last = $ordered->last();
            $isSingle = $ordered->count() === 1;
            $singleType = $first->timeRecord?->type;

            return [
                'id' => $first->id,
                'kind' => 'correction',
                'type_label' => 'Corrección de fichaje',
                'employee_name' => $first->requester->name,
                'employee_email' => $first->requester->email,
                'period_label' => $first->new_datetime->format('d/m/Y'),
                'requested_clock_in' => $isSingle && $singleType === \App\Models\TimeRecord::TYPE_CLOCK_OUT
                    ? null
                    : $first->new_datetime->format('H:i'),
                'requested_clock_out' => $isSingle && $singleType === \App\Models\TimeRecord::TYPE_CLOCK_IN
                    ? null
                    : ($ordered->count() > 1
                        ? $last->new_datetime->format('H:i')
                        : ($singleType === \App\Models\TimeRecord::TYPE_CLOCK_OUT
                            ? $first->new_datetime->format('H:i')
                            : null)),
                'reason' => $first->reason,
                'status' => $this->resolveGroupStatus($group),
                'status_label' => $this->statusLabel($this->resolveGroupStatus($group)),
                'created_at' => $first->created_at->format('d/m/Y H:i'),
                'created_at_sort' => $first->created_at->toIso8601String(),
                'review_note' => $group->pluck('review_note')->filter()->first(),
                'attachment' => null,
            ];
        })->values()->all();
    }

    private function resolveGroupStatus(Collection $group): string
    {
        if ($group->contains(fn (CorrectionRequest $r) => $r->status === CorrectionRequest::STATUS_REJECTED)) {
            return CorrectionRequest::STATUS_REJECTED;
        }
        if ($group->every(fn (CorrectionRequest $r) => $r->status === CorrectionRequest::STATUS_APPROVED)) {
            return CorrectionRequest::STATUS_APPROVED;
        }

        return CorrectionRequest::STATUS_PENDING;
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            AbsenceRequest::STATUS_APPROVED => 'Aprobada',
            AbsenceRequest::STATUS_REJECTED => 'Rechazada',
            default => 'Pendiente',
        };
    }
}
