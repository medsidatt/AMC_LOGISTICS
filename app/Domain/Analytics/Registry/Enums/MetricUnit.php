<?php

namespace App\Domain\Analytics\Registry\Enums;

/**
 * The unit a descriptive (BI) metric is expressed in. Metadata only — formatting/precision
 * live in the (future) BI Report Translators, never here.
 *
 * Business Intelligence owns its own unit vocabulary (it does NOT reuse the operational
 * `KpiUnit` enum) so the two contexts evolve independently. Only the units the R4.1 design
 * approved are declared; fuel units (litres, litres-per-tonne) are added with the deferred
 * fuel KPIs, not before.
 */
enum MetricUnit: string
{
    case PERCENT = 'percent';
    case TONNES = 'tonnes';
    case COUNT = 'count';
    case CURRENCY = 'currency';
    case HOURS = 'hours';
    case DAYS = 'days';
    case RATIO = 'ratio';
}
