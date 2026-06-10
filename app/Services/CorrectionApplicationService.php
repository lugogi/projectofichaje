<?php

namespace App\Services;

use App\Models\CorrectionRequest;
use App\Models\TimeRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CorrectionApplicationService
{
    public function __construct(
        private WorkSessionSyncService $sessions,
        private TimeRecordChainService $chain,
    ) {}

    /**
     * @param  string|null  $chainTip  Punta de la cadena de hashes en la misma transacción.
     */
    public function apply(CorrectionRequest $correction, ?string &$chainTip = null): TimeRecord
    {
        if ($correction->status !== CorrectionRequest::STATUS_PENDING) {
            throw new \RuntimeException('Solo se pueden aplicar correcciones pendientes.');
        }

        $original = $correction->time_record_id
            ? TimeRecord::find($correction->time_record_id)
            : null;

        if ($original) {
            DB::table('time_records')
                ->where('id', $original->id)
                ->update(['corrected' => true]);
        }

        $type = $original?->type
            ?? ($correction->timeRecord?->type)
            ?? $this->inferTypeFromContext($correction);

        return $this->chain->append([
            'employee_id' => $correction->requested_by,
            'type' => $type,
            'recorded_at' => $correction->new_datetime,
            'clock_method' => 'manual',
            'validation_method' => 'none',
            'origin' => 'correction',
            'corrected' => false,
            'original_record_id' => $original?->id,
            'note' => 'Corrección aprobada: ' . $correction->reason,
        ], $chainTip);
    }

    public function syncSessionsAfterGroup(string $employeeId, Carbon $date): void
    {
        $this->sessions->syncForDate($employeeId, $date);
    }

    private function inferTypeFromContext(CorrectionRequest $correction): int
    {
        $hour = (int) $correction->new_datetime->format('H');

        return $hour < 14 ? TimeRecord::TYPE_CLOCK_IN : TimeRecord::TYPE_CLOCK_OUT;
    }
}
