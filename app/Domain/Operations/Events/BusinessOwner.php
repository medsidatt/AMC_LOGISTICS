<?php

namespace App\Domain\Operations\Events;

/** The single department accountable for an event (mirrors the KPI Catalog owners). */
enum BusinessOwner: string
{
    case OPERATIONS = 'operations';
    case FINANCE = 'finance';
    case FLEET = 'fleet';
    case MAINTENANCE = 'maintenance';
    case HSE = 'hse';
    case EXECUTIVE = 'executive';
    case DISPATCH = 'dispatch';
}
