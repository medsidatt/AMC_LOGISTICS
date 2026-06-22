<?php

namespace App\Http\Controllers;

use App\Models\CalendarDay;
use App\Models\OperationsCalendar;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Manage the (single, default) operations calendar: the working-week pattern and
 * the exception days (holidays / shutdowns / one-off working days) that pacing
 * and projection count against.
 */
class OperationsCalendarController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:fleet-settings-edit');
    }

    public function edit()
    {
        $calendar = OperationsCalendar::where('is_default', true)
            ->with(['days' => fn ($q) => $q->orderBy('date')])
            ->firstOrFail();

        return Inertia::render('settings/OperationsCalendar', [
            'calendar' => [
                'id' => $calendar->id,
                'name' => $calendar->name,
                'working_weekdays' => $calendar->workingWeekdays(),
            ],
            'days' => $calendar->days->map(fn (CalendarDay $d) => [
                'id' => $d->id,
                'date' => $d->date->toDateString(),
                'day_type' => $d->day_type,
                'note' => $d->note,
            ])->values(),
        ]);
    }

    public function updateWeekdays(Request $request)
    {
        $data = $request->validate([
            'working_weekdays' => ['required', 'array', 'min:1'],
            'working_weekdays.*' => ['integer', 'between:1,7'],
        ]);

        $calendar = OperationsCalendar::where('is_default', true)->firstOrFail();
        $calendar->update(['working_weekdays' => array_values(array_unique($data['working_weekdays']))]);

        return back()->with('success', 'Jours ouvrés mis à jour.');
    }

    public function storeDay(Request $request)
    {
        $calendar = OperationsCalendar::where('is_default', true)->firstOrFail();

        $data = $request->validate([
            'date' => ['required', 'date'],
            'day_type' => ['required', 'in:' . implode(',', [CalendarDay::WORKING_DAY, CalendarDay::HOLIDAY, CalendarDay::SHUTDOWN, CalendarDay::EXCEPTION])],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        CalendarDay::updateOrCreate(
            ['calendar_id' => $calendar->id, 'date' => $data['date']],
            ['day_type' => $data['day_type'], 'note' => $data['note'] ?? null],
        );

        return back()->with('success', 'Jour exceptionnel enregistré.');
    }

    public function destroyDay(CalendarDay $day)
    {
        $day->delete();

        return back()->with('success', 'Jour exceptionnel supprimé.');
    }
}
