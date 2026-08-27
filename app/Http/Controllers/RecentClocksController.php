<?php

namespace App\Http\Controllers;

use App\Models\TimeRecord;
use App\Models\WorkSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecentClocksController extends Controller
{
    /**
     * Get the live feed of currently active staff first, then recent clock events as fallback.
     * Only accessible to admins and managers.
     */
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin() || $request->user()->isManager(), 403);

        $openSessions = WorkSession::query()
            ->with(['employee', 'clockInRecord.clockZone'])
            ->where('status', WorkSession::STATUS_OPEN)
            ->orderBy('clocked_in_at', 'desc')
            ->limit(10)
            ->get();

        if ($openSessions->isNotEmpty()) {
            $records = $openSessions->map(function (WorkSession $session) {
                $record = $session->clockInRecord;

                return [
                    'employee_id' => $session->employee_id,
                    'employee_name' => $session->employee?->name,
                    'employee_code' => $session->employee?->employee_code,
                    'type' => TimeRecord::TYPE_CLOCK_IN,
                    'type_label' => 'Entrada',
                    'recorded_at' => $record?->recorded_at?->format('H:i') ?? $session->clocked_in_at?->format('H:i'),
                    'recorded_at_full' => $record?->recorded_at?->format('d/m/Y H:i') ?? $session->clocked_in_at?->format('d/m/Y H:i'),
                    'zona' => $record?->clockZone?->name,
                ];
            });

            return response()->json($records);
        }

        $records = TimeRecord::query()
            ->with(['employee', 'clockZone'])
            ->whereDate('recorded_at', today())
            ->orderBy('recorded_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function (TimeRecord $record) {
                return [
                    'employee_id' => $record->employee_id,
                    'employee_name' => $record->employee?->name,
                    'employee_code' => $record->employee?->employee_code,
                    'type' => $record->type,
                    'type_label' => $record->label,
                    'recorded_at' => $record->recorded_at->format('H:i'),
                    'recorded_at_full' => $record->recorded_at->format('d/m/Y H:i'),
                    'zona' => $record->clockZone?->name,
                ];
            });

        return response()->json($records);
    }
}
