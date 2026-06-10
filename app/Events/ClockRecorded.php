<?php

namespace App\Events;

use App\Models\Employee;
use App\Models\TimeRecord;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ClockRecorded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public TimeRecord $record,
        public Employee $employee,
    ) {}

    /**
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('fichaje.staff'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'clock.recorded';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $this->record->loadMissing('clockZone');

        return [
            'employee_id' => $this->employee->id,
            'employee_name' => $this->employee->name,
            'employee_code' => $this->employee->employee_code,
            'type' => $this->record->type,
            'type_label' => $this->record->label,
            'recorded_at' => $this->record->recorded_at->format('H:i'),
            'recorded_at_full' => $this->record->recorded_at->format('d/m/Y H:i'),
            'zona' => $this->record->clockZone?->name,
        ];
    }
}
