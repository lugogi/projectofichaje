<?php

namespace App\Services;

use App\Models\TimeRecord;
use App\Models\WorkSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class WorkSessionSyncService
{
    /**
     * Reconstruye las sesiones de un día emparejando entradas/salidas en orden (pila).
     */
    public function syncForDate(string $employeeId, Carbon $date): void
    {
        DB::transaction(function () use ($employeeId, $date) {
            $records = TimeRecord::query()
                ->where('employee_id', $employeeId)
                ->whereDate('recorded_at', $date)
                ->active()
                ->orderBy('recorded_at')
                ->orderBy('created_at')
                ->get();

            WorkSession::query()
                ->where('employee_id', $employeeId)
                ->whereDate('clocked_in_at', $date)
                ->delete();

            /** @var array<int, TimeRecord> $pendingIns */
            $pendingIns = [];

            foreach ($records as $record) {
                if ($record->type === TimeRecord::TYPE_CLOCK_IN) {
                    $pendingIns[] = $record;

                    continue;
                }

                if ($record->type !== TimeRecord::TYPE_CLOCK_OUT || $pendingIns === []) {
                    continue;
                }

                $clockIn = array_pop($pendingIns);
                $this->createClosedSession($employeeId, $clockIn, $record);
            }

            foreach ($pendingIns as $clockIn) {
                $this->createOpenSession($employeeId, $clockIn);
            }
        });
    }

    private function createOpenSession(string $employeeId, TimeRecord $clockIn): void
    {
        WorkSession::create([
            'employee_id' => $employeeId,
            'clock_in_record_id' => $clockIn->id,
            'clocked_in_at' => $clockIn->recorded_at,
            'status' => WorkSession::STATUS_OPEN,
            'processed' => false,
        ]);
    }

    private function createClosedSession(
        string $employeeId,
        TimeRecord $clockIn,
        TimeRecord $clockOut,
    ): void {
        WorkSession::create([
            'employee_id' => $employeeId,
            'clock_in_record_id' => $clockIn->id,
            'clock_out_record_id' => $clockOut->id,
            'clocked_in_at' => $clockIn->recorded_at,
            'clocked_out_at' => $clockOut->recorded_at,
            'status' => WorkSession::STATUS_CLOSED,
            'processed' => false,
        ]);
    }
}
