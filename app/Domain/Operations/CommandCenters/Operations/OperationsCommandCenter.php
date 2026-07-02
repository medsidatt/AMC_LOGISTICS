<?php

namespace App\Domain\Operations\CommandCenters\Operations;

use App\Domain\Operations\CommandCenters\Contracts\BusinessEventSource;
use App\Domain\Operations\CommandCenters\Contracts\OperationsCommandCenterInterface;
use App\Domain\Operations\Intelligence\Contracts\OperationalIntelligenceInterface;
use App\Domain\Operations\Translators\Operations\OperationsTranslator;
use Carbon\CarbonImmutable;

/**
 * The Operations Command Center. It ORCHESTRATES the frozen pipeline and nothing else:
 *
 *     facts (BusinessEventSource)
 *         → conclusions (Operational Intelligence)
 *         → view (Operations Translator)
 *         → OperationsDashboardResponse
 *
 * Zero business logic lives here. It does not calculate KPIs, derive events, instantiate
 * calculators or read models, query models, read the database / config / env, filter
 * business data, group, sort, or rank. Each collaborator owns its own step; the Command
 * Center only composes them and stamps the response with the generation time and schema
 * version. Structurally identical to the Executive Command Center reference implementation.
 */
final class OperationsCommandCenter implements OperationsCommandCenterInterface
{
    public function __construct(
        private readonly BusinessEventSource $source,
        private readonly OperationalIntelligenceInterface $intelligence,
        private readonly OperationsTranslator $translator,
    ) {}

    public function dashboard(): OperationsDashboardResponse
    {
        $conclusions = $this->intelligence->conclude($this->source->events());
        $view = $this->translator->translate($conclusions);

        return new OperationsDashboardResponse(
            $view,
            CarbonImmutable::now()->toDateTimeImmutable(),
        );
    }
}
