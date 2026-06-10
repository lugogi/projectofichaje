<?php

namespace App\Http\Controllers;

use App\Services\AttendanceService;
use App\Services\ClockNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FichajeController extends Controller
{
    public function __construct(
        private AttendanceService $attendance,
        private ClockNotificationService $clockNotifications,
    ) {}

    /**
     * Pantalla de fichaje con estado, resumen y registro detallado.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Fichaje/Index', [
            'asistencia' => $this->attendance->employeeAttendanceData($request->user()),
        ]);
    }

    /**
     * Procesa el fichaje (cuando el empleado pulsa el botón).
     */
    public function store(Request $request): RedirectResponse
    {
        $employee = $request->user();
        $record = $this->attendance->clock($employee, [
            'ip'         => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $this->clockNotifications->notifyReviewers($record, $employee);

        $mensaje = $record->type === 1
            ? 'Entrada registrada correctamente a las '.$record->recorded_at->format('H:i')
            : 'Salida registrada correctamente a las '.$record->recorded_at->format('H:i');

        return redirect()
            ->route('fichaje.index')
            ->with('success', $mensaje);
    }
}
