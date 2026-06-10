<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesRolePanel;
use App\Services\EmployeeAccessService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamExportController extends Controller
{
    use ResolvesRolePanel;

    public function __construct(private EmployeeAccessService $access) {}

    /**
     * Pantalla para que admin/encargado elija trabajador y descargue registros.
     */
    public function index(Request $request): Response
    {
        $panel = $this->resolvePanel($request);
        $actor = $request->user();

        $empleados = $this->access->exportableEmployees($actor)->map(fn ($e) => [
            'id' => $e->id,
            'nombre' => $e->name,
            'email' => $e->email,
            'codigo' => $e->employee_code,
            'rol' => $e->role,
        ])->values();

        return Inertia::render('Team/ExportIndex', [
            'empleados' => $empleados,
            'mesActual' => now()->format('Y-m'),
            'esAdmin' => $actor->isAdmin(),
            'homeRoute' => $panel['home_route'],
        ]);
    }
}
