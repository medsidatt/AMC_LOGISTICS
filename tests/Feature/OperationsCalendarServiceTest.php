<?php

namespace Tests\Feature;

use App\Models\Auth\User;
use App\Models\CalendarDay;
use App\Models\OperationsCalendar;
use App\Services\OperationsCalendarService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Operational-day counting. DatabaseTransactions keeps the dev DB clean (the
 * testing connection points at it). Dates: 2026-06-15 is a Monday.
 */
class OperationsCalendarServiceTest extends TestCase
{
    use DatabaseTransactions;

    private function svc(): OperationsCalendarService
    {
        return app(OperationsCalendarService::class);
    }

    private function defaultCalendar(): OperationsCalendar
    {
        return OperationsCalendar::where('is_default', true)->firstOrFail();
    }

    public function test_counts_monday_to_saturday_as_six(): void
    {
        // Mon 15 → Sun 21: Sunday is not a working weekday → 6.
        $days = $this->svc()->operationalDays(Carbon::parse('2026-06-15'), Carbon::parse('2026-06-21'));
        $this->assertSame(6, $days);
    }

    public function test_holiday_is_excluded(): void
    {
        CalendarDay::create([
            'calendar_id' => $this->defaultCalendar()->id,
            'date' => '2026-06-17', // Wednesday
            'day_type' => CalendarDay::HOLIDAY,
            'note' => 'Test holiday',
        ]);

        $days = $this->svc()->operationalDays(Carbon::parse('2026-06-15'), Carbon::parse('2026-06-21'));
        $this->assertSame(5, $days);
    }

    public function test_working_day_override_makes_sunday_count(): void
    {
        CalendarDay::create([
            'calendar_id' => $this->defaultCalendar()->id,
            'date' => '2026-06-21', // Sunday
            'day_type' => CalendarDay::WORKING_DAY,
            'note' => 'Exceptional Sunday shift',
        ]);

        $days = $this->svc()->operationalDays(Carbon::parse('2026-06-15'), Carbon::parse('2026-06-21'));
        $this->assertSame(7, $days);
    }

    public function test_inverted_range_is_zero(): void
    {
        $this->assertSame(0, $this->svc()->operationalDays(Carbon::parse('2026-06-21'), Carbon::parse('2026-06-15')));
    }

    public function test_is_operational_respects_weekday_pattern(): void
    {
        $this->assertTrue($this->svc()->isOperational(Carbon::parse('2026-06-15')));  // Monday
        $this->assertFalse($this->svc()->isOperational(Carbon::parse('2026-06-21'))); // Sunday
    }

    public function test_management_page_renders(): void
    {
        $user = User::query()->permission('fleet-settings-edit')->firstOrFail();

        $this->actingAs($user)
            ->get('/settings/operations-calendar')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/OperationsCalendar')
                ->has('calendar.working_weekdays')
                ->has('days'));
    }
}
