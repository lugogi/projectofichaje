<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        private NotificationService $notifications,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'items' => $this->notifications->listForUser($request->user()->id),
            'unread_count' => $this->notifications->unreadCount($request->user()->id),
        ]);
    }

    public function markAsRead(Request $request, string $notification): JsonResponse
    {
        $notification = AppNotification::query()
            ->where('id', $notification)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        return $this->markNotificationAsRead($request, $notification);
    }

    private function markNotificationAsRead(Request $request, AppNotification $notification): JsonResponse
    {
        if ($notification->isUnread()) {
            $notification->update(['read_at' => now()]);
        }

        return response()->json([
            'unread_count' => $this->notifications->unreadCount($request->user()->id),
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'unread_count' => 0,
        ]);
    }
}
