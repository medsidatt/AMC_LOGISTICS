<?php

namespace App\Domain\Operations\CommandCenters\Executive;

use App\Domain\Operations\Translators\Executive\ExecutiveAlerts;
use App\Domain\Operations\Translators\Executive\ExecutivePriorities;
use App\Domain\Operations\Translators\Executive\ExecutiveSummary;
use App\Domain\Operations\Translators\Executive\ExecutiveView;
use App\Domain\Operations\Translators\Presentation\PresentationCard;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * The immutable, presentation-ready response of the Executive Command Center. It wraps the
 * translator's {@see ExecutiveView} and adds only response metadata (when it was generated
 * and the response-schema version).
 *
 * It performs NO business calculation. `toArray()` merely maps the already-built view value
 * objects to primitive arrays for JSON / Inertia — the serialization lives here so the
 * frozen Dashboard Translators are never modified.
 *
 * @phpstan-consistent-constructor
 */
final readonly class ExecutiveDashboardResponse
{
    /** Response-schema version — bump when the wire shape changes, not the data. */
    public const VERSION = 1;

    public function __construct(
        private ExecutiveView $view,
        private DateTimeImmutable $generatedAt,
        private int $version = self::VERSION,
    ) {}

    public function summary(): ExecutiveSummary
    {
        return $this->view->summary();
    }

    public function alerts(): ExecutiveAlerts
    {
        return $this->view->alerts();
    }

    public function priorities(): ExecutivePriorities
    {
        return $this->view->priorities();
    }

    public function generatedAt(): DateTimeImmutable
    {
        return $this->generatedAt;
    }

    public function version(): int
    {
        return $this->version;
    }

    /** Total conclusions represented (no conclusion dropped by the view). */
    public function total(): int
    {
        return $this->view->total();
    }

    /**
     * Presentation-ready payload for the controller. Pure mapping of view getters to
     * primitives — no calculation, no ranking, no filtering.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'generatedAt' => $this->generatedAt->format(DateTimeInterface::ATOM),
            'commandCenter' => $this->view->commandCenter()->value,
            'total' => $this->view->total(),
            'summary' => $this->summaryArray($this->view->summary()),
            'alerts' => array_map([$this, 'cardArray'], $this->view->alerts()->cards()),
            'priorities' => array_map([$this, 'cardArray'], $this->view->priorities()->cards()),
        ];
    }

    /** @return array<string, mixed> */
    private function summaryArray(ExecutiveSummary $summary): array
    {
        return [
            'total' => $summary->total(),
            'immediate' => $summary->immediate(),
            'bySeverity' => $summary->bySeverity(),
            'byOwner' => $summary->byOwner(),
        ];
    }

    /** @return array<string, mixed> */
    private function cardArray(PresentationCard $card): array
    {
        return [
            'id' => $card->conclusionId(),
            'kpi' => $card->kpiCode(),
            'event' => $card->eventCode(),
            'question' => $card->businessQuestion(),
            'headline' => $card->headline(),
            'explanation' => $card->explanation(),
            'decision' => $card->decision(),
            'requiredAction' => $card->requiredAction(),
            'drillDown' => $card->drillDownTarget(),
            'severity' => $card->severityLabel(),
            'impact' => $card->impactLabel(),
            'owner' => $card->ownerLabel(),
            'priorityRank' => $card->priorityRank(),
            'immediate' => $card->isImmediate(),
            'entityType' => $card->entityType(),
            'entityId' => $card->entityId(),
            'occurredAt' => $card->occurredAt()->format(DateTimeInterface::ATOM),
        ];
    }
}
