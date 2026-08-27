<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

class WebPushService
{
    public function isConfigured(): bool
    {
        return config('webpush.enabled')
            && filled(config('webpush.public_key'))
            && filled(config('webpush.private_key'));
    }

    public function publicKey(): ?string
    {
        return config('webpush.public_key');
    }

    /**
     * Envía una notificación push a todos los dispositivos registrados del usuario.
     */
    public function sendToUser(string $userId, array $payload): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        $subscriptions = PushSubscription::query()
            ->where('user_id', $userId)
            ->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        try {
            $webPush = $this->client();
        } catch (Throwable $e) {
            Log::warning('WebPush no pudo inicializarse', ['error' => $e->getMessage()]);

            return;
        }

        $byEndpoint = [];

        foreach ($subscriptions as $subscription) {
            $byEndpoint[$subscription->endpoint] = $subscription;

            try {
                $webPush->queueNotification(
                    Subscription::create([
                        'endpoint' => $subscription->endpoint,
                        'publicKey' => $subscription->public_key,
                        'authToken' => $subscription->auth_token,
                        'contentEncoding' => $subscription->content_encoding ?: 'aes128gcm',
                    ]),
                    json_encode($payload, JSON_UNESCAPED_UNICODE),
                );
            } catch (Throwable $e) {
                Log::warning('No se pudo encolar la notificación push', [
                    'subscription' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        try {
            foreach ($webPush->flush() as $report) {
                $endpoint = (string) $report->getRequest()->getUri();
                $subscription = $byEndpoint[$endpoint] ?? null;

                if (! $subscription) {
                    continue;
                }

                if ($report->isSuccess()) {
                    $subscription->update([
                        'last_used_at' => now(),
                        'failure_count' => 0,
                    ]);

                    continue;
                }

                // 404/410: el navegador ya no reconoce la suscripción
                if ($report->isSubscriptionExpired()) {
                    $subscription->delete();

                    continue;
                }

                $failures = $subscription->failure_count + 1;

                if ($failures >= (int) config('webpush.max_failures', 3)) {
                    $subscription->delete();
                } else {
                    $subscription->update(['failure_count' => $failures]);
                }

                Log::info('Fallo al enviar push', [
                    'subscription' => $subscription->id,
                    'reason' => $report->getReason(),
                ]);
            }
        } catch (Throwable $e) {
            Log::warning('Error al enviar notificaciones push', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Construye el payload que recibirá el service worker.
     */
    public function payloadFor(AppNotification $notification, string $category): array
    {
        return [
            'id' => $notification->id,
            'title' => $notification->title,
            'body' => $notification->message,
            'event_type' => $notification->event_type,
            'category' => $category,
            'url' => $notification->target_url ?: url('/dashboard'),
            'tag' => $notification->event_type . ':' . $notification->user_id,
        ];
    }

    private function client(): WebPush
    {
        return new WebPush([
            'VAPID' => [
                'subject' => config('webpush.subject'),
                'publicKey' => config('webpush.public_key'),
                'privateKey' => config('webpush.private_key'),
            ],
        ], [
            'TTL' => 43200,
            'urgency' => 'normal',
        ]);
    }
}
