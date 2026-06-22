<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An operations calendar: a default working-week pattern plus exception days.
 * Single default calendar today; schema supports multiple (site/contract) later.
 */
class OperationsCalendar extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_default' => 'boolean',
        'working_weekdays' => 'array',
    ];

    /** ISO weekdays worked when no per-day override applies. Falls back to Mon–Sat. */
    public function workingWeekdays(): array
    {
        $days = $this->working_weekdays;
        return ! empty($days) ? array_map('intval', $days) : [1, 2, 3, 4, 5, 6];
    }

    public function days(): HasMany
    {
        return $this->hasMany(CalendarDay::class, 'calendar_id');
    }
}
