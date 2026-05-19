<?php

namespace App\Services;

use App\Models\ObjectiveHistory;
use Illuminate\Database\Eloquent\Model;

/**
 * Records every change to "objective" fields (fleet defaults, per-truck
 * targets, client demand tonnages) with a mandatory note, so the team
 * can prove who decided what at any later date.
 */
class ObjectiveHistoryService
{
    /**
     * Record one field change. Skipped silently when old == new.
     * Returns the created entry or null if nothing changed.
     */
    public function record(
        ?Model $subject,
        string $subjectLabel,
        string $fieldName,
        string $fieldLabel,
        mixed $oldValue,
        mixed $newValue,
        string $note,
        array $context = [],
        ?int $userId = null,
    ): ?ObjectiveHistory {
        if ($this->same($oldValue, $newValue)) {
            return null;
        }

        $direction = $this->direction($oldValue, $newValue);
        $magnitude = $this->magnitude($oldValue, $newValue);

        return ObjectiveHistory::create([
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->getKey(),
            'subject_label' => $subjectLabel,
            'field_name' => $fieldName,
            'field_label' => $fieldLabel,
            'old_value' => $oldValue === null ? null : (string) $oldValue,
            'new_value' => $newValue === null ? null : (string) $newValue,
            'magnitude' => $magnitude,
            'direction' => $direction,
            'note' => $note,
            'context' => $context,
            'user_id' => $userId ?? auth()->id(),
            'changed_at' => now(),
        ]);
    }

    private function same(mixed $a, mixed $b): bool
    {
        if ($a === null && $b === null) return true;
        if (is_numeric($a) && is_numeric($b)) {
            return (float) $a === (float) $b;
        }
        return (string) $a === (string) $b;
    }

    private function direction(mixed $old, mixed $new): string
    {
        if (is_numeric($old) && is_numeric($new)) {
            if ((float) $new > (float) $old) return ObjectiveHistory::DIRECTION_INCREASE;
            if ((float) $new < (float) $old) return ObjectiveHistory::DIRECTION_DECREASE;
        }
        return ObjectiveHistory::DIRECTION_NEUTRAL;
    }

    private function magnitude(mixed $old, mixed $new): ?float
    {
        if (! is_numeric($old) || ! is_numeric($new)) {
            return null;
        }
        return round(abs((float) $new - (float) $old), 2);
    }
}
