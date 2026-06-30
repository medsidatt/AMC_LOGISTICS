<?php

namespace App\Enums;

/**
 * Controlled vocabulary for a parameter's unit (the `unit` column).
 * Never free text — the admin UI and any display layer rely on these.
 */
enum ParameterUnit: string
{
    case TONNES = 'tonnes';
    case KILOGRAMS = 'kg';
    case RATIO = 'ratio';
    case PERCENT = 'percent';
    case DAYS = 'days';
    case HOURS = 'hours';
    case KILOMETRES = 'km';
    case ROTATIONS_PER_WEEK = 'rotations/week';
    case ROTATIONS = 'rotations';
    case DAY_OF_MONTH = 'day-of-month';
    case CURRENCY_PER_LITRE = 'currency/litre';
}
