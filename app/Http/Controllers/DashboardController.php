<?php

namespace App\Http\Controllers;

use App\Services\AttendanceService;
use App\Services\ExpectedWorkHoursService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private AttendanceService $attendance,
        private ExpectedWorkHoursService $expectedHours,
    ) {}

    /**
     * Panel personal del empleado: resumen de horas y actividad reciente.
     */
    public function index(Request $request): Response
    {
        $asistencia = $this->attendance->employeeAttendanceData($request->user());

        return Inertia::render('Dashboard', [
            'asistencia' => $asistencia,
            'objetivoMensual' => $this->expectedHours->monthlySummary(
                $request->user(),
                null,
                $request->user()->isEmployee(),
            ),
            'usuario' => [
                'nombre' => $request->user()->name,
                'rol' => $request->user()->role,
            ],
        ]);
    }
}
