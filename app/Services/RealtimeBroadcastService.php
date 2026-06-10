<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class RealtimeBroadcastService
{
    public function send(object $event): void
    {
        if (config('broadcasting.default') === 'null') {
            return;
        }

        try {
            broadcast($event);
        } catch (\Throwable $e) {
            Log::warning('Realtime broadcast failed', [
                'event' => $event::class,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
