<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesRolePanel;
use App\Models\AbsenceRequest;
use App\Models\CorrectionRequest;
use App\Models\EmployeeApplication;
use App\Services\AbsenceScheduleService;
use App\Services\AbsenceValidationService;
use App\Services\AuditLogService;
use App\Services\CorrectionApplicationService;
use App\Services\EmployeeAccessService;
use App\Services\NotificationService;
use App\Services\SolicitudesReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SolicitudesReviewController extends Controller
{
    use ResolvesRolePanel;

    public function __construct(
        private EmployeeAccessService $access,
        private SolicitudesReviewService $reviews,
        private CorrectionApplicationService $corrections,
        private NotificationService $notifications,
        private AbsenceScheduleService $absenceSchedule,
        private AbsenceValidationService $absenceValidation,
        private AuditLogService $audit,
    ) {}

    public function index(Request $request): Response
    {
        $panel = $this->resolvePanel($request);
        $actor = $request->user();
        $filtro = $request->query('estado');

        return Inertia::render('Panel/SolicitudesReview', [
            'panel' => $panel['panel'],
            'homeRoute' => $panel['home_route'],
            'solicitudes' => $this->reviews->listForReviewer(
                $actor,
                in_array($filtro, ['pending', 'approved', 'rejected'], true) ? $filtro : null,
            ),
            'filtroEstado' => $filtro,
            'reviewApplicationRoute' => $panel['review_application_route'],
            'reviewAbsenceRoute' => $panel['review_absence_route'],
            'reviewCorrectionRoute' => $panel['review_correction_route'],
        ]);
    }

    public function reviewEmployeeApplication(Request $request, EmployeeApplication $employeeApplication): RedirectResponse
    {
        $panel = $this->resolvePanel($request);
        $actor = $request->user();

        $validated = $request->validate([
            'action' => ['required', 'in:approve,reject'],
            'review_comment' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($employeeApplication->status !== EmployeeApplication::STATUS_PENDING) {
            return back()->withErrors(['solicitud' => 'Esta solicitud ya fue revisada.']);
        }

        $status = $validated['action'] === 'approve'
            ? EmployeeApplication::STATUS_APPROVED
            : EmployeeApplication::STATUS_REJECTED;

        $employeeApplication->update([
            'status' => $status,
            'reviewed_by' => $actor->id,
            'review_comment' => $validated['review_comment'] ?? null,
            'reviewed_at' => now(),
        ]);

        $reviewer = $actor;
        $routeName = $actor->isAdmin() ? 'admin.solicitudes.index' : 'manager.solicitudes.index';

        $this->notifications->notify(
            $reviewer,
            $status === EmployeeApplication::STATUS_APPROVED ? 'Solicitud de alta revisada' : 'Solicitud de alta revisada',
            'Has ' . ($status === EmployeeApplication::STATUS_APPROVED ? 'aprobado' : 'rechazado') . ' la solicitud de ' . $employeeApplication->candidate_name . ' ' . $employeeApplication->candidate_surname . '.',
            'employee_application_reviewed',
            route($routeName),
        );

        return redirect()
            ->route($panel['solicitudes_route'])
            ->with('success', 'Solicitud de alta actualizada.');
    }

    public function reviewAbsence(Request $request, AbsenceRequest $absenceRequest): RedirectResponse
    {
        $panel = $this->resolvePanel($request);
        $actor = $request->user();

        $validated = $request->validate([
            'action' => ['required', 'in:approve,reject'],
            'review_comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $absenceRequest->load('employee');
        $this->access->authorizeReview($actor, $absenceRequest->employee);

        if ($absenceRequest->status !== AbsenceRequest::STATUS_PENDING) {
            return back()->withErrors(['solicitud' => 'Esta solicitud ya fue revisada.']);
        }

        $status = $validated['action'] === 'approve'
            ? AbsenceRequest::STATUS_APPROVED
            : AbsenceRequest::STATUS_REJECTED;

        $origin = $actor->isAdmin() ? 'admin' : 'manager';

        DB::transaction(function () use ($absenceRequest, $validated, $actor, $status, $request, $origin) {
            if ($status === AbsenceRequest::STATUS_APPROVED) {
                $this->absenceValidation->assertNoOverlap(
                    $absenceRequest->employee_id,
                    $absenceRequest->start_date,
                    $absenceRequest->end_date,
                    $absenceRequest->id,
                );
            }

            $absenceRequest->update([
                'status' => $status,
                'reviewed_by' => $actor->id,
                'review_comment' => $validated['review_comment'] ?? null,
            ]);

            if ($status === AbsenceRequest::STATUS_APPROVED) {
                $this->absenceSchedule->syncApproved($absenceRequest->fresh());
            } else {
                $this->absenceSchedule->clearForAbsence($absenceRequest->id);
            }

            $this->audit->log(
                $actor,
                $status === AbsenceRequest::STATUS_APPROVED
                    ? 'absence_request.approved'
                    : 'absence_request.rejected',
                'absence_request',
                $absenceRequest->id,
                [
                    'employee_id' => $absenceRequest->employee_id,
                    'type' => $absenceRequest->type,
                    'start_date' => $absenceRequest->start_date->toDateString(),
                    'end_date' => $absenceRequest->end_date->toDateString(),
                ],
                $validated['review_comment'] ?? null,
                $request,
                $origin,
            );
        });

        $this->notifications->notify(
            $absenceRequest->employee_id,
            $status === AbsenceRequest::STATUS_APPROVED ? 'Solicitud aprobada' : 'Solicitud rechazada',
            "Tu solicitud de {$absenceRequest->type_label} ha sido "
                . ($status === AbsenceRequest::STATUS_APPROVED ? 'aprobada' : 'rechazada') . '.',
            'absence_request_reviewed',
            route('solicitudes.index'),
        );

        return redirect()
            ->route($panel['solicitudes_route'])
            ->with('success', 'Solicitud de ausencia actualizada.');
    }

    public function reviewCorrection(Request $request, CorrectionRequest $correctionRequest): RedirectResponse
    {
        $panel = $this->resolvePanel($request);
        $actor = $request->user();

        $validated = $request->validate([
            'action' => ['required', 'in:approve,reject'],
            'review_comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $correctionRequest->load('requester');
        $this->access->authorizeReview($actor, $correctionRequest->requester);

        $group = $this->reviews->correctionGroup($correctionRequest);

        if ($group->contains(fn (CorrectionRequest $r) => $r->status !== CorrectionRequest::STATUS_PENDING)) {
            return back()->withErrors(['solicitud' => 'Esta solicitud ya fue revisada.']);
        }

        $origin = $actor->isAdmin() ? 'admin' : 'manager';

        DB::transaction(function () use ($group, $validated, $actor, $correctionRequest, $request, $origin) {
            $syncDate = null;
            $chainTip = null;

            foreach ($group as $correction) {
                if ($validated['action'] === 'approve') {
                    $record = $this->corrections->apply($correction, $chainTip);
                    $correction->update([
                        'status' => CorrectionRequest::STATUS_APPROVED,
                        'reviewed_by' => $actor->id,
                        'review_note' => $validated['review_comment'] ?? null,
                        'corrected_record_id' => $record->id,
                        'applied_at' => now(),
                    ]);
                    $syncDate ??= $correction->new_datetime->copy()->startOfDay();
                } else {
                    $correction->update([
                        'status' => CorrectionRequest::STATUS_REJECTED,
                        'reviewed_by' => $actor->id,
                        'review_note' => $validated['review_comment'] ?? null,
                    ]);
                }
            }

            if ($validated['action'] === 'approve' && $syncDate) {
                $this->corrections->syncSessionsAfterGroup(
                    $correctionRequest->requested_by,
                    $syncDate,
                );
            }

            $this->audit->log(
                $actor,
                $validated['action'] === 'approve'
                    ? 'correction_request.approved'
                    : 'correction_request.rejected',
                'correction_request',
                $correctionRequest->id,
                ['requested_by' => $correctionRequest->requested_by, 'count' => $group->count()],
                $validated['review_comment'] ?? null,
                $request,
                $origin,
            );
        });

        $approved = $validated['action'] === 'approve';

        $this->notifications->notify(
            $correctionRequest->requested_by,
            $approved ? 'Corrección aprobada' : 'Corrección rechazada',
            'Tu solicitud de corrección de fichaje ha sido '
                . ($approved ? 'aprobada y aplicada' : 'rechazada') . '.',
            'correction_request_reviewed',
            route('solicitudes.index'),
        );

        return redirect()
            ->route($panel['solicitudes_route'])
            ->with('success', 'Solicitud de corrección actualizada.');
    }
}
