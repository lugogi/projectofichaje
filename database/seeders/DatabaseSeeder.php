<?php

namespace Database\Seeders;

use App\Models\AbsenceRequest;
use App\Services\AbsenceScheduleService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Ensure Company exists and get its ID safely
        $companyName = 'Salamandra Group SMD';
        $company = DB::table('companies')->where('name', $companyName)->first();

        if (!$company) {
            $companyId = (string) Str::ulid();
            DB::table('companies')->insert([
                'id' => $companyId,
                'name' => $companyName,
                'address' => 'Calle Ficticia 123',
                'province' => 'Madrid',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $companyId = $company->id;
        }

        // 2. Ensure Work Calendar exists for this company
        $calendarName = 'Calendario Estándar';
        $calendar = DB::table('work_calendars')->where('name', $calendarName)
            ->where('company_id', $companyId)
            ->first();

        if (!$calendar) {
            $calendarId = (string) Str::ulid();
            DB::table('work_calendars')->insert([
                'id' => $calendarId,
                'name' => $calendarName,
                'company_id' => $companyId,
                'timezone' => 'Europe/Madrid',
                'created_at' => now(),
            ]);
        } else {
            $calendarId = $calendar->id;
        }

        // 3. Usuarios de prueba (admin + encargado + empleado)
        $this->seedEmployeeWithSchedule([
            'email' => 'admin@fichaje.test',
            'name' => 'Admin Prueba',
            'role' => 'admin',
            'employee_code' => 'ADM-001',
        ], $companyId, $calendarId);

        $managerId = $this->seedEmployeeWithSchedule([
            'email' => 'encargado@fichaje.test',
            'name' => 'Tomy Encargado',
            'role' => 'manager',
            'employee_code' => 'MGR-001',
            'hire_date' => '2025-06-01',
            'notify_all_checkins' => true,
        ], $companyId, $calendarId);

        $empleadoId = $this->seedEmployeeWithSchedule([
            'email' => 'empleado@fichaje.test',
            'name' => 'María López',
            'role' => 'employee',
            'employee_code' => 'EMP-001',
            'hire_date' => '2026-01-15',
        ], $companyId, $calendarId);

        $this->seedManagerEmployee($managerId, $empleadoId);

        // Agregar 4 empleados más
        $empleado2Id = $this->seedEmployeeWithSchedule([
            'email' => 'juan@fichaje.test',
            'name' => 'Juan García',
            'role' => 'employee',
            'employee_code' => 'EMP-002',
            'hire_date' => '2025-06-01',
        ], $companyId, $calendarId);

        $this->seedManagerEmployee($managerId, $empleado2Id);

        $empleado3Id = $this->seedEmployeeWithSchedule([
            'email' => 'carlos@fichaje.test',
            'name' => 'Carlos Martínez',
            'role' => 'employee',
            'employee_code' => 'EMP-003',
            'hire_date' => '2025-09-10',
        ], $companyId, $calendarId);

        $this->seedManagerEmployee($managerId, $empleado3Id);

        $empleado4Id = $this->seedEmployeeWithSchedule([
            'email' => 'ana@fichaje.test',
            'name' => 'Ana Fernández',
            'role' => 'employee',
            'employee_code' => 'EMP-004',
            'hire_date' => '2026-02-20',
        ], $companyId, $calendarId);

        $this->seedManagerEmployee($managerId, $empleado4Id);

        $empleado5Id = $this->seedEmployeeWithSchedule([
            'email' => 'laura@fichaje.test',
            'name' => 'Laura Ruiz',
            'role' => 'employee',
            'employee_code' => 'EMP-005',
            'hire_date' => '2025-11-03',
        ], $companyId, $calendarId);

        $this->seedManagerEmployee($managerId, $empleado5Id);

        // 4. Add holidays to the WorkCalendar
        $holidayData = [
            ['name' => 'Día de la Comunidad', 'date' => '2026-04-25', 'type' => 'public'],
            ['name' => 'Día del Trabajador', 'date' => '2026-05-01', 'type' => 'public'],
        ];

        foreach ($holidayData as $h) {
            $holiday = DB::table('holidays')->where('work_calendar_id', $calendarId)
                ->where('date', $h['date'])
                ->first();

            if (!$holiday) {
                DB::table('holidays')->insert([
                    'id' => (string) Str::ulid(),
                    'work_calendar_id' => $calendarId,
                    'date' => $h['date'],
                    'name' => $h['name'],
                    'type' => $h['type'],
                    'mandatory' => true,
                    'created_at' => now(),
                ]);
            } else {
                DB::table('holidays')->where('id', $holiday->id)->update([
                    'name' => $h['name'],
                    'type' => $h['type'],
                    'work_calendar_id' => $calendarId,
                ]);
            }
        }

        AbsenceRequest::query()
            ->where('status', AbsenceRequest::STATUS_APPROVED)
            ->each(fn (AbsenceRequest $absence) => app(AbsenceScheduleService::class)->syncApproved($absence));
    }

    /**
     * Crea o actualiza un empleado de prueba con horario lun–vie.
     *
     * @param  array{name: string, email: string, role: string, employee_code: string, hire_date?: string}  $data
     */
    private function seedManagerEmployee(string $managerId, string $employeeId): void
    {
        $exists = DB::table('manager_employees')
            ->where('manager_id', $managerId)
            ->where('employee_id', $employeeId)
            ->whereNull('end_date')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('manager_employees')->insert([
            'id' => (string) Str::ulid(),
            'manager_id' => $managerId,
            'employee_id' => $employeeId,
            'start_date' => '2026-01-01',
            'end_date' => null,
            'created_at' => now(),
        ]);
    }

    private function seedEmployeeWithSchedule(array $data, string $companyId, string $calendarId): string
    {
        $employee = DB::table('employees')->where('email', $data['email'])->first();

        if (! $employee) {
            $employeeId = (string) Str::ulid();
            DB::table('employees')->insert([
                'id' => $employeeId,
                'name' => $data['name'],
                'email' => $data['email'],
                'password_hash' => bcrypt('password'),
                'role' => $data['role'],
                'notify_all_checkins' => $data['notify_all_checkins'] ?? false,
                'employee_code' => $data['employee_code'],
                'employment_status' => 1,
                'hire_date' => $data['hire_date'] ?? '2026-01-01',
                'company_id' => $companyId,
                'work_calendar_id' => $calendarId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $employeeId = $employee->id;
            DB::table('employees')->where('id', $employeeId)->update([
                'name' => $data['name'],
                'role' => $data['role'],
                'notify_all_checkins' => $data['notify_all_checkins'] ?? false,
                'employee_code' => $data['employee_code'],
                'employment_status' => 1,
                'hire_date' => $data['hire_date'] ?? '2026-01-01',
                'company_id' => $companyId,
                'work_calendar_id' => $calendarId,
                'updated_at' => now(),
            ]);
        }

        DB::table('employee_day_schedule')->where('employee_id', $employeeId)->delete();
        DB::table('schedule_exceptions')->where('employee_id', $employeeId)->delete();

        foreach ([1, 2, 3, 4, 5] as $weekday) {
            DB::table('employee_day_schedule')->insert([
                'id' => (string) Str::ulid(),
                'employee_id' => $employeeId,
                'weekday' => $weekday,
                'start_time' => '08:00:00',
                'end_time' => '17:00:00',
                'active' => true,
                'start_date' => '2026-01-01',
                'end_date' => '2099-12-31',
                'created_at' => now(),
            ]);
        }

        return $employeeId;
    }
}
