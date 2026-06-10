<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EnsuresAdminAccess;
use App\Models\Employee;
use App\Services\EmployeeAccessService;
use App\Services\ScheduleAdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminScheduleController extends Controller
{
    use EnsuresAdminAccess;

    public function __construct(
        private ScheduleAdminService $schedules,
        private EmployeeAccessService $access,
    ) {}

    public function index(Request $request): Response
    {
        $this->ensureAdmin($request);

        $empleados = $this->access->exportableEmployees($request->user());
        $selectedId = $request->query('employee_id', $empleados->first()?->id);
        $selected = $selectedId ? Employee::find($selectedId) : null;

        return Inertia::render('Admin/Schedules/Index', [
            'empleados' => $empleados,
            'empleadoSeleccionado' => $selected ? [
                'id' => $selected->id,
                'name' => $selected->name,
                'employee_code' => $selected->employee_code,
            ] : null,
            'horario' => $selected ? $this->schedules->weeklySchedule($selected) : [],
        ]);
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'days' => ['required', 'array'],
            'days.*.weekday' => ['required', 'integer', 'min:0', 'max:6'],
            'days.*.active' => ['required', 'boolean'],
            'days.*.start_time' => ['required', 'string'],
            'days.*.end_time' => ['required', 'string'],
        ]);

        $this->schedules->updateWeeklySchedule(
            $employee,
            $data['days'],
            $request->user(),
            $request,
        );

        return redirect()
            ->route('admin.schedules.index', ['employee_id' => $employee->id])
            ->with('success', "Horario de {$employee->name} actualizado.");
    }
}
