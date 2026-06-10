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
     * @return Collection<int, Employee>
     */
    public function exportableEmployees(Employee $actor): Collection
    {
        if ($actor->isAdmin()) {
            return Employee::query()
                ->where('id', '!=', $actor->id)
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'employee_code', 'role']);
        }

        if ($actor->isManager()) {
            $ids = ManagerEmployee::query()
                ->active()
                ->where('manager_id', $actor->id)
                ->pluck('employee_id');

            return Employee::query()
                ->whereIn('id', $ids)
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'employee_code', 'role']);
        }

        return collect();
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
