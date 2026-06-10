<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Employee;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_format_includes_category_and_action_outcome(): void
    {
        $employee = Employee::factory()->create();

        $pending = AppNotification::create([
            'user_id' => $employee->id,
            'title' => 'Nueva solicitud de ausencia',
            'message' => 'María ha solicitado vacaciones.',
            'event_type' => 'absence_request_pending',
        ]);

        $approved = AppNotification::create([
            'user_id' => $employee->id,
            'title' => 'Solicitud aprobada',
            'message' => 'Tu solicitud ha sido aprobada.',
            'event_type' => 'absence_request_reviewed',
        ]);

        $rejected = AppNotification::create([
            'user_id' => $employee->id,
            'title' => 'Corrección rechazada',
            'message' => 'Tu solicitud ha sido rechazada.',
            'event_type' => 'correction_request_reviewed',
        ]);

        $service = app(NotificationService::class);

        $this->assertSame('ausencia', $service->format($pending)['category']);
        $this->assertSame('pending_action', $service->format($pending)['action_outcome']);
        $this->assertSame('approved', $service->format($approved)['action_outcome']);
        $this->assertSame('rejected', $service->format($rejected)['action_outcome']);
    }
}
