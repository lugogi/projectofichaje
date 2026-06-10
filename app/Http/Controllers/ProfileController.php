<?php

namespace App\Http\Controllers;

use App\Services\AttendanceExportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __construct(private AttendanceExportService $exportService) {}

    /**
     * Muestra la página de perfil del empleado.
     */
    public function edit(Request $request): Response
    {
        $usuario = $request->user()->load(['company', 'workCalendar']);
        $resumenHoras = $this->exportService->monthlyReport(
            $request->user(),
            null,
            $request->user()->isEmployee(),
        );

        return Inertia::render('Profile/Edit', [
            'status' => session('status'),
            'usuario' => [
                'nombre'              => $usuario->name,
                'email'                => $usuario->email,
                'codigo_empleado'      => $usuario->employee_code,
                'rol'                  => $usuario->role,
                'fecha_contratacion'   => $usuario->hire_date?->format('d/m/Y'),
                'centro_trabajo'       => $usuario->company?->name,
                'calendario_laboral'   => $usuario->workCalendar?->name,
            ],
            'resumenHoras' => [
                'periodo' => $resumenHoras['periodo'],
                'contrato' => $resumenHoras['contrato'],
                'perfil' => $resumenHoras['perfil'],
                'exportacion' => [
                    'formato_incluido' => $resumenHoras['exportacion']['formato_incluido'],
                    'formato_omitido' => $resumenHoras['exportacion']['formato_omitido'],
                    'tope_aplicado' => $resumenHoras['exportacion']['tope_aplicado'],
                    'nota_legal' => $resumenHoras['exportacion']['nota_legal'],
                ],
            ],
        ]);
    }
}
