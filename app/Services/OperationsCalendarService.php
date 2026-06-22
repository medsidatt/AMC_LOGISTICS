<?php

namespace App\Services;

use App\Models\CalendarDay;
use App\Models\OperationsCalendar;
use Carbon\Carbon;

/**
 * Operational-day counting — the single source for pacing in the planning engine.
 * A day is operational when it is a working weekday (per the calendar's pattern)
 * unless a per-date override says otherwise. Holidays / shutdowns / exceptions are
 * non-working; a WORKING_DAY override forces a normally-off day to count.
 *
 * Fallback: with no configured calendar, every calendar day counts (so the engine
 * degrades to calendar-day math rather than breaking).
 */
class OperationsCalendarService
{
    public function defaultCalendar(): ?OperationsCalendar
    {
        return OperationsCalendar::where('is_default', true)->with('days')->first();
    }

    /** Whether a single date is an operational working day. */
    public function isOperational(Carbon $date, ?OperationsCalendar $calendar = null): bool
    {
        $calendar ??= $this->defaultCalendar();
        if (! $calendar) {
            return true; // fallback: no calendar ⇒ every day counts
        }

        $override = $calendar->days->first(fn (CalendarDay $d) => $d->date->isSameDay($date));
        if ($override) {
            return $override->day_type === CalendarDay::WORKING_DAY;
        }

        return in_array($date->isoWeekday(), $calendar->workingWeekdays(), true);
    }

    /**
     * Count operational working days in [start, end] (inclusive).
     */
    public function operationalDays(Carbon $start, Carbon $end, ?OperationsCalendar $calendar = null): int
    {
        $start = $start->copy()->startOfDay();
        $end = $end->copy()->startOfDay();
        if ($end->lt($start)) {
            return 0;
        }

        $calendar ??= $this->defaultCalendar();
        if (! $calendar) {
            return (int) $start->diffInDays($end) + 1; // fallback: calendar days
        }

        $working = $calendar->workingWeekdays();
        $overrides = $calendar->days->keyBy(fn (CalendarDay $d) => $d->date->toDateString());

        $count = 0;
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $override = $overrides->get($d->toDateString());
            if ($override) {
                if ($override->day_type === CalendarDay::WORKING_DAY) {
                    $count++;
                }
            } elseif (in_array($d->isoWeekday(), $working, true)) {
                $count++;
            }
        }

        return $count;
    }
}
