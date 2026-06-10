<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EnsuresAdminAccess;
use App\Models\ClockZone;
use App\Models\Employee;
use App\Models\TimeRecord;
use App\Services\AttendanceService;
use App\Services\EmployeeAccessService;
use App\Services\ManualAttendanceService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminManualClockController extends Controller
{
    use EnsuresAdminAccess;

    public function __construct(
        private ManualAttendanceService $manualClock,
        private EmployeeAccessService $access,
        private AttendanceService $attendance,
    ) {}

    public function index(Request $request): Response
    {
        $this->ensureAdmin($request);

        $empleados = $this->access->exportableEmployees($request->user());
        $selectedId = $request->query('employee_id', $empleados->first()?->id);
        $selected = $selectedId
            ? Employee::find($selectedId)
            : null;

        return Inertia::render('Admin/ManualClock/Index', [
            'empleados' => $empleados,
            'empleadoSeleccionado' => $selected ? [
                'id' => $selected->id,
                'name' => $selected->name,
                'employee_code' => $selected->employee_code,
                'proxima_accion' => $this->attendance->nextAction($selected),
                'estado' => $this->attendance->openSession($selected) ? 'trabajando' : 'fuera',
                'registros_hoy' => $this->attendance->todayRecords($selected),
            ] : null,
            'salas' => ClockZone::query()->where('active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'employee_id' => ['required', 'string'],
            'type' => ['required', 'in:1,2'],
            'recorded_at' => ['required', 'date'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'clock_zone_id' => ['nullable', 'string'],
        ]);

        $employee = Employee::findOrFail($data['employee_id']);
        abort_unless(
            $this->access->canReviewFor($request->user(), $employee),
            403,
        );
        $recordedAt = Carbon::parse($data['recorded_at']);

        $this->manualClock->register(
            $employee,
            $request->user(),
            (int) $data['type'],
            $recordedAt,
            $data['reason'],
            $data['clock_zone_id'] ?? null,
            $request,
        );

        $label = (int) $data['type'] === TimeRecord::TYPE_CLOCK_IN ? 'Entrada' : 'Salida';

        return redirect()
            ->route('admin.manual-clock.index', ['employee_id' => $employee->id])
            ->with('success', "{$label} manual registrada para {$employee->name}.");
    }
}
