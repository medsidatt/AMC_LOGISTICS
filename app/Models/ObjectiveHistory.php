<?php

namespace App\Models;

use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ObjectiveHistory extends Model
{
    protected $table = 'objective_history_entries';

    protected $guarded = [];

    protected $casts = [
        'context' => 'array',
        'changed_at' => 'datetime',
        'magnitude' => 'decimal:2',
    ];

    public const DIRECTION_INCREASE = 'increase';
    public const DIRECTION_DECREASE = 'decrease';
    public const DIRECTION_NEUTRAL = 'neutral';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Polymorphic subject ad-hoc resolver (we keep a string label and id
     * rather than a true morphTo, because the subject may be a singleton
     * like FleetSetting that doesn't fit cleanly).
     */
    public function subjectModel()
    {
        if (! $this->subject_type || ! $this->subject_id) {
            return null;
        }
        if (! class_exists($this->subject_type)) {
            return null;
        }
        return $this->subject_type::find($this->subject_id);
    }
}
