<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EnsuresAdminAccess;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminAuditLogController extends Controller
{
    use EnsuresAdminAccess;

    public function __construct(private AuditLogService $audit) {}

    public function index(Request $request): Response
    {
        $this->ensureAdmin($request);

        $page = max(1, (int) $request->query('page', 1));
        $action = $request->query('action');

        $result = $this->audit->paginated($page, 30, $action);

        return Inertia::render('Admin/AuditLog/Index', [
            'registros' => $result['items'],
            'total' => $result['total'],
            'pagina' => $page,
            'filtroAccion' => $action,
            'acciones' => $this->audit->actionFilters(),
        ]);
    }
}
