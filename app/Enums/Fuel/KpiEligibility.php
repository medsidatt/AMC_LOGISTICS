<?php

namespace App\Enums\Fuel;

/**
 * May this record influence operational Fuel KPIs? Decided ONLY by ClassificationPolicy.
 * The FuelReadModel (F2) reads this flag and NEVER inspects findings/type/source.
 */
enum KpiEligibility: string
{
    case ELIGIBLE = 'ELIGIBLE';
    case NOT_ELIGIBLE = 'NOT_ELIGIBLE';
}
