<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EnsuresAdminAccess;
use App\Models\ClockZone;
use App\Models\Company;
use App\Services\ClockZoneAdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminClockZoneController extends Controller
{
    use EnsuresAdminAccess;

    public function __construct(private ClockZoneAdminService $zones) {}

    public function index(Request $request): Response
    {
        $this->ensureAdmin($request);

        $companyId = $request->query('company_id', $this->zones->defaultCompanyId());

        return Inertia::render('Admin/ClockZones/Index', [
            'empresas' => Company::query()->where('active', true)->orderBy('name')->get(['id', 'name']),
            'companyId' => $companyId,
            'salas' => $this->zones->listForCompany($companyId),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'company_id' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'ip' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:50'],
            'active' => ['nullable', 'boolean'],
        ]);

        $this->zones->create($data, $request->user(), $request);

        return redirect()
            ->route('admin.zones.index', ['company_id' => $data['company_id']])
            ->with('success', 'Sala creada correctamente.');
    }

    public function update(Request $request, ClockZone $clockZone): RedirectResponse
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'ip' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:50'],
            'active' => ['nullable', 'boolean'],
        ]);

        $this->zones->update($clockZone, $data, $request->user(), $request);

        return redirect()
            ->route('admin.zones.index', ['company_id' => $clockZone->company_id])
            ->with('success', 'Sala actualizada correctamente.');
    }

    public function destroy(Request $request, ClockZone $clockZone): RedirectResponse
    {
        $this->ensureAdmin($request);

        $companyId = $clockZone->company_id;
        $this->zones->delete($clockZone, $request->user(), $request);

        return redirect()
            ->route('admin.zones.index', ['company_id' => $companyId])
            ->with('success', 'Sala eliminada correctamente.');
    }
}
