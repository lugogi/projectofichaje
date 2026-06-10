<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EnsuresAdminAccess;
use App\Models\Company;
use App\Models\Employee;
use App\Models\ManagerEmployee;
use App\Models\WorkCalendar;
use App\Services\EmployeeAdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminEmployeeController extends Controller
{
    use EnsuresAdminAccess;

    public function __construct(private EmployeeAdminService $employees) {}

    public function index(Request $request): Response
    {
        $this->ensureAdmin($request);

        return Inertia::render('Admin/Employees/Index', [
            'empleados' => $this->employees->listForAdmin(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->ensureAdmin($request);

        return Inertia::render('Admin/Employees/Form', [
            'empleado' => null,
            ...$this->formOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureAdmin($request);

        $data = $this->validated($request, true);
        $this->employees->create($data, $request->user(), $request);

        return redirect()
            ->route('admin.employees.index')
            ->with('success', 'Empleado creado correctamente.');
    }

    public function edit(Request $request, Employee $employee): Response
    {
        $this->ensureAdmin($request);

        $employee->load(['company', 'workCalendar']);
        $manager = ManagerEmployee::query()
            ->active()
            ->where('employee_id', $employee->id)
            ->with('manager:id,name')
            ->first()
            ?->manager;

        return Inertia::render('Admin/Employees/Form', [
            'empleado' => $this->employees->formatEmployee($employee, $manager),
            ...$this->formOptions(),
        ]);
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $this->ensureAdmin($request);

        $data = $this->validated($request, false);
        $this->employees->update($employee, $data, $request->user(), $request);

        return redirect()
            ->route('admin.employees.index')
            ->with('success', 'Empleado actualizado correctamente.');
    }

    public function destroy(Request $request, Employee $employee): RedirectResponse
    {
        $this->ensureAdmin($request);

        $this->employees->deactivate($employee, $request->user(), $request);

        return redirect()
            ->route('admin.employees.index')
            ->with('success', 'Empleado dado de baja correctamente.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'empresas' => Company::query()->where('active', true)->orderBy('name')->get(['id', 'name']),
            'calendarios' => WorkCalendar::query()->orderBy('name')->get(['id', 'name', 'company_id']),
            'encargados' => Employee::query()
                ->where('role', Employee::ROLE_MANAGER)
                ->where('employment_status', 1)
                ->whereNull('deleted_at')
                ->orderBy('name')
                ->get(['id', 'name', 'employee_code']),
            'roles' => [
                ['value' => Employee::ROLE_EMPLOYEE, 'label' => 'Empleado'],
                ['value' => Employee::ROLE_MANAGER, 'label' => 'Encargado'],
                ['value' => Employee::ROLE_ADMIN, 'label' => 'Administración'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $creating): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'employee_code' => ['required', 'string', 'max:100'],
            'role' => ['required', 'in:admin,manager,employee'],
            'hire_date' => ['nullable', 'date'],
            'company_id' => ['required', 'string'],
            'work_calendar_id' => ['required', 'string'],
            'manager_id' => ['nullable', 'string'],
            'employment_status' => ['nullable', 'integer', 'in:0,1'],
        ];

        if ($creating) {
            $rules['password'] = ['required', 'string', 'min:8'];
        } else {
            $rules['password'] = ['nullable', 'string', 'min:8'];
        }

        return $request->validate($rules);
    }
}
