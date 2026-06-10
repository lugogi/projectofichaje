<?php

namespace App\Services;

use App\Models\ClockZone;
use App\Models\Company;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ClockZoneAdminService
{
    public function __construct(private AuditLogService $audit) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listForCompany(?string $companyId): array
    {
        if (! $companyId) {
            return [];
        }

        return ClockZone::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get()
            ->map(fn (ClockZone $zone) => $this->format($zone))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function format(ClockZone $zone): array
    {
        return [
            'id' => $zone->id,
            'name' => $zone->name,
            'ip' => $zone->ip,
            'type' => $zone->type ?? 'sala',
            'active' => (bool) $zone->active,
            'company_id' => $zone->company_id,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, Employee $actor, ?Request $request = null): ClockZone
    {
        $this->validateIp($data['ip'] ?? '');

        $zone = ClockZone::create([
            'company_id' => $data['company_id'],
            'name' => $data['name'],
            'ip' => $data['ip'],
            'type' => $data['type'] ?? 'sala',
            'active' => $data['active'] ?? true,
        ]);

        $this->audit->log(
            $actor,
            'clock_zone.created',
            'clock_zone',
            $zone->id,
            $this->format($zone),
            null,
            $request,
        );

        return $zone;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ClockZone $zone, array $data, Employee $actor, ?Request $request = null): ClockZone
    {
        $this->validateIp($data['ip'] ?? '');

        $before = $this->format($zone);

        $zone->update([
            'name' => $data['name'],
            'ip' => $data['ip'],
            'type' => $data['type'] ?? 'sala',
            'active' => $data['active'] ?? true,
        ]);

        $this->audit->log(
            $actor,
            'clock_zone.updated',
            'clock_zone',
            $zone->id,
            ['before' => $before, 'after' => $this->format($zone)],
            null,
            $request,
        );

        return $zone;
    }

    public function delete(ClockZone $zone, Employee $actor, ?Request $request = null): void
    {
        $snapshot = $this->format($zone);
        $zone->delete();

        $this->audit->log(
            $actor,
            'clock_zone.deleted',
            'clock_zone',
            $zone->id,
            $snapshot,
            null,
            $request,
        );
    }

    public function defaultCompanyId(): ?string
    {
        return Company::query()->where('active', true)->value('id');
    }

    private function validateIp(string $ip): void
    {
        if ($ip === '') {
            throw ValidationException::withMessages(['ip' => 'Debes indicar una IP o rango CIDR.']);
        }

        if (! str_contains($ip, '/')) {
            if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
                throw ValidationException::withMessages(['ip' => 'La IP no tiene un formato válido.']);
            }

            return;
        }

        [$subnet, $bits] = explode('/', $ip, 2);
        if (filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            throw ValidationException::withMessages(['ip' => 'El rango CIDR debe ser IPv4.']);
        }

        $bits = (int) $bits;
        if ($bits < 0 || $bits > 32) {
            throw ValidationException::withMessages(['ip' => 'La máscara CIDR debe estar entre 0 y 32.']);
        }
    }
}
