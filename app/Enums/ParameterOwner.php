<?php

namespace App\Enums;

/**
 * The department accountable for a parameter (the `owner` column).
 * Mirrors the KPI Catalog owners. A future admin UI scopes editing by owner.
 */
enum ParameterOwner: string
{
    case OPERATIONS = 'operations';
    case FINANCE = 'finance';
    case FLEET = 'fleet';
    case MAINTENANCE = 'maintenance';
    case HSE = 'hse';
    case EXECUTIVE = 'executive';
    case DISPATCH = 'dispatch';
}
