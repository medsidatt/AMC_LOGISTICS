<?php

namespace App\Domain\Operations\Parameters;

use App\Enums\OperationalParameterKey;

/**
 * The one mapping between legacy FleetSetting columns and OperationalParameter keys.
 *
 * Used by both the sync command and the FleetSettings dual-write so the mapping
 * exists exactly once (no duplicated logic). FleetSetting is temporary compatibility
 * storage during migration; OperationalParameter is the future source of truth (ADR-008).
 */
final class FleetSettingParameterMap
{
    /**
     * @return array<string, OperationalParameterKey> FleetSetting column => parameter key
     */
    public static function map(): array
    {
        return [
            'default_capacity_tonnage' => OperationalParameterKey::DEFAULT_CAPACITY,
            'weight_gap_threshold' => OperationalParameterKey::WEIGHT_OPERATIONAL_THRESHOLD,
            'target_rotations_per_week' => OperationalParameterKey::TARGET_ROTATIONS,
            'price_per_litre' => OperationalParameterKey::PRICE_PER_LITRE,
            'monthly_target_tonnage' => OperationalParameterKey::MONTHLY_TARGET_TONNAGE,
        ];
    }
}
