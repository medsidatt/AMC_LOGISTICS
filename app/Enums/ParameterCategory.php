<?php

namespace App\Enums;

/**
 * Controlled vocabulary for a parameter's value domain (the `category` column).
 * A future admin UI groups parameters by these. Never free text.
 */
enum ParameterCategory: string
{
    case CAPACITY = 'capacity';
    case WEIGHT = 'weight';
    case ROTATIONS = 'rotations';
    case OBJECTIVE = 'objective';
    case FINANCE = 'finance';
    case FISCAL = 'fiscal';
    case CYCLE = 'cycle';
    case INSPECTION = 'inspection';
    case MAINTENANCE = 'maintenance';
}
