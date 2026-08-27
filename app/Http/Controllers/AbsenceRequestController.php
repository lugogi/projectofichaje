<?php

namespace App\Http\Controllers;

use App\Models\AbsenceRequest;
use App\Models\CorrectionRequest;
use App\Models\StoredFile;
use App\Services\AbsenceValidationService;
use App\Services\NotificationService;
use App\Services\SolicitudesReviewService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AbsenceRequestController extends Controller
{
    public function __construct(
        private NotificationService $notifications,
        private SolicitudesReviewService $reviews,
        private AbsenceValidationService $absenceValidation,
    ) {}

    public function index(Request $request): Response
    {
        $employeeId = $request->user()->id;

        $correctionRows = CorrectionRequest::query()
            ->with('timeRecord')
            ->where('requested_by', $employeeId)
            ->get();

        $absenceRows = AbsenceRequest::query()
            ->with('document')
            ->where('employee_id', $employeeId)
            ->get();

        $corrections = $this->formatCorrectionRequests($correctionRows);
        $absences = $this->formatAbsenceRequests($absenceRows);

        $allSolicitudes = array_merge($corrections, $absences);

        usort($allSolicitudes, fn ($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));

        return Inertia::render('Solicitudes/Index', [
            'solicitudes' => $allSolicitudes,
        ]);
    }

    private function formatCorrectionRequests($rows): array
    {
        $grouped = $rows->groupBy(
            fn (CorrectionRequest $row) => $row->created_at->format('Y-m-d H:i:s') . '|' . $row->reason
        );

        return $grouped->map(function ($group) {
            $ordered = $group->sortBy('new_datetime')->values();
            $first = $ordered->first();
            $last = $ordered->last();
            $isSingle = $ordered->count() === 1;
            $singleType = $first->timeRecord?->type;

            return [
                'id' => $first->id,
                'type' => 'correction',
                'type_label' => 'Corrección',
                'work_date_label' => $first->new_datetime->format('d/m/Y'),
                'requested_clock_in' => $isSingle && $singleType === \App\Models\TimeRecord::TYPE_CLOCK_OUT
                    ? null
                    : $first->new_datetime->format('H:i'),
                'requested_clock_out' => $isSingle && $singleType === \App\Models\TimeRecord::TYPE_CLOCK_IN
                    ? null
                    : ($ordered->count() > 1
                        ? $last->new_datetime->format('H:i')
                        : ($singleType === \App\Models\TimeRecord::TYPE_CLOCK_OUT ? $first->new_datetime->format('H:i') : null)),
                'reason' => $first->reason,
                'status' => $this->resolveGroupStatus($group),
                'status_label' => $this->statusLabel($this->resolveGroupStatus($group)),
                'created_at' => $first->created_at->format('d/m/Y H:i'),
                'review_note' => $group->pluck('review_note')->filter()->first(),
                'attachment' => null,
            ];
        })->values()->all();
    }

    private function resolveGroupStatus($group): string
    {
        if ($group->contains(fn (CorrectionRequest $r) => $r->status === CorrectionRequest::STATUS_REJECTED)) {
            return CorrectionRequest::STATUS_REJECTED;
        }
        if ($group->every(fn (CorrectionRequest $r) => $r->status === CorrectionRequest::STATUS_APPROVED)) {
            return CorrectionRequest::STATUS_APPROVED;
        }
        return CorrectionRequest::STATUS_PENDING;
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:vacation,medical_leave,free_day'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'request_reason' => ['required', 'string', 'min:10', 'max:2000'],
            'attachment' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,doc,docx'],
        ]);

        $employeeId = $request->user()->id;
        $now = now();
        $documentId = null;

        $this->absenceValidation->assertNoOverlap(
            $employeeId,
            Carbon::parse($validated['start_date']),
            Carbon::parse($validated['end_date']),
        );

        DB::transaction(function () use ($request, $validated, $employeeId, $now, &$documentId) {
            $absenceRequest = AbsenceRequest::create([
                'employee_id' => $employeeId,
                'type' => $validated['type'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'request_reason' => $validated['request_reason'],
                'status' => AbsenceRequest::STATUS_PENDING,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($request->hasFile('attachment')) {
                $uploadedFile = $request->file('attachment');
                $extension = strtolower($uploadedFile->getClientOriginalExtension() ?: $uploadedFile->extension());
                $fileName = Str::uuid() . '.' . $extension;
                $directory = "absence-requests/{$absenceRequest->id}";
                $storageKey = "{$directory}/{$fileName}";

                $uploadedFile->storeAs($directory, $fileName, 'local');

                $storedFile = StoredFile::create([
                    'uploaded_by' => $employeeId,
                    'entity_type' => 'absence_request',
                    'entity_id' => $absenceRequest->id,
                    'file_name' => $uploadedFile->getClientOriginalName(),
                    'storage_provider' => 'local',
                    'bucket' => 'local',
                    'storage_key' => $storageKey,
                    'mime_type' => $uploadedFile->getMimeType(),
                    'extension' => $extension,
                    'size_bytes' => $uploadedFile->getSize(),
                    'hash_sha256' => hash_file('sha256', $uploadedFile->getRealPath()),
                    'visibility' => 'private',
                    'status' => 'stored',
                ]);
                $absenceRequest->document_id = $storedFile->id;
                $absenceRequest->save();
            }
        });

        $this->notifications->notify(
            $employeeId,
            'Solicitud de ausencia registrada',
            "Tu solicitud de {$this->getTypeLabel($validated['type'])} está pendiente de revisión.",
            'absence_request_submitted',
            route('solicitudes.index'),
        );

        $employee = $request->user();
        foreach ($this->reviews->reviewersFor($employee) as $reviewer) {
            $panelRoute = $reviewer->isAdmin() ? 'admin.solicitudes.index' : 'manager.solicitudes.index';
            $this->notifications->notify(
                $reviewer->id,
                'Nueva solicitud de ausencia',
                "{$employee->name} ha solicitado {$this->getTypeLabel($validated['type'])}.",
                'absence_request_pending',
                route($panelRoute),
            );
        }

        return redirect()
            ->route('solicitudes.index')
            ->with('success', 'Solicitud enviada. Estado: pendiente de revisión.');
    }

    public function downloadAttachment(Request $request, StoredFile $storedFile): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        abort_unless(
            app(SolicitudesReviewService::class)->canDownloadAttachment($request->user(), $storedFile)
            && in_array($storedFile->entity_type, ['absence_request', 'correction_request', 'employee_application']),
            403,
        );

        return response()->download(
            Storage::disk('local')->path($storedFile->storage_key),
            $storedFile->file_name,
        );
    }

    private function formatAbsenceRequests($requests): array
    {
        return $requests->map(function ($request) {
            return [
                'id' => $request->id,
                'type' => 'absence',
                'sub_type' => $request->type,
                'type_label' => $request->type_label,
                'work_date_label' => $request->start_date->format('d/m/Y') . ($request->end_date !== $request->start_date ? ' - ' . $request->end_date->format('d/m/Y') : ''),
                'start_date' => $request->start_date->format('Y-m-d'),
                'end_date' => $request->end_date->format('Y-m-d'),
                'reason' => $request->request_reason,
                'status' => $request->status,
                'status_label' => $this->statusLabel($request->status),
                'created_at' => $request->created_at->format('d/m/Y H:i'),
                'attachment' => $request->document ? [
                    'id' => $request->document->id,
                    'name' => $request->document->file_name,
                    'url' => route('solicitudes.attachment', $request->document->id),
                ] : null,
            ];
        })->all();
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            AbsenceRequest::STATUS_APPROVED => 'Aprobada',
            AbsenceRequest::STATUS_REJECTED => 'Rechazada',
            default => 'Pendiente',
        };
    }

    private function getTypeLabel(string $type): string
    {
        return match ($type) {
            AbsenceRequest::TYPE_VACATION => 'vacaciones',
            AbsenceRequest::TYPE_MEDICAL_LEAVE => 'baja médica',
            AbsenceRequest::TYPE_FREE_DAY => 'día libre',
            default => $type,
        };
    }
}