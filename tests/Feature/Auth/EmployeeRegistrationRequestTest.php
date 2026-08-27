<?php

namespace Tests\Feature\Auth;

use App\Models\Employee;
use App\Models\AppNotification;
use App\Models\EmployeeApplication;
use App\Services\PhoneOtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmployeeRegistrationRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_form_is_available(): void
    {
        $response = $this->get('/darse-de-alta');

        $response->assertStatus(200);
    }

    private function verifyPhone(string $phone = '600123456'): void
    {
        $otp = app(PhoneOtpService::class);
        $otp->send($phone);
        $otp->verify($phone, cache()->get('employee_app_otp:+34' . $phone));
    }

    /**
     * Solicitud completa con NAF de titular. Sobrescribe lo que necesites por test.
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ana',
            'surname' => 'Pérez',
            'birth_date' => '1990-05-15',
            'nationality' => 'Española',
            'marital_status' => 'soltero',
            'dependents_count' => 0,
            'disability_recognized' => '0',
            'street' => 'Calle Mayor 12',
            'postal_code' => '28001',
            'city' => 'Madrid',
            'province' => 'Madrid',
            'phone' => '600123456',
            'phone_verified' => '1',
            'email' => 'ana.nueva@example.com',
            'document_type' => 'dni',
            'document_number' => '12345678Z',
            'document_expiry_date' => now()->addYears(3)->toDateString(),
            'has_social_security' => '1',
            'social_security_number' => '281234567840T',
            'document_ocr_verified' => '0',
            'position' => 'Administrativo/a',
            'department' => 'Administración',
            'start_date' => now()->addWeek()->toDateString(),
            'contract_type' => 'indefinido',
            'work_schedule' => 'completa',
            'iban' => 'ES9121000418450200051332',
            'irpf_family_situation' => '1',
            'irpf_children_under_3' => 0,
            'irpf_disability_degree' => 0,
            'gdpr_accepted' => '1',
            'gdpr_version' => '2026-08',
            'signature' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            'notes' => 'Solicita trabajo de apoyo administrativo.',
            'document_images' => [
                UploadedFile::fake()->image('dni.jpg', 1200, 800),
            ],
        ], $overrides);
    }

    public function test_candidate_application_is_created_and_notified_to_managers(): void
    {
        Storage::fake('local');

        $admin = Employee::factory()->create(['role' => Employee::ROLE_ADMIN]);
        $manager = Employee::factory()->create(['role' => Employee::ROLE_MANAGER]);

        $this->verifyPhone();

        $response = $this->post('/darse-de-alta', $this->payload());

        $response->assertRedirect();
        $this->assertDatabaseHas('employee_applications', [
            'email' => 'ana.nueva@example.com',
            'status' => EmployeeApplication::STATUS_PENDING,
            'department' => 'Administración',
            'social_security_number' => '281234567840T',
            'has_social_security' => true,
            'gdpr_version' => '2026-08',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'event_type' => 'employee_application_submitted',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $manager->id,
            'event_type' => 'employee_application_submitted',
        ]);

        $this->assertTrue(AppNotification::query()->where('event_type', 'employee_application_submitted')->count() >= 2);
    }

    public function test_social_security_number_must_end_in_t_for_holder(): void
    {
        Storage::fake('local');
        $this->verifyPhone();

        $response = $this->from('/darse-de-alta')->post('/darse-de-alta', $this->payload([
            'social_security_number' => '281234567840',
        ]));

        $response->assertSessionHasErrors('social_security_number');
        $this->assertDatabaseCount('employee_applications', 0);
    }

    public function test_beneficiary_social_security_number_is_rejected(): void
    {
        Storage::fake('local');
        $this->verifyPhone();

        $response = $this->from('/darse-de-alta')->post('/darse-de-alta', $this->payload([
            'social_security_number' => '281234567840B',
        ]));

        $response->assertSessionHasErrors('social_security_number');
        $this->assertDatabaseCount('employee_applications', 0);
    }

    public function test_social_security_number_with_wrong_control_digits_is_rejected(): void
    {
        Storage::fake('local');
        $this->verifyPhone();

        $response = $this->from('/darse-de-alta')->post('/darse-de-alta', $this->payload([
            'social_security_number' => '281234567841T',
        ]));

        $response->assertSessionHasErrors('social_security_number');
        $this->assertDatabaseCount('employee_applications', 0);
    }

    public function test_foreign_worker_can_apply_with_work_permit_instead_of_naf(): void
    {
        Storage::fake('local');
        Employee::factory()->create(['role' => Employee::ROLE_ADMIN]);
        $this->verifyPhone();

        $response = $this->post('/darse-de-alta', $this->payload([
            'email' => 'karim.nuevo@example.com',
            'nationality' => 'Marroquí',
            'document_type' => 'nie',
            'document_number' => 'X1234567L',
            'has_social_security' => '0',
            'social_security_number' => null,
            'work_permit_type' => 'tie',
            'work_permit_number' => 'X1234567L',
            'work_permit_expiry' => now()->addYear()->toDateString(),
            'passport_number' => 'AB123456',
            'passport_expiry' => now()->addYears(5)->toDateString(),
            'document_images' => [
                UploadedFile::fake()->image('pasaporte.jpg', 1200, 800),
                UploadedFile::fake()->image('tie.jpg', 1200, 800),
            ],
        ]));

        $response->assertRedirect();
        $this->assertDatabaseHas('employee_applications', [
            'email' => 'karim.nuevo@example.com',
            'has_social_security' => false,
            'work_permit_type' => 'tie',
            'work_permit_number' => 'X1234567L',
            'passport_number' => 'AB123456',
            'social_security_number' => null,
        ]);
    }

    public function test_foreign_worker_must_attach_passport_and_permit(): void
    {
        Storage::fake('local');
        $this->verifyPhone();

        $response = $this->from('/darse-de-alta')->post('/darse-de-alta', $this->payload([
            'has_social_security' => '0',
            'social_security_number' => null,
            'work_permit_type' => 'tie',
            'work_permit_number' => 'X1234567L',
            'work_permit_expiry' => now()->addYear()->toDateString(),
            'passport_number' => 'AB123456',
            'passport_expiry' => now()->addYears(5)->toDateString(),
            'document_images' => [
                UploadedFile::fake()->image('solo-pasaporte.jpg', 1200, 800),
            ],
        ]));

        $response->assertSessionHasErrors('document_images');
        $this->assertDatabaseCount('employee_applications', 0);
    }

    public function test_foreign_worker_with_invalid_nie_is_rejected(): void
    {
        Storage::fake('local');
        $this->verifyPhone();

        $response = $this->from('/darse-de-alta')->post('/darse-de-alta', $this->payload([
            'has_social_security' => '0',
            'social_security_number' => null,
            'work_permit_type' => 'tie',
            'work_permit_number' => 'X1234567A',
            'work_permit_expiry' => now()->addYear()->toDateString(),
            'passport_number' => 'AB123456',
            'passport_expiry' => now()->addYears(5)->toDateString(),
            'document_images' => [
                UploadedFile::fake()->image('pasaporte.jpg', 1200, 800),
                UploadedFile::fake()->image('tie.jpg', 1200, 800),
            ],
        ]));

        $response->assertSessionHasErrors('work_permit_number');
    }

    public function test_gdpr_consent_is_mandatory(): void
    {
        Storage::fake('local');
        $this->verifyPhone();

        $response = $this->from('/darse-de-alta')->post('/darse-de-alta', $this->payload([
            'gdpr_accepted' => '0',
        ]));

        $response->assertSessionHasErrors('gdpr_accepted');
        $this->assertDatabaseCount('employee_applications', 0);
    }
}
