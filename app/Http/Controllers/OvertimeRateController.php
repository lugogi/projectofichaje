<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesRolePanel;
use App\Models\Employee;
use App\Services\EmployeeAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El encargado fija la tarifa de horas extra de su equipo sin entrar
 * en el alta completa (roles, contraseñas, etc.).
 */
class OvertimeRateController extends Controller
{
    use ResolvesRolePanel;

    public function __construct(private EmployeeAccessService $access) {}

    public function index(Request $request): Response
    {
        $panel = $this->resolvePanel($request);
        $actor = $request->user();

        $empleados = $this->access->exportableEmployees($actor)->map(fn (Employee $e) => [
            'id' => $e->id,
            'nombre' => $e->name,
            'codigo' => $e->employee_code,
            'puesto' => $e->position,
            'departamento' => $e->department,
            'overtime_rate' => $e->overtime_rate !== null ? (float) $e->overtime_rate : null,
        ])->values();

        return Inertia::render('Team/OvertimeRates', [
            'empleados' => $empleados,
            'esAdmin' => $actor->isAdmin(),
            'homeRoute' => $panel['home_route'],
            'saveUrl' => $actor->isAdmin()
                ? route('admin.overtime-rates.update')
                : route('manager.overtime-rates.update'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->resolvePanel($request);

        $validated = $request->validate([
            'rates' => ['required', 'array'],
            'rates.*.id' => ['required', 'string', 'size:26'],
            'rates.*.overtime_rate' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
        ]);

        $actor = $request->user();

        foreach ($validated['rates'] as $row) {
            $employee = Employee::findOrFail($row['id']);
            $this->access->authorizeReview($actor, $employee);
            $employee->overtime_rate = $row['overtime_rate'] === '' || $row['overtime_rate'] === null
                ? null
                : $row['overtime_rate'];
            $employee->save();
        }

        $home = $actor->isAdmin() ? 'admin.overtime-rates.index' : 'manager.overtime-rates.index';

        return redirect()->route($home)->with('success', 'Tarifas de horas extra actualizadas.');
    }
}
