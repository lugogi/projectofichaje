<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\TimeRecord;
use App\Models\WorkSession;
use App\Services\AttendanceService;
use App\Services\WorkSessionSyncService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FichajeTest extends TestCase
{
    use RefreshDatabase;

    public function test_stack_sync_pairs_out_with_latest_open_in(): void
    {
        $employee = Employee::factory()->create();
        $date = Carbon::parse('2026-06-10');

        $this->createRecord($employee, TimeRecord::TYPE_CLOCK_IN, '11:41', $date);
        $this->createRecord($employee, TimeRecord::TYPE_CLOCK_OUT, '11:59', $date);
        $this->createRecord($employee, TimeRecord::TYPE_CLOCK_IN, '12:22', $date);
        $this->createRecord($employee, TimeRecord::TYPE_CLOCK_IN, '13:24', $date);
        $this->createRecord($employee, TimeRecord::TYPE_CLOCK_OUT, '13:28', $date);
        $this->createRecord($employee, TimeRecord::TYPE_CLOCK_OUT, '13:29', $date);

        app(WorkSessionSyncService::class)->syncForDate($employee->id, $date->copy()->startOfDay());

        $sessions = WorkSession::where('employee_id', $employee->id)
            ->whereDate('clocked_in_at', $date)
            ->orderBy('clocked_in_at')
            ->get();

        $this->assertCount(3, $sessions);

        $byEntrada = $sessions->keyBy(fn (WorkSession $session) => $session->clocked_in_at->format('H:i'));

        $this->assertSame('11:59', $byEntrada['11:41']->clocked_out_at->format('H:i'));
        $this->assertSame('13:28', $byEntrada['13:24']->clocked_out_at->format('H:i'));
        $this->assertSame('13:29', $byEntrada['12:22']->clocked_out_at->format('H:i'));
        $this->assertSame(0, WorkSession::where('employee_id', $employee->id)->where('status', 'open')->count());
    }

    public function test_next_action_after_clock_in_is_clock_out(): void
    {
        $employee = Employee::factory()->create();
        $date = today();

        $this->createRecord($employee, TimeRecord::TYPE_CLOCK_IN, '09:00', $date);

        $attendance = app(AttendanceService::class);

        $this->assertTrue($attendance->isWorkingToday($employee));
        $this->assertSame(TimeRecord::TYPE_CLOCK_OUT, $attendance->nextAction($employee));
    }

    public function test_clock_out_closes_open_entry(): void
    {
        $employee = Employee::factory()->create();
        $date = today();

        $this->createRecord($employee, TimeRecord::TYPE_CLOCK_IN, '09:00', $date);

        $record = app(AttendanceService::class)->clock($employee, ['ip' => '127.0.0.1']);

        $this->assertSame(TimeRecord::TYPE_CLOCK_OUT, $record->type);
        $this->assertFalse(app(AttendanceService::class)->isWorkingToday($employee));
        $this->assertSame(0, WorkSession::where('employee_id', $employee->id)->where('status', 'open')->count());
    }

    private function createRecord(
        Employee $employee,
        int $type,
        string $time,
        Carbon $date,
    ): TimeRecord {
        return TimeRecord::create([
            'employee_id' => $employee->id,
            'type' => $type,
            'recorded_at' => $date->copy()->setTimeFromTimeString($time),
            'clock_method' => 'manual',
            'validation_method' => 'none',
            'origin' => 'web',
            'is_suspicious' => false,
            'corrected' => false,
            'record_hash' => hash('sha256', uniqid('', true)),
            'previous_hash' => hash('sha256', 'genesis'),
            'created_at' => $date->copy()->setTimeFromTimeString($time),
        ]);
    }
}
