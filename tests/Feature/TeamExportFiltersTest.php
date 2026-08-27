<?php

namespace Tests\Feature;

use App\Mail\TeamAttendanceForLaboralMail;
use App\Models\AbsenceRequest;
use App\Models\Employee;
use App\Models\ManagerEmployee;
use App\Services\EmployeeAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TeamExportFiltersTest extends TestCase
{
    use RefreshDatabase;

    private Employee $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Employee::factory()->create(['role' => Employee::ROLE_ADMIN]);

        Employee::factory()->create([
            'name' => 'Operario Producción',
            'role' => Employee::ROLE_EMPLOYEE,
            'position' => 'Operario/a',
            'department' => 'Producción',
        ]);

        Employee::factory()->create([
            'name' => 'Jefe de línea',
            'role' => Employee::ROLE_EMPLOYEE,
            'position' => 'Jefe/a de línea',
            'department' => 'Producción',
        ]);

        Employee::factory()->create([
            'name' => 'Mozo Almacén',
            'role' => Employee::ROLE_EMPLOYEE,
            'position' => 'Mozo/a de almacén',
            'department' => 'Logística',
        ]);
    }

    public function test_without_filters_returns_the_whole_staff(): void
    {
        $result = app(EmployeeAccessService::class)->exportableEmployees($this->admin);

        $this->assertCount(3, $result);
    }

    public function test_can_filter_by_department(): void
    {
        $result = app(EmployeeAccessService::class)
            ->exportableEmployees($this->admin, ['department' => 'Producción']);

        $this->assertCount(2, $result);
        $this->assertEqualsCanonicalizing(
            ['Operario Producción', 'Jefe de línea'],
            $result->pluck('name')->all(),
        );
    }

    public function test_can_filter_by_position(): void
    {
        $result = app(EmployeeAccessService::class)
            ->exportableEmployees($this->admin, ['position' => 'Operario/a']);

        $this->assertCount(1, $result);
        $this->assertSame('Operario Producción', $result->first()->name);
    }

    public function test_can_filter_by_explicit_selection(): void
    {
        $ids = Employee::where('department', 'Logística')->pluck('id')->all();

        $result = app(EmployeeAccessService::class)
            ->exportableEmployees($this->admin, ['employee_ids' => $ids]);

        $this->assertCount(1, $result);
        $this->assertSame('Mozo Almacén', $result->first()->name);
    }

    public function test_filter_options_only_list_existing_values(): void
    {
        $options = app(EmployeeAccessService::class)->exportableFilterOptions($this->admin);

        $this->assertEqualsCanonicalizing(['Producción', 'Logística'], $options['departments']);
        $this->assertContains('Operario/a', $options['positions']);
    }

    public function test_manager_only_sees_their_own_team_when_filtering(): void
    {
        $manager = Employee::factory()->create(['role' => Employee::ROLE_MANAGER]);
        $mine = Employee::where('name', 'Operario Producción')->first();

        ManagerEmployee::create([
            'manager_id' => $manager->id,
            'employee_id' => $mine->id,
            'start_date' => now()->subYear()->toDateString(),
        ]);

        $result = app(EmployeeAccessService::class)
            ->exportableEmployees($manager, ['department' => 'Producción']);

        // "Jefe de línea" también es de Producción, pero no está a su cargo.
        $this->assertCount(1, $result);
        $this->assertSame('Operario Producción', $result->first()->name);
    }

    public function test_export_endpoint_returns_a_spreadsheet(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.exports.team'), [
            'month' => now()->format('Y-m'),
            'department' => 'Producción',
        ]);

        $response->assertOk();
        $this->assertStringContainsString(
            'spreadsheetml',
            $response->headers->get('content-type'),
        );
        $this->assertStringContainsString(
            'produccion',
            $response->headers->get('content-disposition'),
        );
    }

    public function test_export_fails_when_no_one_matches_the_filters(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.exports.team'), [
            'month' => now()->format('Y-m'),
            'department' => 'Departamento inexistente',
        ]);

        $response->assertStatus(422);
    }

    public function test_employees_cannot_export_the_team(): void
    {
        $employee = Employee::factory()->create(['role' => Employee::ROLE_EMPLOYEE]);

        $this->actingAs($employee)
            ->post(route('admin.exports.team'), ['month' => now()->format('Y-m')])
            ->assertForbidden();
    }

    public function test_export_endpoint_returns_pdf(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.exports.team'), [
            'month' => now()->format('Y-m'),
            'format' => 'pdf',
            'department' => 'Producción',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString(
            'produccion',
            $response->headers->get('content-disposition'),
        );
    }

    public function test_export_endpoint_returns_json(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.exports.team'), [
            'month' => now()->format('Y-m'),
            'format' => 'json',
            'department' => 'Producción',
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'mes',
            'periodo',
            'filtros',
            'totales' => [
                'trabajadores',
                'horas_trabajadas',
                'horas_nomina',
                'horas_extra',
                'importe_horas_extra',
            ],
            'trabajadores',
        ]);
        $this->assertCount(2, $response->json('trabajadores'));
        $this->assertArrayHasKey('horas_extra', $response->json('trabajadores.0'));
        $this->assertArrayHasKey('vacaciones_y_bajas', $response->json());
    }

    public function test_json_export_lists_approved_vacations_and_medical_leaves(): void
    {
        $employee = Employee::where('name', 'Operario Producción')->first();

        AbsenceRequest::create([
            'employee_id' => $employee->id,
            'type' => AbsenceRequest::TYPE_VACATION,
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->startOfMonth()->addDays(4)->toDateString(),
            'request_reason' => 'Vacaciones de prueba',
            'status' => AbsenceRequest::STATUS_APPROVED,
        ]);

        AbsenceRequest::create([
            'employee_id' => $employee->id,
            'type' => AbsenceRequest::TYPE_MEDICAL_LEAVE,
            'start_date' => now()->startOfMonth()->addDays(10)->toDateString(),
            'end_date' => now()->startOfMonth()->addDays(14)->toDateString(),
            'request_reason' => 'Baja de prueba',
            'status' => AbsenceRequest::STATUS_APPROVED,
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.exports.team'), [
            'month' => now()->format('Y-m'),
            'department' => 'Producción',
            'format' => 'json',
        ]);

        $response->assertOk();

        $ausencias = collect($response->json('vacaciones_y_bajas'));
        $this->assertTrue($ausencias->contains('tipo_label', 'Vacaciones'));
        $this->assertTrue($ausencias->contains('tipo_label', 'Baja laboral'));
        $this->assertTrue($ausencias->contains('nombre', 'Operario Producción'));
    }

    public function test_send_to_laboral_explains_when_not_configured(): void
    {
        config(['laboral.enabled' => false, 'laboral.email' => null]);

        $this->actingAs($this->admin)
            ->postJson(route('admin.exports.laboral'), [
                'month' => now()->format('Y-m'),
                'department' => 'Producción',
            ])
            ->assertOk()
            ->assertJson([
                'ok' => false,
                'configurado' => false,
            ]);
    }

    public function test_send_to_laboral_mails_the_spreadsheet_when_configured(): void
    {
        Mail::fake();

        config([
            'laboral.enabled' => true,
            'laboral.email' => 'nominas@asesoria.test',
            'laboral.name' => 'Asesoría demo',
        ]);

        $this->actingAs($this->admin)
            ->postJson(route('admin.exports.laboral'), [
                'month' => now()->format('Y-m'),
                'department' => 'Producción',
            ])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'configurado' => true,
            ]);

        Mail::assertSent(TeamAttendanceForLaboralMail::class, function (TeamAttendanceForLaboralMail $mail) {
            return $mail->hasTo('nominas@asesoria.test');
        });
    }

    public function test_employees_cannot_send_the_team_to_laboral(): void
    {
        $employee = Employee::factory()->create(['role' => Employee::ROLE_EMPLOYEE]);

        $this->actingAs($employee)
            ->postJson(route('admin.exports.laboral'), ['month' => now()->format('Y-m')])
            ->assertForbidden();
    }
}
