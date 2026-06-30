<?php

namespace App\Domain\Operations\Contracts;

/**
 * Owns cycle calculations (average cycle days / turnaround). Pure over the rotation
 * data supplied by the caller — no Eloquent, SQL, config, or events.
 */
interface CycleCalculatorInterface
{
    /**
     * Average days between consecutive rotations (previous client date → next provider date).
     * Null when fewer than two dated rotations exist.
     *
     * @param  iterable<int, object>  $rotations  items exposing provider_date / client_date
     */
    public function averageCycleDays(iterable $rotations): ?float;
}
