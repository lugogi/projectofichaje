<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Services\WebPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function __construct(private WebPushService $webPush) {}

    public function config(): JsonResponse
    {
        return response()->json([
            'enabled' => $this->webPush->isConfigured(),
            'public_key' => $this->webPush->publicKey(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:1000'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'content_encoding' => ['nullable', 'string', 'max:20'],
        ]);

        $endpoint = $validated['endpoint'];

        PushSubscription::updateOrCreate(
            ['endpoint_hash' => hash('sha256', $endpoint)],
            [
                'user_id' => $request->user()->id,
                'endpoint' => $endpoint,
                'public_key' => $validated['keys']['p256dh'],
                'auth_token' => $validated['keys']['auth'],
                'content_encoding' => $validated['content_encoding'] ?? 'aes128gcm',
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
                'last_used_at' => now(),
                'failure_count' => 0,
            ],
        );

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:1000'],
        ]);

        PushSubscription::query()
            ->where('user_id', $request->user()->id)
            ->where('endpoint_hash', hash('sha256', $validated['endpoint']))
            ->delete();

        return response()->json(['ok' => true]);
    }

    public function test(Request $request): JsonResponse
    {
        $this->webPush->sendToUser($request->user()->id, [
            'title' => 'Notificaciones activadas',
            'body' => 'Recibirás avisos aunque tengas la aplicación cerrada.',
            'url' => url('/dashboard'),
            'tag' => 'push-test',
            'category' => 'general',
        ]);

        return response()->json(['ok' => true]);
    }
}
