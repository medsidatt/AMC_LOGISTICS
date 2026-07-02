<?php

namespace App\Enums\Fuel;

/** Does a human need to investigate this record? Decided ONLY by ClassificationPolicy. */
enum ReviewDecision: string
{
    case NONE = 'NONE';
    case REQUIRED = 'REQUIRED';
}
