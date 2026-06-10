<?php

namespace App\Console\Commands;

use App\Models\TimeRecord;
use App\Services\WorkSessionSyncService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncWorkSessionsCommand extends Command
{
    protected $signature = 'fichaje:sync-sessions {date?}';

    protected $description = 'Reconstruye las jornadas (work_sessions) a partir de fichajes vigentes';

    public function handle(WorkSessionSyncService $sync): int
    {
        $date = $this->argument('date')
            ? Carbon::parse($this->argument('date'))
            : today();

        $pairs = TimeRecord::query()
            ->whereDate('recorded_at', $date)
            ->select('employee_id')
            ->distinct()
            ->pluck('employee_id');

        foreach ($pairs as $employeeId) {
            $sync->syncForDate($employeeId, $date);
        }

        $this->info("Sesiones sincronizadas para {$pairs->count()} empleado(s) en {$date->toDateString()}.");

        return self::SUCCESS;
    }
}
