<?php

namespace App\Http\Controllers;

use App\Services\CalendarService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    public function __construct(private CalendarService $calendar) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Calendar/Index', [
            'esEquipo' => $this->calendar->canSeeTeam($user),
            'esAdmin' => $user->isAdmin(),
        ]);
    }

    public function events(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        return response()->json(
            $this->calendar->monthPayload(
                $request->user(),
                (int) $validated['year'],
                (int) $validated['month'],
            ),
        );
    }

    public function dayEvents(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        return response()->json(
            $this->calendar->dayPayload(
                $request->user(),
                Carbon::createFromFormat('Y-m-d', $validated['date'])->startOfDay(),
            ),
        );
    }
}
