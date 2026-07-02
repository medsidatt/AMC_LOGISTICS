<?php

namespace App\Domain\Operations\CommandCenters;

use App\Domain\Operations\CommandCenters\Contracts\BusinessEventSource;
use App\Domain\Operations\Contracts\BusinessEventInterface;
use App\Domain\Operations\Events\Derivers\Contracts\BusinessEventDeriver;
use App\Domain\Operations\Events\Derivers\Contracts\DerivationContextFactory;

/**
 * The single producer of Business Events (frozen architecture: Derivers → BusinessEventSource
 * → Operational Intelligence). A pure orchestrator: it obtains one derivation context from the
 * factory, invokes every injected deriver exactly once, and returns the merged, de-duplicated
 * event stream.
 *
 * It creates no context (the DerivationContextFactory owns the clock and period) and derives
 * nothing itself. It depends solely on the deriver abstraction and the context factory; it
 * never touches Eloquent, models, the database, calculators, read models, the KPI Registry,
 * Operational Intelligence, Translators, or Command Centers. No filtering, no prioritising, no
 * grouping, no severity ordering — merge and de-duplicate, nothing else. Same context + same
 * derived events → identical output.
 */
final class DerivedBusinessEventSource implements BusinessEventSource
{
    /** @param list<BusinessEventDeriver> $derivers invoked in the given, stable order */
    public function __construct(
        private readonly DerivationContextFactory $contextFactory,
        private readonly array $derivers,
    ) {}

    public function events(): iterable
    {
        $context = $this->contextFactory->current();

        $seen = [];
        $events = [];

        foreach ($this->derivers as $deriver) {
            foreach ($deriver->derive($context) as $event) {
                $key = $this->dedupeKey($event);
                if (isset($seen[$key])) {
                    continue; // an identical fact already surfaced — keep the first
                }
                $seen[$key] = true;
                $events[] = $event;
            }
        }

        return $events;
    }

    /** Identity of an operational fact: event type + affected entity. No time, no severity. */
    private function dedupeKey(BusinessEventInterface $event): string
    {
        return $event->id()->value.'|'.$event->entityType().'|'.($event->entityId() ?? '');
    }
}
