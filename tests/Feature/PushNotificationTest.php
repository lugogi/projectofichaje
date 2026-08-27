<?php

namespace Tests\Feature;

use App\Jobs\SendWebPushNotification;
use App\Models\Employee;
use App\Models\PushSubscription;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function fakeVapid(): void
    {
        config([
            'webpush.enabled' => true,
            'webpush.public_key' => 'BE8eqdP1CA7vca6gVX4m9ehU1UGCYlAmkZ0meONKA4yjUzy7vRHe8WxRroD5krGP0pqtNoiVeSdgU3BHNhtPUh0',
            'webpush.private_key' => '_KKkhemiULbP4oT3wyB-nG1V4TpPeBWAnS21fY63_YM',
            'webpush.subject' => 'mailto:admin@fichatime.local',
        ]);
    }

    public function test_config_endpoint_exposes_public_key(): void
    {
        $this->fakeVapid();
        $employee = Employee::factory()->create();

        $response = $this->actingAs($employee)->getJson('/api/push/config');

        $response->assertOk()
            ->assertJson(['enabled' => true])
            ->assertJsonPath('public_key', config('webpush.public_key'));
    }

    public function test_employee_can_register_a_device(): void
    {
        $this->fakeVapid();
        $employee = Employee::factory()->create();

        $response = $this->actingAs($employee)->postJson('/api/push/subscribe', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
            'keys' => [
                'p256dh' => 'BJxKI_dhbnc0k0EMEbHkYFGRHiJlqYNzTGmR1YFCPWCJfLPKlIu6MDrPPGFsAoBdmyjP4hpBURLmiUqNSNiUYDo',
                'auth' => 'kZOd2A2MpRHzGmH0hqLMSw',
            ],
        ]);

        $response->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $employee->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
            'content_encoding' => 'aes128gcm',
        ]);
    }

    public function test_registering_same_device_twice_does_not_duplicate(): void
    {
        $this->fakeVapid();
        $employee = Employee::factory()->create();

        $payload = [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/same-device',
            'keys' => [
                'p256dh' => 'BJxKI_dhbnc0k0EMEbHkYFGRHiJlqYNzTGmR1YFCPWCJfLPKlIu6MDrPPGFsAoBdmyjP4hpBURLmiUqNSNiUYDo',
                'auth' => 'kZOd2A2MpRHzGmH0hqLMSw',
            ],
        ];

        $this->actingAs($employee)->postJson('/api/push/subscribe', $payload)->assertOk();
        $this->actingAs($employee)->postJson('/api/push/subscribe', $payload)->assertOk();

        $this->assertSame(1, PushSubscription::where('user_id', $employee->id)->count());
    }

    public function test_employee_can_remove_a_device(): void
    {
        $this->fakeVapid();
        $employee = Employee::factory()->create();
        $endpoint = 'https://fcm.googleapis.com/fcm/send/to-delete';

        PushSubscription::create([
            'user_id' => $employee->id,
            'endpoint' => $endpoint,
            'public_key' => 'test-key',
            'auth_token' => 'test-auth',
        ]);

        $this->actingAs($employee)
            ->postJson('/api/push/unsubscribe', ['endpoint' => $endpoint])
            ->assertOk();

        $this->assertDatabaseMissing('push_subscriptions', [
            'endpoint_hash' => hash('sha256', $endpoint),
        ]);
    }

    public function test_guests_cannot_register_devices(): void
    {
        $this->postJson('/api/push/subscribe', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/anon',
            'keys' => ['p256dh' => 'x', 'auth' => 'y'],
        ])->assertUnauthorized();
    }

    public function test_notification_queues_a_push_for_the_recipient(): void
    {
        $this->fakeVapid();
        Bus::fake();

        $manager = Employee::factory()->create(['role' => Employee::ROLE_MANAGER]);

        app(NotificationService::class)->notify(
            $manager,
            'Nuevo fichaje',
            'Ana ha registrado entrada a las 09:00.',
            'clock_recorded',
            '/encargado',
        );

        Bus::assertDispatchedAfterResponse(
            SendWebPushNotification::class,
            fn (SendWebPushNotification $job) => $job->userId === $manager->id
                && $job->payload['title'] === 'Nuevo fichaje'
                && $job->payload['category'] === 'fichaje',
        );
    }

    public function test_no_push_is_queued_when_vapid_is_not_configured(): void
    {
        config(['webpush.public_key' => null, 'webpush.private_key' => null]);
        Bus::fake();

        $manager = Employee::factory()->create(['role' => Employee::ROLE_MANAGER]);

        app(NotificationService::class)->notify(
            $manager,
            'Nuevo fichaje',
            'Ana ha registrado entrada.',
            'clock_recorded',
        );

        Bus::assertNotDispatchedAfterResponse(SendWebPushNotification::class);
    }
}
