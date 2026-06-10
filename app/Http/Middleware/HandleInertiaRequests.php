<?php

namespace App\Http\Middleware;

use App\Services\NotificationService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
                'panel' => fn () => match (true) {
                    $request->user()?->isAdmin() => [
                        'label' => 'Administración',
                        'route' => 'admin.index',
                    ],
                    $request->user()?->isManager() => [
                        'label' => 'Encargado',
                        'route' => 'manager.index',
                    ],
                    default => null,
                },
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'notifications' => fn () => $request->user()
                ? ['unread_count' => app(NotificationService::class)->unreadCount($request->user()->id)]
                : ['unread_count' => 0],
            'realtime' => [
                'enabled' => config('broadcasting.default') !== 'null',
            ],
        ];
    }
}
