<?php

namespace App\Http\Controllers;

use App\Services\EmployeeAccessService;
use App\Services\SolicitudesReviewService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ManagerPanelController extends Controller
{
    public function __construct(
        private EmployeeAccessService $access,
        private SolicitudesReviewService $reviews,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->isManager(), 403);

        $actor = $request->user();

        return Inertia::render('Manager/Index', [
            'pendientes' => $this->reviews->pendingCount($actor),
            'empleadosEquipo' => $this->access->exportableEmployees($actor)->count(),
        ]);
    }
}
