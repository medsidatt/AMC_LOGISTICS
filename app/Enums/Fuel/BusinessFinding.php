<?php

namespace App\Enums\Fuel;

/**
 * Operational-context anomalies — "does it fit our fleet model?". A business finding NEVER rejects the
 * record (financial truth is preserved); it disqualifies KPI eligibility and (by default) requires review.
 * Distinct from TechnicalFinding (data integrity).
 */
enum BusinessFinding: string
{
    case UNKNOWN_TRUCK = 'UNKNOWN_TRUCK';
    case INACTIVE_TRUCK = 'INACTIVE_TRUCK';
    case DRIVER_MISMATCH = 'DRIVER_MISMATCH';
    case CARD_MISMATCH = 'CARD_MISMATCH';

    /**
     * v1 default: every business anomaly is queued for a human. [A1] DRIVER_MISMATCH-alone is a
     * provisional policy row pending stakeholder confirmation (spec Phase 4) — change here if ruled log-only.
     */
    public function forcesReview(): bool
    {
        return true;
    }

    public function label(): string
    {
        return match ($this) {
            self::UNKNOWN_TRUCK => 'Camion inconnu',
            self::INACTIVE_TRUCK => 'Camion inactif',
            self::DRIVER_MISMATCH => 'Chauffeur incohérent',
            self::CARD_MISMATCH => 'Carte incohérente',
        };
    }
}
