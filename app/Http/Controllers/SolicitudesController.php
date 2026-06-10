<?php

namespace App\Http\Controllers;

use App\Models\CorrectionRequest;
use App\Models\TimeRecord;
use App\Services\CorrectionRequestFileService;
use App\Services\NotificationService;
use App\Services\SolicitudesReviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SolicitudesController extends Controller
{
    public function __construct(
        private CorrectionRequestFileService $files,
        private NotificationService $notifications,
        private SolicitudesReviewService $reviews,
    ) {}

    public function storeApi(Request $request)
    {
        $blockMode = $request->filled('time_record_id_in') && $request->filled('time_record_id_out');
        $partialBlockMode = $request->filled('time_record_id_in')
            && ! $request->filled('time_record_id_out')
            && $request->filled('clock_out_time');
        $singleRecord = ! $blockMode
            && ! $partialBlockMode
            && $request->filled('time_record_id');

        $rules = [
            'work_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
            'attachment' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,doc,docx'],
            'time_record_id' => ['nullable', 'string', 'size:26'],
            'time_record_id_in' => ['nullable', 'string', 'size:26'],
            'time_record_id_out' => ['nullable', 'string', 'size:26'],
        ];

        if ($singleRecord) {
            $rules['record_type'] = ['required', 'in:1,2'];
            $rules['clock_in_time'] = ['required_if:record_type,1', 'nullable', 'date_format:H:i'];
            $rules['clock_out_time'] = ['required_if:record_type,2', 'nullable', 'date_format:H:i'];
        } elseif ($blockMode || $partialBlockMode) {
            $rules['clock_in_time'] = ['required', 'date_format:H:i'];
            $rules['clock_out_time'] = ['required', 'date_format:H:i'];
        } else {
            $rules['clock_in_time'] = ['required', 'date_format:H:i'];
            $rules['clock_out_time'] = ['required', 'date_format:H:i'];
        }

        $validated = $request->validate($rules);

        $employeeId = $request->user()->id;
        $workDate = $validated['work_date'];
        $now = now();
        $primaryRequestId = null;

        if (! $singleRecord) {
            $clockIn = Carbon::parse("{$workDate} {$validated['clock_in_time']}");
            $clockOut = Carbon::parse("{$workDate} {$validated['clock_out_time']}");

            if ($clockOut->lte($clockIn)) {
                return back()->withErrors([
                    'clock_out_time' => 'La hora de salida debe ser posterior a la de entrada.',
                ]);
            }
        }

        DB::transaction(function () use ($request, $validated, $employeeId, $workDate, $now, $singleRecord, $blockMode, $partialBlockMode, &$primaryRequestId) {
            if ($singleRecord) {
                $record = TimeRecord::query()
                    ->where('id', $validated['time_record_id'])
                    ->where('employee_id', $employeeId)
                    ->firstOrFail();

                $time = (int) $validated['record_type'] === TimeRecord::TYPE_CLOCK_IN
                    ? $validated['clock_in_time']
                    : $validated['clock_out_time'];

                $correction = $this->createCorrectionRow(
                    $employeeId,
                    Carbon::parse("{$workDate} {$time}"),
                    (int) $validated['record_type'],
                    $validated['reason'],
                    $now,
                    $record->id,
                );
                $primaryRequestId = $correction->id;
            } else {
                $clockIn = Carbon::parse("{$workDate} {$validated['clock_in_time']}");
                $clockOut = Carbon::parse("{$workDate} {$validated['clock_out_time']}");

                if ($blockMode || $partialBlockMode) {
                    $in = $this->createCorrectionRow(
                        $employeeId,
                        $clockIn,
                        TimeRecord::TYPE_CLOCK_IN,
                        $validated['reason'],
                        $now,
                        $validated['time_record_id_in'],
                    );
                    $this->createCorrectionRow(
                        $employeeId,
                        $clockOut,
                        TimeRecord::TYPE_CLOCK_OUT,
                        $validated['reason'],
                        $now,
                        $partialBlockMode ? null : $validated['time_record_id_out'],
                    );
                    $primaryRequestId = $in->id;
                } else {
                    $in = $this->createCorrectionRow(
                        $employeeId,
                        $clockIn,
                        TimeRecord::TYPE_CLOCK_IN,
                        $validated['reason'],
                        $now,
                    );
                    $this->createCorrectionRow(
                        $employeeId,
                        $clockOut,
                        TimeRecord::TYPE_CLOCK_OUT,
                        $validated['reason'],
                        $now,
                    );
                    $primaryRequestId = $in->id;
                }
            }

            if ($request->hasFile('attachment') && $primaryRequestId) {
                $this->files->storeForCorrectionRequest(
                    $request->file('attachment'),
                    $primaryRequestId,
                    $employeeId,
                );
            }
        });

        $fechaLabel = Carbon::parse($workDate)->format('d/m/Y');
        $this->notifications->notify(
            $employeeId,
            'Solicitud registrada',
            "Tu solicitud de modificación del día {$fechaLabel} está pendiente de revisión.",
            'correction_request_submitted',
            route('solicitudes.index'),
        );

        $employee = $request->user();
        foreach ($this->reviews->reviewersFor($employee) as $reviewer) {
            $panelRoute = $reviewer->isAdmin() ? 'admin.solicitudes.index' : 'manager.solicitudes.index';
            $this->notifications->notify(
                $reviewer->id,
                'Nueva solicitud de corrección',
                "{$employee->name} ha solicitado una corrección de fichaje del {$fechaLabel}.",
                'correction_request_pending',
                route($panelRoute),
            );
        }

        return back()->with('success', 'Solicitud enviada. Estado: pendiente de revisión.');
    }

    private function createCorrectionRow(
        string $employeeId,
        Carbon $newDatetime,
        int $recordType,
        string $reason,
        Carbon $createdAt,
        ?string $timeRecordId = null,
    ): CorrectionRequest {
        return CorrectionRequest::create([
            'time_record_id' => $timeRecordId ?? $this->findTimeRecordId($employeeId, $newDatetime->toDateString(), $recordType),
            'requested_by' => $employeeId,
            'new_datetime' => $newDatetime,
            'reason' => $reason,
            'status' => CorrectionRequest::STATUS_PENDING,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function findTimeRecordId(string $employeeId, string $date, int $type): ?string
    {
        return TimeRecord::query()
            ->where('employee_id', $employeeId)
            ->where('type', $type)
            ->whereDate('recorded_at', $date)
            ->orderBy('recorded_at')
            ->value('id');
    }
}