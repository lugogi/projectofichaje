<?php

namespace App\Services;

use App\Models\TimeRecord;

/**
 * Cadena de integridad SHA-256 para registros de fichaje (RDL 8/2019).
 */
class TimeRecordChainService
{
    public function previousHash(string $employeeId): string
    {
        return TimeRecord::where('employee_id', $employeeId)
            ->latest('created_at')
            ->value('record_hash') ?? '';
    }

    public function computeHash(TimeRecord $record, string $previousHash): string
    {
        $payload = implode('|', [
            $record->employee_id,
            $record->type,
            $record->recorded_at->toIso8601String(),
            $previousHash,
        ]);

        return hash('sha256', $payload);
    }

    /**
     * Crea un registro encadenado. Pasa $chainTip por referencia para encadenar
     * varios registros en la misma transacción.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function append(array $attributes, ?string &$chainTip = null): TimeRecord
    {
        $employeeId = $attributes['employee_id'];
        $previousHash = $chainTip ?? $this->previousHash($employeeId);

        $record = new TimeRecord($attributes);
        $record->previous_hash = $previousHash;
        $record->record_hash = $this->computeHash($record, $previousHash);
        $record->save();

        $chainTip = $record->record_hash;

        return $record;
    }
}
