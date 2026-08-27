<?php

namespace Database\Seeders;

use App\Models\AbsenceRequest;
use App\Models\CorrectionRequest;
use App\Models\Employee;
use App\Models\EmployeeApplication;
use App\Models\TimeRecord;
use App\Models\WorkSession;
use App\Services\TimeRecordChainService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Entorno de pruebas: plantilla repartida por departamentos, fichajes de
 * varios meses y solicitudes pendientes de aprobación.
 *
 * Uso:  php artisan db:seed --class=DemoDataSeeder
 *
 * Es idempotente: vuelve a generar los datos de demostración desde cero
 * sin tocar al admin ni al encargado existentes.
 */
class DemoDataSeeder extends Seeder
{
    private const MONTHS_OF_HISTORY = 5;

    /** Último día con fichajes en el entorno de pruebas (incluido). */
    private const HISTORY_UNTIL = '2026-09-01';

    private const DEPARTMENT = 'Mantenimiento';

    /**
     * Toda la plantilla pertenece a Mantenimiento y la gestiona Tomy, para poder
     * sacar un único informe de todos ellos. Los puestos sí varían, que es lo que
     * permite afinar el filtro dentro del departamento.
     *
     * Los cinco primeros son los que ya existían; se respetan sus correos.
     *
     * @var list<array{name: string, email: string, code: string, position: string, hire: string, pattern: string, overtime: float}>
     */
    private const STAFF = [
        ['name' => 'María López', 'email' => 'empleado@fichaje.test', 'code' => 'EMP-001', 'position' => 'Oficial de 1ª', 'hire' => '2024-01-15', 'pattern' => 'puntual', 'overtime' => 12],
        ['name' => 'Juan García', 'email' => 'juan@fichaje.test', 'code' => 'EMP-002', 'position' => 'Oficial de 1ª', 'hire' => '2023-06-01', 'pattern' => 'horas_extra', 'overtime' => 12],
        ['name' => 'Carlos Martínez', 'email' => 'carlos@fichaje.test', 'code' => 'EMP-003', 'position' => 'Oficial de 2ª', 'hire' => '2023-09-10', 'pattern' => 'impuntual', 'overtime' => 11],
        ['name' => 'Ana Fernández', 'email' => 'ana@fichaje.test', 'code' => 'EMP-004', 'position' => 'Electricista', 'hire' => '2024-02-20', 'pattern' => 'puntual', 'overtime' => 12.5],
        ['name' => 'Laura Ruiz', 'email' => 'laura@fichaje.test', 'code' => 'EMP-005', 'position' => 'Administrativo/a de mantenimiento', 'hire' => '2023-11-03', 'pattern' => 'puntual', 'overtime' => 10],

        ['name' => 'Rocío Navarro', 'email' => 'rocio@fichaje.test', 'code' => 'EMP-006', 'position' => 'Jefe/a de equipo', 'hire' => '2022-03-01', 'pattern' => 'puntual', 'overtime' => 14],
        ['name' => 'Sergio Ortega', 'email' => 'sergio@fichaje.test', 'code' => 'EMP-007', 'position' => 'Mecánico/a', 'hire' => '2023-11-03', 'pattern' => 'horas_extra', 'overtime' => 12],
        ['name' => 'Nadia Haddad', 'email' => 'nadia@fichaje.test', 'code' => 'EMP-008', 'position' => 'Ayudante', 'hire' => '2025-01-08', 'pattern' => 'olvida_salida', 'overtime' => 9],
        ['name' => 'Pablo Serrano', 'email' => 'pablo@fichaje.test', 'code' => 'EMP-009', 'position' => 'Administrativo/a de mantenimiento', 'hire' => '2022-09-15', 'pattern' => 'jornada_parcial', 'overtime' => 10],
        ['name' => 'Elena Castro', 'email' => 'elena@fichaje.test', 'code' => 'EMP-010', 'position' => 'Ayudante', 'hire' => '2024-05-02', 'pattern' => 'jornada_parcial', 'overtime' => 9],
        ['name' => 'Iván Molina', 'email' => 'ivan@fichaje.test', 'code' => 'EMP-011', 'position' => 'Fontanero/a', 'hire' => '2025-03-17', 'pattern' => 'impuntual', 'overtime' => 11],
        ['name' => 'Cristina Vega', 'email' => 'cristina@fichaje.test', 'code' => 'EMP-012', 'position' => 'Electricista', 'hire' => '2023-04-11', 'pattern' => 'horas_extra', 'overtime' => 12.5],
        ['name' => 'Diego Ramos', 'email' => 'diego@fichaje.test', 'code' => 'EMP-013', 'position' => 'Técnico/a de climatización', 'hire' => '2022-10-01', 'pattern' => 'puntual', 'overtime' => 11.5],
        ['name' => 'Yolanda Prieto', 'email' => 'yolanda@fichaje.test', 'code' => 'EMP-014', 'position' => 'Mecánico/a', 'hire' => '2024-08-26', 'pattern' => 'olvida_salida', 'overtime' => 12],
    ];

    public function run(): void
    {
        $company = DB::table('companies')->first();
        $calendar = DB::table('work_calendars')->first();

        if (! $company || ! $calendar) {
            $this->command?->error('Falta la empresa o el calendario. Ejecuta primero: php artisan db:seed');

            return;
        }

        $this->command?->info('Limpiando datos de demostración anteriores...');
        $this->resetDemoData();

        $zones = $this->seedClockZones($company->id);

        $admin = Employee::where('role', Employee::ROLE_ADMIN)->first();
        $manager = Employee::where('role', Employee::ROLE_MANAGER)->first();

        if (! $manager) {
            $this->command?->error('No existe el encargado. Ejecuta primero: php artisan db:seed');

            return;
        }

        $this->command?->info('Creando plantilla de ' . self::DEPARTMENT . '...');
        $employees = collect(self::STAFF)->map(
            fn (array $data) => $this->seedEmployee($data, $company->id, $calendar->id, $manager->id),
        );

        $until = Carbon::parse(self::HISTORY_UNTIL)->toDateString();
        $this->command?->info('Generando fichajes hasta el ' . $until . ' (sin jornadas abiertas)...');
        $bar = $this->command?->getOutput()->createProgressBar($employees->count());
        $bar?->start();

        foreach ($employees as $employee) {
            $this->seedTimeRecords($employee, $zones);
            $bar?->advance();
        }

        $bar?->finish();
        $this->command?->newLine(2);

        $this->command?->info('Creando solicitudes pendientes...');
        $this->seedAbsenceRequests($employees, $manager);
        $this->seedCorrectionRequests($employees);
        $this->seedEmployeeApplications();

        $this->report($employees->count());
    }

    /**
     * Borra lo generado por este seeder. Los time_records son inmutables a nivel
     * de modelo, así que se eliminan con el query builder.
     */
    private function resetDemoData(): void
    {
        $demoIds = DB::table('employees')
            ->whereIn('email', collect(self::STAFF)->pluck('email'))
            ->pluck('id');

        if ($demoIds->isNotEmpty()) {
            DB::table('correction_requests')->whereIn('requested_by', $demoIds)->delete();
            DB::table('absence_requests')->whereIn('employee_id', $demoIds)->delete();
            DB::table('schedule_exceptions')->whereIn('employee_id', $demoIds)->delete();
            DB::table('work_sessions')->whereIn('employee_id', $demoIds)->delete();
            DB::table('time_records')->whereIn('employee_id', $demoIds)->delete();
            DB::table('notifications')->whereIn('user_id', $demoIds)->delete();

            // El código de empleado es único: si se renumeran los puestos, dos
            // personas podrían pelearse por el mismo código a mitad del proceso.
            // Se usa la cola del ULID, que es la parte aleatoria.
            DB::table('employees')
                ->whereIn('id', $demoIds)
                ->update(['employee_code' => DB::raw("CONCAT('TMP-', RIGHT(id, 12))")]);
        }

        // Devuelve al encargado y al admin al estado en que estaban: ellos solo
        // revisan, no forman parte de la plantilla que se exporta.
        $staffIds = DB::table('employees')
            ->whereIn('role', [Employee::ROLE_ADMIN, Employee::ROLE_MANAGER])
            ->pluck('id');

        if ($staffIds->isNotEmpty()) {
            DB::table('work_sessions')->whereIn('employee_id', $staffIds)->delete();
            DB::table('time_records')->whereIn('employee_id', $staffIds)->where('origin', 'seed')->delete();
            DB::table('employees')->whereIn('id', $staffIds)->update([
                'position' => null,
                'department' => null,
            ]);
        }

        DB::table('employee_applications')->where('email', 'like', '%@candidato.test')->delete();
    }

    /**
     * @return array<int, string> ids de zonas de fichaje
     */
    private function seedClockZones(string $companyId): array
    {
        $zones = [
            ['name' => 'Nave de producción', 'ip' => '192.168.10.0/24'],
            ['name' => 'Almacén', 'ip' => '192.168.20.0/24'],
            ['name' => 'Oficinas', 'ip' => '192.168.30.0/24'],
        ];

        $ids = [];

        foreach ($zones as $zone) {
            $existing = DB::table('clock_zones')
                ->where('company_id', $companyId)
                ->where('name', $zone['name'])
                ->first();

            if ($existing) {
                $ids[] = $existing->id;

                continue;
            }

            $id = (string) Str::ulid();
            DB::table('clock_zones')->insert([
                'id' => $id,
                'company_id' => $companyId,
                'name' => $zone['name'],
                'ip' => $zone['ip'],
                'type' => 'office',
                'active' => true,
                'created_at' => now(),
            ]);

            $ids[] = $id;
        }

        return $ids;
    }

    private function seedEmployee(array $data, string $companyId, string $calendarId, string $managerId): Employee
    {
        $employee = Employee::withTrashed()->firstOrNew(['email' => $data['email']]);

        $employee->fill([
            'name' => $data['name'],
            'role' => Employee::ROLE_EMPLOYEE,
            'position' => $data['position'],
            'department' => self::DEPARTMENT,
            'overtime_rate' => $data['overtime'],
            'employee_code' => $data['code'],
            'employment_status' => 1,
            'hire_date' => $data['hire'],
            'company_id' => $companyId,
            'work_calendar_id' => $calendarId,
            'deleted_at' => null,
        ]);

        if (! $employee->exists) {
            $employee->password_hash = bcrypt('password');
        }

        $employee->save();

        $this->seedSchedule($employee->id, $data['pattern']);
        $this->linkToManager($managerId, $employee->id);

        // El patrón guía la generación de fichajes más adelante.
        $employee->setAttribute('demo_pattern', $data['pattern']);

        return $employee;
    }

    private function seedSchedule(string $employeeId, string $pattern): void
    {
        DB::table('employee_day_schedule')->where('employee_id', $employeeId)->delete();

        [$start, $end] = $pattern === 'jornada_parcial'
            ? ['09:00:00', '13:00:00']
            : ['08:00:00', '17:00:00'];

        foreach ([1, 2, 3, 4, 5] as $weekday) {
            DB::table('employee_day_schedule')->insert([
                'id' => (string) Str::ulid(),
                'employee_id' => $employeeId,
                'weekday' => $weekday,
                'start_time' => $start,
                'end_time' => $end,
                'active' => true,
                'start_date' => '2022-01-01',
                'end_date' => '2099-12-31',
                'created_at' => now(),
            ]);
        }
    }

    private function linkToManager(string $managerId, string $employeeId): void
    {
        $exists = DB::table('manager_employees')
            ->where('manager_id', $managerId)
            ->where('employee_id', $employeeId)
            ->whereNull('end_date')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('manager_employees')->insert([
            'id' => (string) Str::ulid(),
            'manager_id' => $managerId,
            'employee_id' => $employeeId,
            'start_date' => '2022-01-01',
            'end_date' => null,
            'created_at' => now(),
        ]);
    }

    /**
     * Genera entradas y salidas encadenadas hasta HISTORY_UNTIL.
     * Todas las jornadas quedan cerradas: el entorno de prueba no deja fichajes en curso.
     *
     * @param  array<int, string>  $zones
     */
    private function seedTimeRecords(Employee $employee, array $zones): void
    {
        $chain = app(TimeRecordChainService::class);
        $pattern = $employee->getAttribute('demo_pattern');
        $zoneId = $zones[array_rand($zones)] ?? null;

        $end = Carbon::parse(self::HISTORY_UNTIL)->startOfDay();
        $start = $end->copy()->subMonths(self::MONTHS_OF_HISTORY)->startOfMonth();
        $chainTip = null;

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            if (! $day->isWeekday()) {
                continue;
            }

            if ($day->lt(Carbon::parse($employee->hire_date))) {
                continue;
            }

            // Alguna ausencia esporádica: no fichó ese día.
            if (random_int(1, 100) <= 4) {
                continue;
            }

            [$in, $out] = $this->shiftFor($pattern, $day);

            $entrada = $chain->append([
                'employee_id' => $employee->id,
                'type' => TimeRecord::TYPE_CLOCK_IN,
                'recorded_at' => $in,
                'clock_method' => 'web',
                'validation_method' => 'ip',
                'clock_zone_id' => $zoneId,
                'ip' => '192.168.10.' . random_int(2, 250),
                'origin' => 'seed',
                'created_at' => $in,
            ], $chainTip);

            $salida = $chain->append([
                'employee_id' => $employee->id,
                'type' => TimeRecord::TYPE_CLOCK_OUT,
                'recorded_at' => $out,
                'clock_method' => 'web',
                'validation_method' => 'ip',
                'clock_zone_id' => $zoneId,
                'ip' => '192.168.10.' . random_int(2, 250),
                'origin' => 'seed',
                'created_at' => $out,
            ], $chainTip);

            WorkSession::create([
                'employee_id' => $employee->id,
                'clock_in_record_id' => $entrada->id,
                'clock_out_record_id' => $salida->id,
                'clocked_in_at' => $in,
                'clocked_out_at' => $out,
                'status' => WorkSession::STATUS_CLOSED,
                'processed' => true,
            ]);
        }
    }

    /**
     * Horario de entrada y salida según el perfil de comportamiento.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function shiftFor(string $pattern, Carbon $day): array
    {
        return match ($pattern) {
            'impuntual' => [
                $day->copy()->setTime(8, random_int(6, 35)),
                $day->copy()->setTime(17, random_int(0, 12)),
            ],
            'horas_extra' => [
                $day->copy()->setTime(7, random_int(45, 59)),
                $day->copy()->setTime(random_int(18, 19), random_int(0, 50)),
            ],
            'jornada_parcial' => [
                $day->copy()->setTime(9, random_int(0, 6)),
                $day->copy()->setTime(13, random_int(0, 15)),
            ],
            'olvida_salida' => [
                $day->copy()->setTime(8, random_int(0, 10)),
                $day->copy()->setTime(17, random_int(0, 20)),
            ],
            default => [
                $day->copy()->setTime(7, random_int(52, 59)),
                $day->copy()->setTime(17, random_int(0, 8)),
            ],
        };
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Employee>  $employees
     */
    private function seedAbsenceRequests($employees, Employee $manager): void
    {
        $pendientes = [
            ['type' => AbsenceRequest::TYPE_VACATION, 'days' => 5, 'in' => 20, 'reason' => 'Vacaciones de verano ya planificadas con la familia.'],
            ['type' => AbsenceRequest::TYPE_VACATION, 'days' => 10, 'in' => 35, 'reason' => 'Viaje al extranjero, billetes ya comprados.'],
            ['type' => AbsenceRequest::TYPE_MEDICAL_LEAVE, 'days' => 3, 'in' => 2, 'reason' => 'Intervención odontológica programada.'],
            ['type' => AbsenceRequest::TYPE_FREE_DAY, 'days' => 1, 'in' => 7, 'reason' => 'Asuntos propios: cita en el registro civil.'],
            ['type' => AbsenceRequest::TYPE_FREE_DAY, 'days' => 1, 'in' => 12, 'reason' => 'Mudanza.'],
            ['type' => AbsenceRequest::TYPE_MEDICAL_LEAVE, 'days' => 7, 'in' => 1, 'reason' => 'Baja por lumbalgia, adjunto parte médico.'],
            // Baja larga con fechas ya pasadas: comprueba cómo se comporta el panel
            // con una solicitud antigua que nadie ha resuelto.
            ['type' => AbsenceRequest::TYPE_MEDICAL_LEAVE, 'days' => 71, 'in' => -184, 'reason' => 'Baja de larga duración por intervención de rodilla. Parte de alta pendiente de registrar.'],
        ];

        foreach ($pendientes as $index => $data) {
            $employee = $employees[$index % $employees->count()];
            $start = now()->copy()->addDays($data['in']);

            AbsenceRequest::create([
                'employee_id' => $employee->id,
                'type' => $data['type'],
                'start_date' => $start->toDateString(),
                'end_date' => $start->copy()->addDays($data['days'] - 1)->toDateString(),
                'request_reason' => $data['reason'],
                'status' => AbsenceRequest::STATUS_PENDING,
            ]);
        }

        // Algunas ya resueltas, para que el histórico no esté vacío.
        $resueltas = [
            ['status' => AbsenceRequest::STATUS_APPROVED, 'comment' => 'Aprobado. Recuerda dejar el relevo organizado.'],
            ['status' => AbsenceRequest::STATUS_REJECTED, 'comment' => 'No es posible: coincide con el pico de producción.'],
            ['status' => AbsenceRequest::STATUS_APPROVED, 'comment' => 'Conforme.'],
        ];

        foreach ($resueltas as $index => $data) {
            $employee = $employees[($index + 6) % $employees->count()];
            $start = now()->copy()->subDays(random_int(20, 50));

            AbsenceRequest::create([
                'employee_id' => $employee->id,
                'type' => AbsenceRequest::TYPE_VACATION,
                'start_date' => $start->toDateString(),
                'end_date' => $start->copy()->addDays(2)->toDateString(),
                'request_reason' => 'Días de vacaciones pendientes del año pasado.',
                'status' => $data['status'],
                'reviewed_by' => $manager->id,
                'review_comment' => $data['comment'],
            ]);
        }

        // Vacaciones y bajas ya aprobadas, con fechas fijas, para el informe de laboral.
        $paraLaboral = [
            ['email' => 'empleado@fichaje.test', 'type' => AbsenceRequest::TYPE_VACATION, 'from' => '2026-08-10', 'to' => '2026-08-14', 'reason' => 'Vacaciones de agosto.'],
            ['email' => 'ana@fichaje.test', 'type' => AbsenceRequest::TYPE_VACATION, 'from' => '2026-08-24', 'to' => '2026-08-28', 'reason' => 'Puente de agosto.'],
            ['email' => 'sergio@fichaje.test', 'type' => AbsenceRequest::TYPE_MEDICAL_LEAVE, 'from' => '2026-08-18', 'to' => '2026-09-01', 'reason' => 'Baja laboral por lumbalgia. Parte de alta el 1 de septiembre.'],
        ];

        $byEmail = $employees->keyBy('email');

        foreach ($paraLaboral as $data) {
            $employee = $byEmail->get($data['email']);

            if (! $employee) {
                continue;
            }

            AbsenceRequest::create([
                'employee_id' => $employee->id,
                'type' => $data['type'],
                'start_date' => $data['from'],
                'end_date' => $data['to'],
                'request_reason' => $data['reason'],
                'status' => AbsenceRequest::STATUS_APPROVED,
                'reviewed_by' => $manager->id,
                'review_comment' => 'Aprobado para el informe de laboral.',
            ]);
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Employee>  $employees
     */
    private function seedCorrectionRequests($employees): void
    {
        $motivos = [
            'Olvidé fichar la salida, me fui a las 17:05.',
            'El lector no registró mi entrada, llegué a las 08:00.',
            'Fiché la salida por error al ir a comer.',
            'Se fue la luz y no pude fichar la salida.',
            'Entré por la puerta de almacén y no había lector.',
        ];

        foreach ($motivos as $index => $motivo) {
            $employee = $employees[$index % $employees->count()];

            $record = TimeRecord::where('employee_id', $employee->id)
                ->where('corrected', false)
                ->orderByDesc('recorded_at')
                ->skip($index)
                ->first();

            if (! $record) {
                continue;
            }

            CorrectionRequest::create([
                'time_record_id' => $record->id,
                'requested_by' => $employee->id,
                'new_datetime' => $record->recorded_at->copy()->addMinutes(random_int(10, 45)),
                'reason' => $motivo,
                'status' => CorrectionRequest::STATUS_PENDING,
            ]);
        }
    }

    /** Candidatos que han rellenado el formulario público de alta. */
    private function seedEmployeeApplications(): void
    {
        $candidatos = [
            [
                'name' => 'Lucía', 'surname' => 'Ibáñez Soto',
                'email' => 'lucia@candidato.test', 'phone' => '600111222',
                'document_type' => 'dni', 'document_number' => '12345678Z',
                'naf' => '281234567840T', 'has_ss' => true,
                'position' => 'Administrativo/a de mantenimiento',
                'notes' => 'Disponibilidad inmediata, experiencia previa en gestión documental.',
            ],
            [
                'name' => 'Karim', 'surname' => 'El Amrani',
                'email' => 'karim@candidato.test', 'phone' => '600333444',
                'document_type' => 'nie', 'document_number' => 'X1234567L',
                'naf' => null, 'has_ss' => false,
                'position' => 'Ayudante',
                'notes' => 'Aporta pasaporte y TIE con autorización de trabajo.',
            ],
            [
                'name' => 'Marta', 'surname' => 'Del Río Peña',
                'email' => 'marta@candidato.test', 'phone' => '600555666',
                'document_type' => 'dni', 'document_number' => '12345678Z',
                'naf' => '084567890118T', 'has_ss' => true,
                'position' => 'Fontanero/a',
                'notes' => 'Busca jornada parcial de mañanas.',
            ],
        ];

        foreach ($candidatos as $data) {
            EmployeeApplication::create([
                'candidate_name' => $data['name'],
                'candidate_surname' => $data['surname'],
                'birth_date' => '1995-04-12',
                'nationality' => $data['has_ss'] ? 'Española' : 'Marroquí',
                'marital_status' => 'soltero',
                'dependents_count' => 0,
                'disability_recognized' => false,
                'address' => 'Calle Ejemplo 1, 28001 Madrid, Madrid',
                'street' => 'Calle Ejemplo 1',
                'postal_code' => '28001',
                'city' => 'Madrid',
                'province' => 'Madrid',
                'phone' => $data['phone'],
                'phone_verified_at' => now(),
                'email' => $data['email'],
                'document_type' => $data['document_type'],
                'document_number' => $data['document_number'],
                'document_expiry_date' => now()->addYears(4)->toDateString(),
                'document_ocr_verified' => true,
                'has_social_security' => $data['has_ss'],
                'social_security_number' => $data['naf'],
                'work_permit_type' => $data['has_ss'] ? null : 'tie',
                'work_permit_number' => $data['has_ss'] ? null : 'X1234567L',
                'work_permit_expiry' => $data['has_ss'] ? null : now()->addYear()->toDateString(),
                'passport_number' => $data['has_ss'] ? null : 'AB123456',
                'passport_expiry' => $data['has_ss'] ? null : now()->addYears(6)->toDateString(),
                'position' => $data['position'],
                'department' => self::DEPARTMENT,
                'start_date' => now()->addDays(15)->toDateString(),
                'contract_type' => 'indefinido',
                'work_schedule' => 'completa',
                'iban' => 'ES9121000418450200051332',
                'bank_name' => 'CaixaBank',
                'irpf_data' => [
                    'family_situation' => '1',
                    'children_under_3' => 0,
                    'disability_degree' => 0,
                    'additional_withholding' => 0,
                ],
                'notes' => $data['notes'],
                'gdpr_accepted_at' => now(),
                'gdpr_version' => config('privacy.consent_version'),
                'status' => EmployeeApplication::STATUS_PENDING,
            ]);
        }
    }

    private function report(int $staffCount): void
    {
        $this->command?->newLine();
        $this->command?->info('Entorno de pruebas listo.');
        $this->command?->table(
            ['Concepto', 'Cantidad'],
            [
                ['Trabajadores en ' . self::DEPARTMENT, $staffCount],
                ['Puestos distintos', collect(self::STAFF)->pluck('position')->unique()->count()],
                ['Fichajes registrados', TimeRecord::count()],
                ['Jornadas', WorkSession::count()],
                ['Jornadas abiertas', WorkSession::where('status', WorkSession::STATUS_OPEN)->count()],
                ['Último día con fichajes', self::HISTORY_UNTIL],
                ['Ausencias pendientes', AbsenceRequest::where('status', 'pending')->count()],
                ['Correcciones pendientes', CorrectionRequest::where('status', 'pending')->count()],
                ['Altas pendientes', EmployeeApplication::where('status', 'pending')->count()],
            ],
        );
        $this->command?->newLine();
        $this->command?->line('  Todos entran con la contraseña: <fg=yellow>password</>');
        $this->command?->line('  Encargado de toda la plantilla: <fg=yellow>encargado@fichaje.test</> (Tomy)');
    }
}
