<?php

namespace App\Http\Controllers;

use App\Models\TimeRecord;
use App\Models\EmployeeDaySchedule;
use App\Models\ScheduleException;
use App\Models\Holiday;
use App\Services\AbsenceScheduleService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CalendarController extends Controller
{
    /**
     * Display the calendar page.
     *
     * @return \Inertia\Inertia\Response
     */
    public function index()
    {
        return Inertia::render('Calendar/Index');
    }

    /**
     * Get events for a given month and year via JSON API.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function events(Request $request)
    {
        $month = $request->query('month');
        $year = $request->query('year');

        if (!$month || !$year) {
            return response()->json([]);
        }

        try {
            $user = $request->user();
            $employeeId = $user->id;
            $startDate = new \DateTime("$year-$month-01");
            $endDate = (clone $startDate)->modify('last day of this month');
            $startCarbon = Carbon::parse($startDate->format('Y-m-d'));
            $endCarbon = Carbon::parse($endDate->format('Y-m-d'));
            $absenceSchedule = app(AbsenceScheduleService::class);

            $schedules = EmployeeDaySchedule::where('employee_id', $employeeId)
                ->where('active', true)
                ->get();

            $exceptions = ScheduleException::where('employee_id', $employeeId)
                ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->get();

            $exceptionTypes = $exceptions->reduce(function ($carry, $exception) {
                $carry[$exception->date->format('Y-m-d')] = $exception->type;

                return $carry;
            }, []);

            $employee = \App\Models\Employee::findOrFail($employeeId);
            $workCalendarId = $employee->work_calendar_id;

            $holidays = Holiday::where('work_calendar_id', $workCalendarId)
                ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->get()
                ->map(fn($h) => [
                    'date' => $h->date->format('Y-m-d'),
                    'name' => $h->name
                ])
                ->toArray();

            $holidayDates = collect($holidays)->pluck('date')->flip()->all();

            $workDays = [];
            $tempDate = clone $startDate;
            while ($tempDate <= $endDate) {
                if ($this->isScheduledWorkDay($tempDate, $schedules, $exceptionTypes, $holidayDates)) {
                    $workDays[] = $tempDate->format('Y-m-d');
                }
                $tempDate->modify('+1 day');
            }

            $records = TimeRecord::where('employee_id', $employeeId)
                ->active()
                ->whereBetween('recorded_at', [$startDate->format('Y-m-d 00:00:00'), $endDate->format('Y-m-d 23:59:59')])
                ->get()
                ->unique(fn (TimeRecord $t) => $t->recorded_at->format('Y-m-d'))
                ->map(fn (TimeRecord $t) => [
                    'date' => $t->recorded_at->format('Y-m-d'),
                ])
                ->values()
                ->all();

            $clockedDates = collect($records)->pluck('date')->values()->all();

            $absences = $absenceSchedule
                ->absencesInRange($user, $startCarbon, $endCarbon)
                ->map(fn (array $a) => [
                    'date' => $a['date'],
                    'type' => $a['type'],
                    'label' => $a['label'],
                ])
                ->values()
                ->all();

            $teamAbsences = $absenceSchedule
                ->teamAbsencesInRange($user, $startCarbon, $endCarbon)
                ->values()
                ->all();

            $today = today()->toDateString();

            $missedDays = collect($workDays)
                ->filter(fn (string $date) => $date < $today && ! in_array($date, $clockedDates, true))
                ->values()
                ->all();

            return response()->json([
                'work_days' => $workDays,
                'holidays' => $holidays,
                'records' => $records,
                'clocked_dates' => $clockedDates,
                'missed_days' => $missedDays,
                'today' => $today,
                'absences' => $absences,
                'team_absences' => $teamAbsences,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get detailed events for a specific day via JSON API.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function dayEvents(Request $request)
    {
        $date = $request->query('date');

        if (!$date) {
            return response()->json([]);
        }

        try {
            $user = $request->user();
            $absenceSchedule = app(AbsenceScheduleService::class);

            $records = TimeRecord::where('employee_id', $user->id)
                ->active()
                ->whereDate('recorded_at', $date)
                ->orderBy('recorded_at')
                ->get()
                ->map(function ($record) {
                    return [
                        'id' => $record->id,
                        'type' => (int) $record->type,
                        'work_date' => $record->recorded_at->format('Y-m-d'),
                        'recorded_at' => $record->recorded_at->format('H:i'),
                        'clock_method' => $record->clock_method,
                        'origin' => $record->origin,
                        'es_correccion' => $record->origin === 'correction',
                        'device_id' => $record->device_id,
                        'ip' => $record->ip,
                    ];
                })
                ->toArray();

            return response()->json([
                'records' => $records,
                'absence' => $absenceSchedule->absenceOnDate($user, Carbon::parse($date)),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, EmployeeDaySchedule>  $schedules
     * @param  array<string, string>  $exceptionTypes
     */
    private function isScheduledWorkDay(
        \DateTime $date,
        $schedules,
        array $exceptionTypes,
        array $holidayDates = [],
    ): bool {
        $dateStr = $date->format('Y-m-d');
        $weekday = (int) $date->format('w');

        if (isset($holidayDates[$dateStr])) {
            return false;
        }

        $exceptionType = $exceptionTypes[$dateStr] ?? null;

        if (AbsenceScheduleService::isNonWorkAbsenceType($exceptionType)) {
            return false;
        }

        if ($schedules->isEmpty()) {
            return $weekday >= 1 && $weekday <= 5;
        }

        $inRange = $schedules->contains(function ($schedule) use ($dateStr, $weekday) {
            if (! $schedule->start_date) {
                return false;
            }

            $starts = $dateStr >= $schedule->start_date->format('Y-m-d');
            $ends = $schedule->end_date === null
                || $dateStr <= $schedule->end_date->format('Y-m-d');

            return (int) $schedule->weekday === $weekday && $schedule->active && $starts && $ends;
        });

        if ($inRange) {
            return true;
        }

        $earliestStart = $schedules
            ->filter(fn ($s) => $s->start_date)
            ->min(fn ($s) => $s->start_date->format('Y-m-d'));

        if ($earliestStart && $dateStr >= $earliestStart) {
            return $schedules->contains(
                fn ($schedule) => (int) $schedule->weekday === $weekday && $schedule->active,
            );
        }

        return false;
    }
}
