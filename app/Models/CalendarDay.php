<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A per-date exception to a calendar's default weekly pattern: a non-working
 * closure (holiday / shutdown / exception) or a one-off working day.
 */
class CalendarDay extends Model
{
    public const WORKING_DAY = 'WORKING_DAY';
    public const HOLIDAY     = 'HOLIDAY';
    public const SHUTDOWN    = 'SHUTDOWN';
    public const EXCEPTION   = 'EXCEPTION';

    /** Override types that make a day non-working. */
    public const NON_WORKING = [self::HOLIDAY, self::SHUTDOWN, self::EXCEPTION];

    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
    ];

    public function calendar(): BelongsTo
    {
        return $this->belongsTo(OperationsCalendar::class, 'calendar_id');
    }
}
