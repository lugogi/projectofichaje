<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeApplication;
use App\Models\StoredFile;
use App\Services\NotificationService;
use App\Services\PhoneOtpService;
use App\Services\SpanishDocumentValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeApplicationController extends Controller
{
    public function __construct(
        private NotificationService $notifications,
        private PhoneOtpService $otp,
    ) {}

    public function create(): Response
    {
        return Inertia::render('Auth/EmployeeApplication', [
            'privacy' => [
                'controller' => config('privacy.controller'),
                'links' => config('privacy.links'),
                'consent_version' => config('privacy.consent_version'),
            ],
            'departments' => [
                'Administración',
                'Atención al cliente',
                'Comercial',
                'Informática',
                'Logística',
                'Producción',
                'Recursos Humanos',
                'Otro',
            ],
            'positions' => [
                'Administrativo/a',
                'Auxiliar',
                'Comercial',
                'Encargado/a',
                'Operario/a',
                'Técnico/a',
                'Otro',
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'surname' => ['required', 'string', 'max:120'],
            'birth_date' => ['required', 'date', 'before:today', 'after:1900-01-01'],
            'nationality' => ['required', 'string', 'max:80'],
            'marital_status' => ['required', 'string', 'in:soltero,casado,divorciado,viudo,pareja_hecho'],
            'dependents_count' => ['required', 'integer', 'min:0', 'max:20'],
            'disability_recognized' => ['required', 'boolean'],
            'street' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'regex:/^[0-9]{5}$/'],
            'city' => ['required', 'string', 'max:120'],
            'province' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30'],
            'phone_verified' => ['required', 'accepted'],
            'email' => ['required', 'email', 'max:255'],
            'document_type' => ['required', 'string', 'in:dni,nif,nie,ss'],
            'document_number' => ['required', 'string', 'max:30'],
            'document_expiry_date' => ['required', 'date', 'after:today'],
            'has_social_security' => ['required', 'boolean'],
            'social_security_number' => ['required_if:has_social_security,1', 'nullable', 'string', 'max:20'],
            'work_permit_type' => ['required_if:has_social_security,0', 'nullable', 'string', 'in:tie,visado,permiso_trabajo'],
            'work_permit_number' => ['required_if:has_social_security,0', 'nullable', 'string', 'max:30'],
            'work_permit_expiry' => ['required_if:has_social_security,0', 'nullable', 'date', 'after:today'],
            'passport_number' => ['required_if:has_social_security,0', 'nullable', 'string', 'max:30'],
            'passport_expiry' => ['required_if:has_social_security,0', 'nullable', 'date', 'after:today'],
            'document_ocr_verified' => ['nullable', 'boolean'],
            'position' => ['required', 'string', 'max:120'],
            'department' => ['required', 'string', 'max:120'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'contract_type' => ['required', 'string', 'in:indefinido,temporal,practicas,formacion'],
            'work_schedule' => ['required', 'string', 'in:completa,parcial,reducida'],
            'iban' => ['required', 'string', 'max:34'],
            'irpf_family_situation' => ['required', 'string', 'max:50'],
            'irpf_children_under_3' => ['required', 'integer', 'min:0', 'max:20'],
            'irpf_disability_degree' => ['required', 'integer', 'min:0', 'max:100'],
            'irpf_additional_withholding' => ['nullable', 'numeric', 'min:0', 'max:30'],
            'gdpr_accepted' => ['required', 'accepted'],
            'gdpr_version' => ['nullable', 'string', 'max:20'],
            'signature' => ['required', 'string'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'document_images' => ['nullable', 'array', 'max:6'],
            'document_images.*' => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        if (! $this->otp->isVerified($validated['phone'])) {
            return back()->withErrors(['phone' => 'Debes verificar tu teléfono con el código SMS antes de enviar.'])->withInput();
        }

        if (! SpanishDocumentValidator::validateDocument($validated['document_type'], $validated['document_number'])) {
            return back()->withErrors(['document_number' => 'El documento no es válido (formato o letra de control incorrecta).'])->withInput();
        }

        $hasSocialSecurity = (bool) $validated['has_social_security'];
        $naf = null;

        if ($hasSocialSecurity) {
            $naf = SpanishDocumentValidator::normalizeNaf($validated['social_security_number']);

            if (SpanishDocumentValidator::isBeneficiaryNaf($naf)) {
                return back()->withErrors([
                    'social_security_number' => 'Ese número es de beneficiario. Para el alta hace falta el de titular, que termina en T.',
                ])->withInput();
            }

            if (! SpanishDocumentValidator::validateNaf($naf)) {
                return back()->withErrors([
                    'social_security_number' => 'El número de afiliación no es válido. Debe tener 12 dígitos, terminar en T y cuadrar con sus dígitos de control.',
                ])->withInput();
            }
        } elseif (! SpanishDocumentValidator::validateNie($validated['work_permit_number'])) {
            return back()->withErrors([
                'work_permit_number' => 'El NIE del permiso de trabajo no es válido.',
            ])->withInput();
        }

        $minimumDocuments = $hasSocialSecurity ? 1 : 2;

        if (count($request->file('document_images') ?? []) < $minimumDocuments) {
            return back()->withErrors([
                'document_images' => $hasSocialSecurity
                    ? 'Adjunta al menos un documento identificativo.'
                    : 'Sin número de afiliación debes adjuntar el pasaporte completo y la TIE o el visado.',
            ])->withInput();
        }

        $iban = strtoupper(preg_replace('/\s+/', '', $validated['iban']));
        if (! SpanishDocumentValidator::validateIban($iban)) {
            return back()->withErrors(['iban' => 'El IBAN introducido no es válido.'])->withInput();
        }

        if (! preg_match('/^data:image\/png;base64,/', $validated['signature'])) {
            return back()->withErrors(['signature' => 'La firma digital no es válida.'])->withInput();
        }

        $fullAddress = trim("{$validated['street']}, {$validated['postal_code']} {$validated['city']}, {$validated['province']}");
        $bankName = SpanishDocumentValidator::spanishBankName($iban);

        $application = EmployeeApplication::create([
            'candidate_name' => $validated['name'],
            'candidate_surname' => $validated['surname'],
            'birth_date' => $validated['birth_date'],
            'nationality' => $validated['nationality'],
            'marital_status' => $validated['marital_status'],
            'dependents_count' => $validated['dependents_count'],
            'disability_recognized' => $validated['disability_recognized'],
            'address' => $fullAddress,
            'street' => $validated['street'],
            'postal_code' => $validated['postal_code'],
            'city' => $validated['city'],
            'province' => $validated['province'],
            'phone' => $validated['phone'],
            'phone_verified_at' => now(),
            'email' => $validated['email'],
            'document_type' => $validated['document_type'],
            'document_number' => strtoupper(preg_replace('/[\s-]/', '', $validated['document_number'])),
            'document_expiry_date' => $validated['document_expiry_date'],
            'document_ocr_verified' => (bool) ($validated['document_ocr_verified'] ?? false),
            'has_social_security' => $hasSocialSecurity,
            'social_security_number' => $naf,
            'work_permit_type' => $hasSocialSecurity ? null : $validated['work_permit_type'],
            'work_permit_number' => $hasSocialSecurity ? null : strtoupper($validated['work_permit_number']),
            'work_permit_expiry' => $hasSocialSecurity ? null : $validated['work_permit_expiry'],
            'passport_number' => $hasSocialSecurity ? null : strtoupper($validated['passport_number']),
            'passport_expiry' => $hasSocialSecurity ? null : $validated['passport_expiry'],
            'position' => $validated['position'],
            'department' => $validated['department'],
            'start_date' => $validated['start_date'],
            'contract_type' => $validated['contract_type'],
            'work_schedule' => $validated['work_schedule'],
            'iban' => $iban,
            'bank_name' => $bankName,
            'irpf_data' => [
                'family_situation' => $validated['irpf_family_situation'],
                'children_under_3' => $validated['irpf_children_under_3'],
                'disability_degree' => $validated['irpf_disability_degree'],
                'additional_withholding' => $validated['irpf_additional_withholding'] ?? 0,
            ],
            'notes' => $validated['notes'] ?? null,
            'gdpr_accepted_at' => now(),
            'gdpr_version' => $validated['gdpr_version'] ?? config('privacy.consent_version'),
            'status' => EmployeeApplication::STATUS_PENDING,
        ]);

        $this->storeSignature($application, $validated['signature']);
        $this->storeDocuments($request, $application);

        $this->notifyReviewers($validated['name'], $validated['surname'], $validated['department']);

        return redirect()->route('login')->with('status', 'Tu solicitud de alta ha sido enviada correctamente. El encargado la revisará y te contactará.');
    }

    private function storeSignature(EmployeeApplication $application, string $dataUrl): void
    {
        $base64 = preg_replace('#^data:image/\w+;base64,#', '', $dataUrl);
        $binary = base64_decode($base64, true);

        if ($binary === false) {
            return;
        }

        $directory = "employee-applications/{$application->id}";
        $fileName = 'signature.png';
        $storageKey = "{$directory}/{$fileName}";

        Storage::disk('local')->put($storageKey, $binary);

        StoredFile::create([
            'uploaded_by' => null,
            'entity_type' => 'employee_application',
            'entity_id' => $application->id,
            'file_name' => 'firma-digital.png',
            'storage_provider' => 'local',
            'bucket' => 'local',
            'storage_key' => $storageKey,
            'mime_type' => 'image/png',
            'extension' => 'png',
            'size_bytes' => strlen($binary),
            'hash_sha256' => hash('sha256', $binary),
            'visibility' => 'private',
            'status' => 'stored',
        ]);

        $application->update(['signature_path' => $storageKey]);
    }

    private function storeDocuments(Request $request, EmployeeApplication $application): void
    {
        if (! $request->hasFile('document_images')) {
            return;
        }

        foreach ($request->file('document_images') as $file) {
            $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension());
            $fileName = Str::uuid() . '.' . $extension;
            $directory = "employee-applications/{$application->id}";
            $storageKey = "{$directory}/{$fileName}";

            $file->storeAs($directory, $fileName, 'local');

            StoredFile::create([
                'uploaded_by' => null,
                'entity_type' => 'employee_application',
                'entity_id' => $application->id,
                'file_name' => $file->getClientOriginalName(),
                'storage_provider' => 'local',
                'bucket' => 'local',
                'storage_key' => $storageKey,
                'mime_type' => $file->getMimeType(),
                'extension' => $extension,
                'size_bytes' => $file->getSize(),
                'hash_sha256' => hash_file('sha256', $file->getRealPath()),
                'visibility' => 'private',
                'status' => 'stored',
            ]);
        }
    }

    private function notifyReviewers(string $name, string $surname, string $department): void
    {
        $reviewers = Employee::query()
            ->whereIn('role', [Employee::ROLE_ADMIN, Employee::ROLE_MANAGER])
            ->where(function ($query) {
                $query->where('employment_status', 1)->orWhereNull('employment_status');
            })
            ->whereNull('deleted_at')
            ->get();

        $fullName = trim("{$name} {$surname}");

        foreach ($reviewers as $reviewer) {
            $this->notifications->notify(
                $reviewer,
                'Nueva solicitud de alta',
                "{$fullName} ({$department}) ha completado el formulario de alta y espera revisión.",
                'employee_application_submitted',
                $reviewer->isAdmin() ? route('admin.solicitudes.index') : route('manager.solicitudes.index'),
            );
        }
    }
}
