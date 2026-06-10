<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuditLogService
{
    public function log(
        Employee $actor,
        string $action,
        string $entityType,
        ?string $entityId = null,
        array $changes = [],
        ?string $reason = null,
        ?Request $request = null,
        string $origin = 'admin',
    ): AuditLog {
        return AuditLog::create([
            'actor_id' => $actor->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'changes' => $changes ?: null,
            'reason' => $reason,
            'request_id' => $request?->header('X-Request-ID') ?? (string) Str::uuid(),
            'origin' => $origin,
            'ip' => $request?->ip(),
        ]);
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function paginated(int $page = 1, int $perPage = 30, ?string $action = null): array
    {
        $query = AuditLog::query()
            ->with('actor:id,name,email')
            ->orderByDesc('created_at');

        if ($action) {
            $query->where('action', $action);
        }

        $total = $query->count();
        $items = $query
            ->forPage($page, $perPage)
            ->get()
            ->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'action_label' => $this->actionLabel($log->action),
                'entity_type' => $log->entity_type,
                'entity_id' => $log->entity_id,
                'changes' => $log->changes,
                'reason' => $log->reason,
                'actor' => $log->actor ? [
                    'id' => $log->actor->id,
                    'name' => $log->actor->name,
                ] : null,
                'ip' => $log->ip,
                'created_at' => $log->created_at->format('d/m/Y H:i:s'),
            ])
            ->all();

        return ['items' => $items, 'total' => $total];
    }

    public function actionLabel(string $action): string
    {
        return match ($action) {
            'employee.created' => 'Alta de empleado',
            'employee.updated' => 'Actualización de empleado',
            'employee.deactivated' => 'Baja de empleado',
            'manual_clock.created' => 'Fichada manual',
            'schedule.updated' => 'Horario actualizado',
            'clock_zone.created' => 'Sala creada',
            'clock_zone.updated' => 'Sala actualizada',
            'clock_zone.deleted' => 'Sala eliminada',
            'absence.manual_created' => 'Ausencia registrada manualmente',
            'absence.cancelled' => 'Ausencia anulada',
            'absence_request.approved' => 'Solicitud de ausencia aprobada',
            'absence_request.rejected' => 'Solicitud de ausencia rechazada',
            'correction_request.approved' => 'Corrección de fichaje aprobada',
            'correction_request.rejected' => 'Corrección de fichaje rechazada',
            default => $action,
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function actionFilters(): array
    {
        return [
            ['value' => 'employee.created', 'label' => 'Alta de empleado'],
            ['value' => 'employee.updated', 'label' => 'Actualización de empleado'],
            ['value' => 'employee.deactivated', 'label' => 'Baja de empleado'],
            ['value' => 'manual_clock.created', 'label' => 'Fichada manual'],
            ['value' => 'schedule.updated', 'label' => 'Horario actualizado'],
            ['value' => 'clock_zone.created', 'label' => 'Sala creada'],
            ['value' => 'clock_zone.updated', 'label' => 'Sala actualizada'],
            ['value' => 'clock_zone.deleted', 'label' => 'Sala eliminada'],
            ['value' => 'absence.manual_created', 'label' => 'Ausencia manual'],
            ['value' => 'absence.cancelled', 'label' => 'Ausencia anulada'],
            ['value' => 'absence_request.approved', 'label' => 'Solicitud aprobada'],
            ['value' => 'absence_request.rejected', 'label' => 'Solicitud rechazada'],
            ['value' => 'correction_request.approved', 'label' => 'Corrección aprobada'],
            ['value' => 'correction_request.rejected', 'label' => 'Corrección rechazada'],
        ];
    }
}
