<?php

namespace App\Services;

use App\Events\NotificationCreated;
use App\Jobs\SendWebPushNotification;
use App\Models\AppNotification;
use App\Models\Employee;

class NotificationService
{
    public function __construct(
        private RealtimeBroadcastService $realtime,
        private WebPushService $webPush,
    ) {}

    public function notify(
        Employee|string $user,
        string $title,
        string $message,
        string $eventType,
        ?string $targetUrl = null,
    ): AppNotification {
        $userId = $user instanceof Employee ? $user->id : $user;

        $notification = AppNotification::create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'event_type' => $eventType,
            'target_url' => $targetUrl,
        ]);

        $this->realtime->send(new NotificationCreated($notification));
        $this->pushToDevices($notification);

        return $notification;
    }

    /**
     * Envía la notificación a los dispositivos del usuario (llega con la app cerrada).
     * Se despacha tras la respuesta HTTP para no penalizar el tiempo de carga.
     */
    private function pushToDevices(AppNotification $notification): void
    {
        if (! $this->webPush->isConfigured()) {
            return;
        }

        if (in_array($notification->event_type, config('webpush.muted_event_types', []), true)) {
            return;
        }

        SendWebPushNotification::dispatchAfterResponse(
            $notification->user_id,
            $this->webPush->payloadFor($notification, $this->categoryFor($notification->event_type)),
        );
    }

    public function unreadCount(string $userId): int
    {
        return AppNotification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->count();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listForUser(string $userId, int $limit = 80): array
    {
        return AppNotification::query()
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (AppNotification $n) => $this->format($n))
            ->all();
    }

    public function format(AppNotification $notification): array
    {
        $category = $this->categoryFor($notification->event_type);
        $actionOutcome = $this->actionOutcome($notification);

        return [
            'id' => $notification->id,
            'title' => $notification->title,
            'message' => $notification->message,
            'event_type' => $notification->event_type,
            'target_url' => $notification->target_url,
            'read' => ! $notification->isUnread(),
            'created_at' => $notification->created_at->format('d/m/Y H:i'),
            'created_at_relative' => $notification->created_at->diffForHumans(),
            'created_at_iso' => $notification->created_at->toIso8601String(),
            'category' => $category,
            'category_label' => $this->categoryLabel($category),
            'action_outcome' => $actionOutcome,
            'action_outcome_label' => $this->actionOutcomeLabel($actionOutcome),
        ];
    }

    public function categoryFor(string $eventType): string
    {
        return match (true) {
            in_array($eventType, ['clock_recorded', 'manual_clock'], true) => 'fichaje',
            str_starts_with($eventType, 'absence_') => 'ausencia',
            str_starts_with($eventType, 'correction_') => 'correccion',
            default => 'general',
        };
    }

    public function categoryLabel(string $category): string
    {
        return match ($category) {
            'fichaje' => 'Fichaje',
            'ausencia' => 'Ausencias',
            'correccion' => 'Correcciones',
            default => 'General',
        };
    }

    public function actionOutcome(AppNotification $notification): string
    {
        return match ($notification->event_type) {
            'absence_request_pending', 'correction_request_pending' => 'pending_action',
            'absence_request_submitted', 'correction_request_submitted' => 'pending_review',
            'absence_request_reviewed', 'correction_request_reviewed' => str_contains(
                mb_strtolower($notification->title),
                'rechaz'
            ) ? 'rejected' : 'approved',
            default => 'info',
        };
    }

    public function actionOutcomeLabel(string $outcome): string
    {
        return match ($outcome) {
            'pending_action' => 'Requiere acción',
            'pending_review' => 'En revisión',
            'approved' => 'Aprobada',
            'rejected' => 'Rechazada',
            default => 'Informativa',
        };
    }
}
