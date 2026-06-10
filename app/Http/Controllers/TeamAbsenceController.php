<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesRolePanel;
use App\Models\AbsenceRequest;
use App\Models\Employee;
use App\Services\EmployeeAccessService;
use App\Services\ManualAbsenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamAbsenceController extends Controller
{
    use ResolvesRolePanel;

    public function __construct(
        private ManualAbsenceService $absences,
        private EmployeeAccessService $access,
    ) {}

    public function index(Request $request): Response
    {
        $panel = $this->resolvePanel($request);
        $actor = $request->user();

        $empleados = $this->access->exportableEmployees($actor)->map(fn ($e) => [
            'id' => $e->id,
            'name' => $e->name,
            'employee_code' => $e->employee_code,
        ])->values();

        return Inertia::render('Team/AbsencesIndex', [
            'empleados' => $empleados,
            'ausencias' => $this->absences->listForReviewer($actor),
            'esAdmin' => $actor->isAdmin(),
            'homeRoute' => $panel['home_route'],
            'storeRoute' => str_starts_with((string) $request->route()->getName(), 'admin.')
                ? 'admin.absences.store'
                : 'manager.absences.store',
            'destroyRoute' => str_starts_with((string) $request->route()->getName(), 'admin.')
                ? 'admin.absences.destroy'
                : 'manager.absences.destroy',
            'tipos' => [
                ['value' => AbsenceRequest::TYPE_VACATION, 'label' => 'Vacaciones'],
                ['value' => AbsenceRequest::TYPE_MEDICAL_LEAVE, 'label' => 'Baja médica'],
                ['value' => AbsenceRequest::TYPE_FREE_DAY, 'label' => 'Día libre'],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->resolvePanel($request);

        $data = $request->validate([
            'employee_id' => ['required', 'string'],
            'type' => ['required', 'in:vacation,medical_leave,free_day'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'note' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        $employee = Employee::findOrFail($data['employee_id']);
        $this->absences->register($employee, $request->user(), $data, $request);

        $route = $request->user()->isAdmin()
            ? 'admin.absences.index'
            : 'manager.absences.index';

        return redirect()
            ->route($route)
            ->with('success', "Ausencia registrada para {$employee->name}. Ya aparece en el calendario.");
    }

    public function destroy(Request $request, AbsenceRequest $absenceRequest): RedirectResponse
    {
        $this->resolvePanel($request);

        $this->absences->cancel($absenceRequest, $request->user(), $request);

        $route = $request->user()->isAdmin()
            ? 'admin.absences.index'
            : 'manager.absences.index';

        return redirect()
            ->route($route)
            ->with('success', 'Ausencia anulada correctamente.');
    }
}
