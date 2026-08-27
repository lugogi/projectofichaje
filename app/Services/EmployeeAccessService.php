<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\ManagerEmployee;
use Illuminate\Support\Collection;

class EmployeeAccessService
{
    public function canExportFor(Employee $actor, Employee $target): bool
    {
        if ($actor->id === $target->id) {
            return true;
        }

        if ($actor->isAdmin()) {
            return true;
        }

        if ($actor->isManager()) {
            return $this->isManagedBy($target, $actor);
        }

        return false;
    }

    public function authorizeExport(Employee $actor, Employee $target): void
    {
        if (! $this->canExportFor($actor, $target)) {
            abort(403, 'No tienes permiso para exportar los registros de este empleado.');
        }
    }

    /**
     * Empleados cuyos registros puede exportar el usuario actual.
     *
     * @param  array{department?: string|null, position?: string|null, role?: string|null, employee_ids?: array<int, string>|null}  $filters
     * @return Collection<int, Employee>
     */
    public function exportableEmployees(Employee $actor, array $filters = []): Collection
    {
        $query = $this->exportableQuery($actor);

        if ($query === null) {
            return collect();
        }

        if (! empty($filters['department'])) {
            $query->where('department', $filters['department']);
        }

        if (! empty($filters['position'])) {
            $query->where('position', $filters['position']);
        }

        if (! empty($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        if (! empty($filters['employee_ids'])) {
            $query->whereIn('id', $filters['employee_ids']);
        }

        return $query
            ->orderBy('department')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'employee_code', 'role', 'position', 'department', 'overtime_rate']);
    }

    /**
     * Valores disponibles para filtrar, limitados a lo que el actor puede ver.
     *
     * @return array{departments: array<int, string>, positions: array<int, string>}
     */
    public function exportableFilterOptions(Employee $actor): array
    {
        $employees = $this->exportableEmployees($actor);

        return [
            'departments' => $employees->pluck('department')->filter()->unique()->sort()->values()->all(),
            'positions' => $employees->pluck('position')->filter()->unique()->sort()->values()->all(),
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Employee>|null
     */
    private function exportableQuery(Employee $actor)
    {
        if ($actor->isAdmin()) {
            return Employee::query()->where('id', '!=', $actor->id);
        }

        if ($actor->isManager()) {
            $ids = ManagerEmployee::query()
                ->active()
                ->where('manager_id', $actor->id)
                ->pluck('employee_id');

            return Employee::query()->whereIn('id', $ids);
        }

        return null;
    }

    public function canManageTeamExports(Employee $actor): bool
    {
        return $actor->isAdmin() || $actor->isManager();
    }

    public function canAccessAdminPanel(Employee $actor): bool
    {
        return $actor->isAdmin();
    }

    public function canAccessManagerPanel(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function canReviewFor(Employee $actor, Employee $target): bool
    {
        return $this->canExportFor($actor, $target) && $actor->id !== $target->id;
    }

    public function authorizeReview(Employee $actor, Employee $target): void
    {
        if (! $this->canReviewFor($actor, $target)) {
            abort(403, 'No tienes permiso para revisar las solicitudes de este empleado.');
        }
    }

    private function isManagedBy(Employee $employee, Employee $manager): bool
    {
        return ManagerEmployee::query()
            ->active()
            ->where('manager_id', $manager->id)
            ->where('employee_id', $employee->id)
            ->exists();
    }
}
