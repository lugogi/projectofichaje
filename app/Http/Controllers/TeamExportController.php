<?php

namespace App\Http\Controllers;

use App\Exports\TeamAttendanceExport;
use App\Http\Controllers\Concerns\ResolvesRolePanel;
use App\Mail\TeamAttendanceForLaboralMail;
use App\Services\AttendanceExportService;
use App\Services\EmployeeAccessService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TeamExportController extends Controller
{
    use ResolvesRolePanel;

    public function __construct(
        private EmployeeAccessService $access,
        private AttendanceExportService $exportService,
    ) {}

    /**
     * Pantalla para que admin/encargado elija trabajadores y descargue registros.
     */
    public function index(Request $request): InertiaResponse
    {
        $panel = $this->resolvePanel($request);
        $actor = $request->user();

        $empleados = $this->access->exportableEmployees($actor)->map(fn ($e) => [
            'id' => $e->id,
            'nombre' => $e->name,
            'email' => $e->email,
            'codigo' => $e->employee_code,
            'rol' => $e->role,
            'puesto' => $e->position,
            'departamento' => $e->department,
        ])->values();

        $prefix = $actor->isAdmin() ? 'admin' : 'manager';

        return Inertia::render('Team/ExportIndex', [
            'empleados' => $empleados,
            'filtros' => $this->access->exportableFilterOptions($actor),
            'mesActual' => now()->format('Y-m'),
            'esAdmin' => $actor->isAdmin(),
            'homeRoute' => $panel['home_route'],
            'exportTeamUrl' => route($prefix.'.exports.team'),
            'enviarLaboralUrl' => route($prefix.'.exports.laboral'),
            'laboralConfigurado' => $this->laboralConfigured(),
            'laboralNombre' => config('laboral.name'),
        ]);
    }

    /**
     * Exporta varios trabajadores en Excel, PDF o JSON.
     */
    public function exportTeam(Request $request): BinaryFileResponse|Response|JsonResponse
    {
        $validated = $this->validatedExport($request);
        $bundle = $this->buildTeamExport($request, $validated);
        $format = $validated['format'] ?? 'excel';
        $baseName = $this->fileBaseName($bundle['month'], $validated);

        return match ($format) {
            'excel' => Excel::download(
                new TeamAttendanceExport($bundle['reports'], $bundle['context']),
                $baseName.'.xlsx',
            ),
            'pdf' => Pdf::loadView('exports.team-attendance-report', [
                'reports' => $bundle['reports'],
                'context' => $bundle['context'],
                'totales' => $bundle['totales'],
                'ausencias' => $bundle['context']['ausencias'] ?? [],
            ])->setPaper('a4', 'landscape')->download($baseName.'.pdf'),
            'json' => response()->json([
                'mes' => $bundle['context']['mes_label'],
                'periodo' => $bundle['month']->format('Y-m'),
                'filtros' => $bundle['context']['filtros'],
                'generado' => now()->toIso8601String(),
                'totales' => $bundle['totales'],
                'vacaciones_y_bajas' => $bundle['context']['ausencias'] ?? [],
                'trabajadores' => $bundle['reports'],
            ], 200, [
                'Content-Disposition' => 'attachment; filename="'.$baseName.'.json"',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            default => abort(404),
        };
    }

    /**
     * Envía el informe de plantilla a la asesoría laboral. Si aún no hay
     * correo configurado, el botón existe igual y se explica por qué no sale.
     */
    public function sendToLaboral(Request $request): JsonResponse
    {
        $validated = $this->validatedExport($request);
        $bundle = $this->buildTeamExport($request, $validated);

        if (! $this->laboralConfigured()) {
            return response()->json([
                'ok' => false,
                'configurado' => false,
                'message' => 'El envío a laboral aún no está configurado. Cuando tengáis el correo de la asesoría, se podrá mandar el informe desde aquí.',
            ]);
        }

        $fileName = $this->fileBaseName($bundle['month'], $validated).'.xlsx';
        $excel = Excel::raw(
            new TeamAttendanceExport($bundle['reports'], $bundle['context']),
            \Maatwebsite\Excel\Excel::XLSX,
        );

        Mail::to(config('laboral.email'))->send(new TeamAttendanceForLaboralMail(
            context: $bundle['context'],
            excelBinary: $excel,
            fileName: $fileName,
            destinatario: (string) config('laboral.name'),
            remitente: $request->user()->name,
        ));

        return response()->json([
            'ok' => true,
            'configurado' => true,
            'message' => 'Informe enviado a '.(config('laboral.name') ?: 'laboral').' ('.config('laboral.email').').',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedExport(Request $request): array
    {
        return $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'format' => ['nullable', 'in:excel,pdf,json'],
            'department' => ['nullable', 'string', 'max:120'],
            'position' => ['nullable', 'string', 'max:120'],
            'employee_ids' => ['nullable', 'array'],
            'employee_ids.*' => ['string', 'size:26'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{month: Carbon, reports: array<int, array<string, mixed>>, context: array<string, mixed>, totales: array<string, mixed>}
     */
    private function buildTeamExport(Request $request, array $validated): array
    {
        $month = Carbon::createFromFormat('Y-m', $validated['month'])->startOfMonth();
        $actor = $request->user();

        $empleados = $this->access->exportableEmployees($actor, [
            'department' => $validated['department'] ?? null,
            'position' => $validated['position'] ?? null,
            'employee_ids' => $validated['employee_ids'] ?? null,
        ]);

        if ($empleados->isEmpty()) {
            abort(422, 'No hay trabajadores que cumplan los filtros seleccionados.');
        }

        $reports = $empleados
            ->map(fn ($employee) => $this->exportService->monthlyReport($employee, $month))
            ->values()
            ->all();

        $ausencias = collect($reports)
            ->flatMap(fn (array $report) => $report['ausencias'] ?? [])
            ->sortBy([
                ['tipo_label', 'asc'],
                ['nombre', 'asc'],
                ['desde', 'asc'],
            ])
            ->values()
            ->all();

        $context = [
            'mes_label' => $month->locale('es')->isoFormat('MMMM YYYY'),
            'filtros' => $this->describeFilters($validated),
            'ausencias' => $ausencias,
        ];

        return [
            'month' => $month,
            'reports' => $reports,
            'context' => $context,
            'totales' => $this->totals($reports),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $reports
     * @return array<string, mixed>
     */
    private function totals(array $reports): array
    {
        $importe = 0.0;

        foreach ($reports as $report) {
            $importe += (float) ($report['horas_extra']['importe'] ?? 0);
        }

        return [
            'trabajadores' => count($reports),
            'horas_contrato' => round(array_sum(array_column(array_column($reports, 'contrato'), 'decimal_esperado_mes')), 2),
            'horas_trabajadas' => round(array_sum(array_column(array_column($reports, 'perfil'), 'decimal_fichado_real')), 2),
            'horas_nomina' => round(array_sum(array_column(array_column($reports, 'exportacion'), 'decimal_incluido')), 2),
            'horas_extra' => round(array_sum(array_column(array_column($reports, 'horas_extra'), 'decimal')), 2),
            'importe_horas_extra' => round($importe, 2),
        ];
    }

    private function laboralConfigured(): bool
    {
        return (bool) config('laboral.enabled') && filled(config('laboral.email'));
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function describeFilters(array $validated): string
    {
        $parts = [];

        if (! empty($validated['department'])) {
            $parts[] = 'Departamento: '.$validated['department'];
        }

        if (! empty($validated['position'])) {
            $parts[] = 'Puesto: '.$validated['position'];
        }

        if (! empty($validated['employee_ids'])) {
            $parts[] = 'Selección manual de '.count($validated['employee_ids']).' trabajador(es)';
        }

        return $parts === [] ? 'Toda la plantilla' : implode(' · ', $parts);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function fileBaseName(Carbon $month, array $validated): string
    {
        $scope = 'plantilla';

        if (! empty($validated['department'])) {
            $scope = str($validated['department'])->slug('_')->toString();
        } elseif (! empty($validated['position'])) {
            $scope = str($validated['position'])->slug('_')->toString();
        } elseif (! empty($validated['employee_ids'])) {
            $scope = 'seleccion';
        }

        return 'registros_'.$scope.'_'.$month->format('m_Y');
    }
}
