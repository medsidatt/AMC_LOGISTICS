<?php

namespace App\Domain\Operations\CommandCenters\Executive;

use App\Domain\Operations\CommandCenters\Contracts\BusinessEventSource;
use App\Domain\Operations\CommandCenters\Contracts\ExecutiveCommandCenterInterface;
use App\Domain\Operations\Intelligence\Contracts\OperationalIntelligenceInterface;
use App\Domain\Operations\Translators\Executive\ExecutiveTranslator;
use Carbon\CarbonImmutable;

/**
 * The Executive Command Center. It ORCHESTRATES the frozen pipeline and nothing else:
 *
 *     facts (BusinessEventSource)
 *         → conclusions (Operational Intelligence)
 *         → view (Executive Translator)
 *         → ExecutiveDashboardResponse
 *
 * Zero business logic lives here. It does not calculate KPIs, derive events, instantiate
 * calculators or read models, query models, read the database / config / env, filter
 * business data, rank priorities, or build charts. Each collaborator owns its own step; the
 * Command Center only composes them and stamps the response with the generation time and
 * schema version.
 */
final class ExecutiveCommandCenter implements ExecutiveCommandCenterInterface
{
    public function __construct(
        private readonly BusinessEventSource $source,
        private readonly OperationalIntelligenceInterface $intelligence,
        private readonly ExecutiveTranslator $translator,
    ) {}

    public function dashboard(): ExecutiveDashboardResponse
    {
        $conclusions = $this->intelligence->conclude($this->source->events());
        $view = $this->translator->translate($conclusions);

        return new ExecutiveDashboardResponse(
            $view,
            CarbonImmutable::now()->toDateTimeImmutable(),
        );
    }
}
