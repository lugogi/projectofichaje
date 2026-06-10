<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EnsuresAdminAccess;
use App\Services\TeamReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminReportController extends Controller
{
    use EnsuresAdminAccess;

    public function __construct(private TeamReportService $reports) {}

    public function index(Request $request): Response
    {
        $this->ensureAdmin($request);

        $month = $request->query('month', now()->format('Y-m'));
        $monthDate = Carbon::createFromFormat('Y-m', $month) ?: now();

        return Inertia::render('Admin/Reports/Index', [
            'mesActual' => $monthDate->format('Y-m'),
            'mesLabel' => $monthDate->locale('es')->isoFormat('MMMM YYYY'),
            'estadisticas' => $this->reports->dashboardStats(),
            'informe' => $this->reports->monthlyTeamReport($monthDate),
        ]);
    }
}
