<?php

namespace App\Http\Controllers;

use App\Services\EmployeeAccessService;
use App\Services\SolicitudesReviewService;
use App\Services\TeamReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminPanelController extends Controller
{
    public function __construct(
        private EmployeeAccessService $access,
        private SolicitudesReviewService $reviews,
        private TeamReportService $reports,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->isAdmin(), 403);

        $actor = $request->user();

        $stats = $this->reports->dashboardStats();

        return Inertia::render('Admin/Index', [
            'pendientes' => $this->reviews->pendingCount($actor),
            'empleadosEquipo' => $this->access->exportableEmployees($actor)->count(),
            'estadisticas' => $stats,
        ]);
    }
}
