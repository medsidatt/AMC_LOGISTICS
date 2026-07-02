<?php

namespace App\Domain\Operations\CommandCenters\Operations;

use App\Domain\Operations\Translators\Operations\OperationalActions;
use App\Domain\Operations\Translators\Operations\OperationalProblems;
use App\Domain\Operations\Translators\Operations\OperationalQueues;
use App\Domain\Operations\Translators\Operations\OperationsView;
use App\Domain\Operations\Translators\Presentation\PresentationCard;
use App\Domain\Operations\Translators\Presentation\PresentationQueue;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * The immutable, presentation-ready response of the Operations Command Center. It wraps the
 * translator's {@see OperationsView} and adds only response metadata (when it was generated
 * and the response-schema version).
 *
 * It performs NO business calculation, grouping, or sorting — the queues/problems/actions
 * were already grouped and ordered by the Operations Translator. `toArray()` merely maps the
 * already-built view value objects to primitive arrays for JSON / Inertia; the serialization
 * lives here so the frozen Dashboard Translators are never modified.
 *
 * @phpstan-consistent-constructor
 */
final readonly class OperationsDashboardResponse
{
    /** Response-schema version — bump when the wire shape changes, not the data. */
    public const VERSION = 1;

    public function __construct(
        private OperationsView $view,
        private DateTimeImmutable $generatedAt,
        private int $version = self::VERSION,
    ) {}

    public function queues(): OperationalQueues
    {
        return $this->view->queues();
    }

    public function problems(): OperationalProblems
    {
        return $this->view->problems();
    }

    public function actions(): OperationalActions
    {
        return $this->view->actions();
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
     * primitives — no calculation, no grouping, no sorting, no filtering.
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
            'queues' => array_map([$this, 'queueArray'], $this->view->queues()->queues()),
            'problems' => array_map([$this, 'cardArray'], $this->view->problems()->cards()),
            'actions' => array_map([$this, 'cardArray'], $this->view->actions()->cards()),
        ];
    }

    /** @return array<string, mixed> */
    private function queueArray(PresentationQueue $queue): array
    {
        return [
            'key' => $queue->key(),
            'label' => $queue->label(),
            'count' => $queue->count(),
            'cards' => array_map([$this, 'cardArray'], $queue->cards()),
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
