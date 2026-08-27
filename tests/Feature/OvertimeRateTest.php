<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\ManagerEmployee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OvertimeRateTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_update_team_overtime_rates(): void
    {
        $manager = Employee::factory()->create(['role' => Employee::ROLE_MANAGER]);
        $mine = Employee::factory()->create([
            'role' => Employee::ROLE_EMPLOYEE,
            'overtime_rate' => null,
        ]);

        ManagerEmployee::create([
            'manager_id' => $manager->id,
            'employee_id' => $mine->id,
            'start_date' => now()->subYear()->toDateString(),
        ]);

        $this->actingAs($manager)
            ->put(route('manager.overtime-rates.update'), [
                'rates' => [
                    ['id' => $mine->id, 'overtime_rate' => 10.5],
                ],
            ])
            ->assertRedirect(route('manager.overtime-rates.index'));

        $this->assertEquals(10.5, (float) $mine->fresh()->overtime_rate);
    }

    public function test_manager_cannot_update_someone_outside_their_team(): void
    {
        $manager = Employee::factory()->create(['role' => Employee::ROLE_MANAGER]);
        $other = Employee::factory()->create(['role' => Employee::ROLE_EMPLOYEE]);

        $this->actingAs($manager)
            ->put(route('manager.overtime-rates.update'), [
                'rates' => [
                    ['id' => $other->id, 'overtime_rate' => 99],
                ],
            ])
            ->assertForbidden();
    }

    public function test_employees_cannot_open_overtime_rates(): void
    {
        $employee = Employee::factory()->create(['role' => Employee::ROLE_EMPLOYEE]);

        $this->actingAs($employee)
            ->get(route('manager.overtime-rates.index'))
            ->assertForbidden();
    }
}
