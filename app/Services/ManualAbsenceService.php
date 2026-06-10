<?php

namespace App\Services;

use App\Models\AbsenceRequest;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManualAbsenceService
{
    public const MANUAL_MARKER = 'Registro manual:';

    public function __construct(
        private EmployeeAccessService $access,
        private AbsenceScheduleService $schedule,
        private AbsenceValidationService $validation,
        private AuditLogService $audit,
        private NotificationService $notifications,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listForReviewer(Employee $actor): array
    {
        $employeeIds = $this->access->exportableEmployees($actor)->pluck('id');

        if ($employeeIds->isEmpty()) {
            return [];
        }

        return AbsenceRequest::query()
            ->with('employee:id,name,employee_code')
            ->whereIn('employee_id', $employeeIds)
            ->where('status', AbsenceRequest::STATUS_APPROVED)
            ->where('end_date', '>=', today()->subMonths(3)->toDateString())
            ->orderByDesc('start_date')
            ->get()
            ->map(fn (AbsenceRequest $a) => $this->format($a))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function register(
        Employee $target,
        Employee $actor,
        array $data,
        ?Request $request = null,
    ): AbsenceRequest {
        $this->access->authorizeReview($actor, $target);

        $start = Carbon::parse($data['start_date'])->startOfDay();
        $end = Carbon::parse($data['end_date'])->startOfDay();

        if ($end->lt($start)) {
            throw ValidationException::withMessages([
                'end_date' => 'La fecha de fin debe ser igual o posterior a la de inicio.',
            ]);
        }

        $this->validation->assertNoOverlap($target->id, $start, $end);

        $note = trim($data['note'] ?? '');
        if ($note === '') {
            throw ValidationException::withMessages([
                'note' => 'Indica el motivo o referencia del registro.',
            ]);
        }

        $absence = DB::transaction(function () use ($target, $actor, $data, $start, $end, $note, $request) {
            $absence = AbsenceRequest::create([
                'employee_id' => $target->id,
                'type' => $data['type'],
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'request_reason' => $note,
                'status' => AbsenceRequest::STATUS_APPROVED,
                'reviewed_by' => $actor->id,
                'review_comment' => self::MANUAL_MARKER . ' ' . $note,
            ]);

            $this->schedule->syncApproved($absence);

            $this->audit->log(
                $actor,
                'absence.manual_created',
                'absence_request',
                $absence->id,
                [
                    'employee_id' => $target->id,
                    'type' => $absence->type,
                    'start_date' => $absence->start_date->toDateString(),
                    'end_date' => $absence->end_date->toDateString(),
                ],
                $note,
                $request,
                $actor->isAdmin() ? 'admin' : 'manager',
            );

            return $absence;
        });

        $label = $absence->type_label;
        $rango = $start->equalTo($end)
            ? $start->format('d/m/Y')
            : $start->format('d/m/Y') . ' – ' . $end->format('d/m/Y');

        $this->notifications->notify(
            $target->id,
            'Ausencia registrada',
            "Se ha registrado {$label} del {$rango}. No necesitas solicitarla: ya está aprobada.",
            'absence_manual',
            route('solicitudes.index'),
        );

        return $absence;
    }

    public function cancel(
        AbsenceRequest $absence,
        Employee $actor,
        ?Request $request = null,
    ): void {
        $absence->load('employee');
        $this->access->authorizeReview($actor, $absence->employee);

        if ($absence->status !== AbsenceRequest::STATUS_APPROVED) {
            throw ValidationException::withMessages([
                'absence' => 'Solo se pueden anular ausencias aprobadas.',
            ]);
        }

        DB::transaction(function () use ($absence, $actor, $request) {
            $snapshot = $this->format($absence);

            $this->schedule->clearForAbsence($absence->id);
            $absence->delete();

            $this->audit->log(
                $actor,
                'absence.cancelled',
                'absence_request',
                $absence->id,
                $snapshot,
                null,
                $request,
                $actor->isAdmin() ? 'admin' : 'manager',
            );
        });

        $this->notifications->notify(
            $absence->employee_id,
            'Ausencia anulada',
            'Una ausencia registrada en tu calendario ha sido anulada por tu encargado o administración.',
            'absence_cancelled',
            route('solicitudes.index'),
        );
    }

    public function isManual(AbsenceRequest $absence): bool
    {
        return str_starts_with((string) $absence->review_comment, self::MANUAL_MARKER);
    }

    /**
     * @return array<string, mixed>
     */
    public function format(AbsenceRequest $absence): array
    {
        $absence->loadMissing('employee:id,name,employee_code');

        return [
            'id' => $absence->id,
            'employee_id' => $absence->employee_id,
            'employee_name' => $absence->employee?->name,
            'employee_code' => $absence->employee?->employee_code,
            'type' => $absence->type,
            'type_label' => $absence->type_label,
            'start_date' => $absence->start_date->toDateString(),
            'end_date' => $absence->end_date->toDateString(),
            'periodo_label' => $this->periodLabel($absence),
            'note' => $absence->request_reason,
            'es_manual' => $this->isManual($absence),
            'created_at' => $absence->created_at->format('d/m/Y H:i'),
        ];
    }

    private function periodLabel(AbsenceRequest $absence): string
    {
        if ($absence->start_date->equalTo($absence->end_date)) {
            return $absence->start_date->format('d/m/Y');
        }

        return $absence->start_date->format('d/m/Y') . ' – ' . $absence->end_date->format('d/m/Y');
    }

}
