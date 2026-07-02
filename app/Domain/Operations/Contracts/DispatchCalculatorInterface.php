<?php

namespace App\Domain\Operations\Contracts;

/**
 * Owns dispatch readiness / completion arithmetic. Pure — the caller supplies the
 * already-counted planned/started/completed/assigned totals (from the Dispatch Read
 * Model). No Eloquent, SQL, config, env, or app().
 *
 * No consumer migrated in this increment: dispatch efficiency (DSP-300/301) is a new
 * outcome wired when its KPI is built. This calculator stands as the owner.
 */
interface DispatchCalculatorInterface
{
    /** Share of planned dispatches that have started; 0 when nothing is planned. */
    public function startRate(int $started, int $planned): float;

    /** Share of planned dispatches completed (dispatch efficiency); 0 when nothing is planned. */
    public function completionRate(int $completed, int $planned): float;

    /** Share of required assignments filled; 0 when none required. */
    public function assignmentCompletion(int $assigned, int $required): float;

    /**
     * Whether a planned dispatch has not started — i.e. no live movement status has been
     * recorded yet. Owns the "started vs not-started" classification over the raw status.
     */
    public function isNotStarted(?string $currentStatus): bool;
}
