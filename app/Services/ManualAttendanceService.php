<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\TimeRecord;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManualAttendanceService
{
    public function __construct(
        private WorkSessionSyncService $sessions,
        private TimeRecordChainService $chain,
        private AuditLogService $audit,
        private NotificationService $notifications,
    ) {}

    public function register(
        Employee $target,
        Employee $actor,
        int $type,
        Carbon $recordedAt,
        string $reason,
        ?string $clockZoneId = null,
        ?Request $request = null,
    ): TimeRecord {
        if (! in_array($type, [TimeRecord::TYPE_CLOCK_IN, TimeRecord::TYPE_CLOCK_OUT], true)) {
            throw ValidationException::withMessages([
                'type' => 'El tipo de fichaje no es válido.',
            ]);
        }

        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => 'Debes indicar el motivo de la fichada manual.',
            ]);
        }

        $record = DB::transaction(function () use ($target, $actor, $type, $recordedAt, $reason, $clockZoneId, $request) {
            $record = $this->chain->append([
                'employee_id' => $target->id,
                'type' => $type,
                'recorded_at' => $recordedAt,
                'clock_method' => 'manual',
                'validation_method' => $clockZoneId ? 'ip' : 'none',
                'clock_zone_id' => $clockZoneId,
                'origin' => 'admin',
                'ip' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
                'is_suspicious' => false,
                'corrected' => false,
                'note' => 'Fichada manual por administración: ' . $reason,
            ]);

            $this->sessions->syncForDate($target->id, $recordedAt->copy()->startOfDay());

            $this->audit->log(
                $actor,
                'manual_clock.created',
                'time_record',
                $record->id,
                [
                    'employee_id' => $target->id,
                    'type' => $type === TimeRecord::TYPE_CLOCK_IN ? 'entrada' : 'salida',
                    'recorded_at' => $recordedAt->toIso8601String(),
                ],
                $reason,
                $request,
                $actor->isAdmin() ? 'admin' : 'manager',
            );

            return $record;
        });

        $label = $type === TimeRecord::TYPE_CLOCK_IN ? 'entrada' : 'salida';
        $this->notifications->notify(
            $target->id,
            'Fichada registrada por administración',
            "Se ha registrado una {$label} manual el {$recordedAt->format('d/m/Y H:i')}. Motivo: {$reason}",
            'manual_clock',
            route('fichaje.index'),
        );

        return $record;
    }
}
