<?php

namespace App\Domain\Operations\Translators\Contracts;

use App\Domain\Operations\KPI\Enums\CommandCenter;

/**
 * A presentation-neutral view model for one command center — the single object a
 * Dashboard Translator returns. It carries only immutable value objects built from
 * Operational Conclusions; it holds no logic, no formulas, and no framework/UI concerns.
 *
 * Concrete views (ExecutiveView, FleetView, …) expose the named products the command
 * center renders. `commandCenter()` names the destination; it does not choose it.
 */
interface DashboardView
{
    /** The command center this view is prepared for. Metadata only — never rendering. */
    public function commandCenter(): CommandCenter;

    /** Total conclusions represented by this view (no conclusion is dropped). */
    public function total(): int;
}
