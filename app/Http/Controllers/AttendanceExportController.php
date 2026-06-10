<?php

namespace App\Http\Controllers;

use App\Exports\EmployeeAttendanceExport;
use App\Models\Employee;
use App\Services\AttendanceExportService;
use App\Services\EmployeeAccessService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AttendanceExportController extends Controller
{
    public function __construct(
        private AttendanceExportService $exportService,
        private EmployeeAccessService $accessService,
    ) {}

    public function download(Request $request, string $format): BinaryFileResponse|Response|\Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'employee_id' => ['nullable', 'string', 'exists:employees,id'],
        ]);

        $month = isset($validated['month'])
            ? Carbon::createFromFormat('Y-m', $validated['month'])->startOfMonth()
            : now()->startOfMonth();

        $target = isset($validated['employee_id'])
            ? Employee::findOrFail($validated['employee_id'])
            : $request->user();

        $this->accessService->authorizeExport($request->user(), $target);

        $report = $this->exportService->monthlyReport($target, $month);
        $slug = str($report['periodo']['mes'])->replace('-', '_');
        $nameSlug = str($report['empleado']['nombre'])->slug('_');
        $baseName = 'registro_horario_'.$nameSlug.'_'.$slug;

        return match ($format) {
            'excel' => Excel::download(
                new EmployeeAttendanceExport($report),
                $baseName.'.xlsx'
            ),
            'pdf' => Pdf::loadView('exports.attendance-report', ['report' => $report])
                ->download($baseName.'.pdf'),
            'json' => response()->json($report, 200, [
                'Content-Disposition' => 'attachment; filename="'.$baseName.'.json"',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            default => abort(404),
        };
    }
}
