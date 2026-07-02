<?php

namespace App\Enums\Fuel;

/** Should this record enter the canonical financial ledger? Decided ONLY by ClassificationPolicy. */
enum PersistenceDecision: string
{
    case ACCEPT = 'ACCEPT';
    case REJECT = 'REJECT';
}
