<?php

namespace App\Domain\Operations\Events;

/** The kind of harm an event causes if ignored (mirrors the KPI Catalog failure-impact). */
enum BusinessImpact: string
{
    case FINANCIAL = 'financial';
    case OPERATIONAL = 'operational';
    case LEGAL = 'legal';
    case SAFETY = 'safety';
    case PLANNING = 'planning';
    case CUSTOMER = 'customer';
}
