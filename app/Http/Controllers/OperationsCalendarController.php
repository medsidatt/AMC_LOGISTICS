<?php

namespace App\Http\Controllers;

use App\Models\CalendarDay;
use App\Models\OperationsCalendar;
use Illuminate\Http\Request;

/**
 * Operations calendar writes (the single, default calendar): the working-week
 * pattern and the exception days (holidays / shutdowns / one-off working days) that
 * pacing and projection count against. The editor UI lives at /planning/calendar
 * (Planning owns the calendar); this controller owns only the writes.
 */
class OperationsCalendarController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:fleet-settings-edit');
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
