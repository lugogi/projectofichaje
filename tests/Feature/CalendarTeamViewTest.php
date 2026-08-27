<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\ManagerEmployee;
use App\Models\TimeRecord;
use App\Models\WorkSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarTeamViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_page_marks_staff_as_team_view(): void
    {
        $admin = Employee::factory()->create(['role' => Employee::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->get(route('calendar.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Calendar/Index')
                ->where('esEquipo', true)
                ->where('esAdmin', true));
    }

    public function test_employee_calendar_is_personal_only(): void
    {
        $employee = Employee::factory()->create(['role' => Employee::ROLE_EMPLOYEE]);

        $this->actingAs($employee)
            ->get(route('calendar.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Calendar/Index')
                ->where('esEquipo', false)
                ->where('esAdmin', false));
    }

    public function test_admin_day_payload_includes_team_clocks(): void
    {
        $admin = Employee::factory()->create(['role' => Employee::ROLE_ADMIN]);
        $employee = Employee::factory()->create([
            'name' => 'María López',
            'role' => Employee::ROLE_EMPLOYEE,
            'position' => 'Oficial de 1ª',
        ]);

        $this->seedClosedDay($employee, today());

        $response = $this->actingAs($admin)->getJson(
            route('calendar.day-events', ['date' => today()->toDateString()]),
        );

        $response->assertOk()
            ->assertJsonPath('equipo.habilitado', true);

        $nombres = collect($response->json('equipo.personas'))->pluck('nombre');
        $this->assertTrue($nombres->contains('María López'));

        $maria = collect($response->json('equipo.personas'))->firstWhere('nombre', 'María López');
        $this->assertSame('completo', $maria['estado']);
        $this->assertSame('08:00', $maria['jornadas'][0]['entrada']);
        $this->assertSame('17:00', $maria['jornadas'][0]['salida']);
    }

    public function test_employee_day_payload_does_not_include_colleagues(): void
    {
        $employee = Employee::factory()->create(['role' => Employee::ROLE_EMPLOYEE]);
        Employee::factory()->create(['role' => Employee::ROLE_EMPLOYEE, 'name' => 'Otro']);

        $this->actingAs($employee)
            ->getJson(route('calendar.day-events', ['date' => today()->toDateString()]))
            ->assertOk()
            ->assertJsonPath('equipo.habilitado', false);
    }

    public function test_manager_only_sees_their_team_on_the_calendar_day(): void
    {
        $manager = Employee::factory()->create(['role' => Employee::ROLE_MANAGER]);
        $mine = Employee::factory()->create([
            'name' => 'Juan García',
            'role' => Employee::ROLE_EMPLOYEE,
        ]);
        Employee::factory()->create([
            'name' => 'Fuera Del Equipo',
            'role' => Employee::ROLE_EMPLOYEE,
        ]);

        ManagerEmployee::create([
            'manager_id' => $manager->id,
            'employee_id' => $mine->id,
            'start_date' => now()->subYear()->toDateString(),
        ]);

        $this->seedClosedDay($mine, today());

        $nombres = collect(
            $this->actingAs($manager)
                ->getJson(route('calendar.day-events', ['date' => today()->toDateString()]))
                ->json('equipo.personas'),
        )->pluck('nombre');

        $this->assertTrue($nombres->contains('Juan García'));
        $this->assertFalse($nombres->contains('Fuera Del Equipo'));
    }

    public function test_month_payload_includes_team_day_counts_for_admin(): void
    {
        $admin = Employee::factory()->create(['role' => Employee::ROLE_ADMIN]);
        $employee = Employee::factory()->create(['role' => Employee::ROLE_EMPLOYEE]);
        $this->seedClosedDay($employee, today());

        $response = $this->actingAs($admin)->getJson(route('calendar.events', [
            'month' => now()->month,
            'year' => now()->year,
        ]));

        $response->assertOk()->assertJsonPath('equipo.habilitado', true);

        $hoy = $response->json('equipo.dias.'.today()->toDateString());
        $this->assertNotNull($hoy);
        $this->assertGreaterThanOrEqual(1, $hoy['fichados']);
    }

    private function seedClosedDay(Employee $employee, $date): void
    {
        $inAt = $date->copy()->setTime(8, 0);
        $outAt = $date->copy()->setTime(17, 0);

        $entrada = TimeRecord::create([
            'employee_id' => $employee->id,
            'type' => TimeRecord::TYPE_CLOCK_IN,
            'recorded_at' => $inAt,
            'clock_method' => 'web',
            'origin' => 'web',
            'corrected' => false,
            'created_at' => $inAt,
        ]);

        $salida = TimeRecord::create([
            'employee_id' => $employee->id,
            'type' => TimeRecord::TYPE_CLOCK_OUT,
            'recorded_at' => $outAt,
            'clock_method' => 'web',
            'origin' => 'web',
            'corrected' => false,
            'created_at' => $outAt,
        ]);

        WorkSession::create([
            'employee_id' => $employee->id,
            'clock_in_record_id' => $entrada->id,
            'clock_out_record_id' => $salida->id,
            'clocked_in_at' => $inAt,
            'clocked_out_at' => $outAt,
            'status' => WorkSession::STATUS_CLOSED,
            'processed' => true,
        ]);
    }
}
