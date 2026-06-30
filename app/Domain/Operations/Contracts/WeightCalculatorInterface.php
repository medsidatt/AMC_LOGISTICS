<?php

namespace App\Domain\Operations\Contracts;

/**
 * Owns every weight-gap business rule. The single source for the operational
 * gap threshold and the violation test. Consumes only OperationalParameterService.
 */
interface WeightCalculatorInterface
{
    /** Operational weight-gap threshold, in tonnes. */
    public function gapThreshold(): float;

    /** Signed gap = client − provider (matches the stored `gap` column). */
    public function gap(float $providerWeight, float $clientWeight): float;

    /** True when |client − provider| exceeds the operational threshold. */
    public function isGapViolation(float $providerWeight, float $clientWeight): bool;
}
