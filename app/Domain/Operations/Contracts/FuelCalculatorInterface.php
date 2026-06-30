<?php

namespace App\Domain\Operations\Contracts;

/**
 * Owns fuel efficiency calculations. yieldPerTonne is the single owner of litres/tonne,
 * duplicated today in Truck/Fleet KPI services. Pure — no Eloquent, SQL, config, events.
 * (litres/km and abnormal-consumption methods will be added with their consumers.)
 */
interface FuelCalculatorInterface
{
    /** Litres per tonne delivered; null when there is no tonnage. */
    public function yieldPerTonne(float $litres, float $tonnage): ?float;
}
