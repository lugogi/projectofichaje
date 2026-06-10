<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeDaySchedule;
use App\Models\ManagerEmployee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmployeeAdminService
{
    public function __construct(private AuditLogService $audit) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listForAdmin(): array
    {
        $employees = Employee::query()
            ->with(['company:id,name', 'workCalendar:id,name'])
            ->orderBy('name')
            ->get();

        $managerMap = $this->activeManagerMap();

        return $employees->map(fn (Employee $e) => $this->formatEmployee($e, $managerMap[$e->id] ?? null))->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function formatEmployee(Employee $employee, ?Employee $manager = null): array
    {
        return [
            'id' => $employee->id,
            'name' => $employee->name,
            'email' => $employee->email,
            'employee_code' => $employee->employee_code,
            'role' => $employee->role,
            'role_label' => $this->roleLabel($employee->role),
            'employment_status' => (int) $employee->employment_status,
            'activo' => $employee->deleted_at === null && (int) $employee->employment_status === 1,
            'hire_date' => $employee->hire_date?->toDateString(),
            'termination_date' => $employee->termination_date?->toDateString(),
            'company_id' => $employee->company_id,
            'company_name' => $employee->company?->name,
            'work_calendar_id' => $employee->work_calendar_id,
            'work_calendar_name' => $employee->workCalendar?->name,
            'manager_id' => $manager?->id,
            'manager_name' => $manager?->name,
        ];
    }

    public function roleLabel(string $role): string
    {
        return match ($role) {
            Employee::ROLE_ADMIN => 'Administración',
            Employee::ROLE_MANAGER => 'Encargado',
            Employee::ROLE_EMPLOYEE => 'Empleado',
            default => $role,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, Employee $actor, ?Request $request = null): Employee
    {
        $this->validateUnique($data);

        return DB::transaction(function () use ($data, $actor, $request) {
            $employee = Employee::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password_hash' => bcrypt($data['password']),
                'role' => $data['role'],
                'employee_code' => $data['employee_code'],
                'employment_status' => 1,
                'hire_date' => $data['hire_date'] ?? now()->toDateString(),
                'company_id' => $data['company_id'],
                'work_calendar_id' => $data['work_calendar_id'],
            ]);

            $this->seedDefaultSchedule($employee->id);
            $this->syncManager($employee, $data['manager_id'] ?? null);

            $this->audit->log(
                $actor,
                'employee.created',
                'employee',
                $employee->id,
                [
                    'name' => $employee->name,
                    'email' => $employee->email,
                    'role' => $employee->role,
                ],
                null,
                $request,
            );

            return $employee;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Employee $employee, array $data, Employee $actor, ?Request $request = null): Employee
    {
        $this->validateUnique($data, $employee->id);

        $before = [
            'name' => $employee->name,
            'email' => $employee->email,
            'role' => $employee->role,
            'employee_code' => $employee->employee_code,
        ];

        DB::transaction(function () use ($employee, $data, $actor, $request, $before) {
            $employee->update([
                'name' => $data['name'],
                'email' => $data['email'],
                'role' => $data['role'],
                'employee_code' => $data['employee_code'],
                'hire_date' => $data['hire_date'] ?? $employee->hire_date,
                'company_id' => $data['company_id'],
                'work_calendar_id' => $data['work_calendar_id'],
                'employment_status' => $data['employment_status'] ?? $employee->employment_status,
            ]);

            if (! empty($data['password'])) {
                $employee->update(['password_hash' => bcrypt($data['password'])]);
            }

            $this->syncManager($employee, $data['manager_id'] ?? null);

            $this->audit->log(
                $actor,
                'employee.updated',
                'employee',
                $employee->id,
                ['before' => $before, 'after' => [
                    'name' => $employee->name,
                    'email' => $employee->email,
                    'role' => $employee->role,
                    'employee_code' => $employee->employee_code,
                ]],
                null,
                $request,
            );
        });

        return $employee->fresh(['company', 'workCalendar']);
    }

    public function deactivate(Employee $employee, Employee $actor, ?Request $request = null): void
    {
        if ($employee->id === $actor->id) {
            throw ValidationException::withMessages([
                'employee' => 'No puedes darte de baja a ti mismo.',
            ]);
        }

        DB::transaction(function () use ($employee, $actor, $request) {
            $employee->update([
                'employment_status' => 0,
                'termination_date' => now()->toDateString(),
            ]);
            $employee->delete();

            $this->audit->log(
                $actor,
                'employee.deactivated',
                'employee',
                $employee->id,
                ['name' => $employee->name, 'email' => $employee->email],
                null,
                $request,
            );
        });
    }

    /**
     * @return array<int, string>
     */
    private function activeManagerMap(): array
    {
        $relations = ManagerEmployee::query()
            ->active()
            ->with('manager:id,name')
            ->get();

        $map = [];
        foreach ($relations as $relation) {
            $map[$relation->employee_id] = $relation->manager;
        }

        return $map;
    }

    private function syncManager(Employee $employee, ?string $managerId): void
    {
        if ($employee->role !== Employee::ROLE_EMPLOYEE) {
            ManagerEmployee::query()
                ->where('employee_id', $employee->id)
                ->whereNull('end_date')
                ->update(['end_date' => today()->toDateString()]);

            return;
        }

        $current = ManagerEmployee::query()
            ->where('employee_id', $employee->id)
            ->whereNull('end_date')
            ->first();

        if ($current && $current->manager_id === $managerId) {
            return;
        }

        if ($current) {
            $current->update(['end_date' => today()->toDateString()]);
        }

        if ($managerId) {
            ManagerEmployee::create([
                'id' => (string) Str::ulid(),
                'employee_id' => $employee->id,
                'manager_id' => $managerId,
                'start_date' => today()->toDateString(),
                'end_date' => null,
            ]);
        }
    }

    private function seedDefaultSchedule(string $employeeId): void
    {
        foreach ([1, 2, 3, 4, 5] as $weekday) {
            EmployeeDaySchedule::create([
                'id' => (string) Str::ulid(),
                'employee_id' => $employeeId,
                'weekday' => $weekday,
                'start_time' => '08:00:00',
                'end_time' => '17:00:00',
                'active' => true,
                'start_date' => today()->toDateString(),
                'end_date' => '2099-12-31',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateUnique(array $data, ?string $exceptId = null): void
    {
        $emailQuery = Employee::withTrashed()->where('email', $data['email']);
        $codeQuery = Employee::withTrashed()->where('employee_code', $data['employee_code']);

        if ($exceptId) {
            $emailQuery->where('id', '!=', $exceptId);
            $codeQuery->where('id', '!=', $exceptId);
        }

        if ($emailQuery->exists()) {
            throw ValidationException::withMessages(['email' => 'Este correo ya está registrado.']);
        }

        if ($codeQuery->exists()) {
            throw ValidationException::withMessages(['employee_code' => 'Este código de empleado ya existe.']);
        }
    }
}
