<?php

namespace App\Domain\Operations\Translators\Presentation;

use App\Domain\Operations\Intelligence\OperationalConclusion;
use App\Domain\Operations\KPI\Enums\KpiOwner;

/**
 * Stateless presentation arrangement — ordering and grouping only. Every translator reuses
 * it so the deterministic ordering rule lives in exactly one place (Reuse > Create).
 *
 * These are presentation operations the translator layer is allowed to perform (group,
 * order, format). They are NOT business logic: ordering reads the priority RANK the
 * conclusion already carries (no scoring formula), grouping partitions by an identity the
 * conclusion already exposes (no derivation). No DB, no config, no calculators, no events.
 */
final class ConclusionArranger
{
    /**
     * Normalise any iterable of conclusions to a list, preserving input order.
     *
     * @param  iterable<OperationalConclusion>  $conclusions
     * @return list<OperationalConclusion>
     */
    public static function toList(iterable $conclusions): array
    {
        return is_array($conclusions) ? array_values($conclusions) : iterator_to_array($conclusions, false);
    }

    /**
     * Order by urgency: rank ascending (1 = most urgent), then oldest fact first, then the
     * stable conclusion id. Deterministic — same input always yields the same order. Uses
     * only pre-existing fields; it computes no urgency of its own.
     *
     * @param  list<OperationalConclusion>  $conclusions
     * @return list<OperationalConclusion>
     */
    public static function byUrgency(array $conclusions): array
    {
        usort($conclusions, static function (OperationalConclusion $a, OperationalConclusion $b): int {
            return [$a->priorityRank(), $a->occurredAt(), $a->id()]
                <=> [$b->priorityRank(), $b->occurredAt(), $b->id()];
        });

        return $conclusions;
    }

    /**
     * Group by the KPI the conclusion already names, preserving first-seen KPI order and
     * within-group input order. Grouping only — no logic.
     *
     * @param  list<OperationalConclusion>  $conclusions
     * @return array<string, list<OperationalConclusion>> KPI code => conclusions
     */
    public static function byKpi(array $conclusions): array
    {
        $groups = [];
        foreach ($conclusions as $c) {
            $groups[$c->kpi()->value][] = $c;
        }

        return $groups;
    }

    /**
     * The subset owned by one department, in input order. Selection by the owner the
     * conclusion already names — a partition, not a decision.
     *
     * @param  list<OperationalConclusion>  $conclusions
     * @return list<OperationalConclusion>
     */
    public static function withOwner(array $conclusions, KpiOwner $owner): array
    {
        return array_values(array_filter(
            $conclusions,
            static fn (OperationalConclusion $c): bool => $c->owner() === $owner,
        ));
    }

    /**
     * The subset flagged immediate by the conclusion's own priority policy (critical/high),
     * ordered by urgency. Selection by an existing flag — not a threshold decision.
     *
     * @param  list<OperationalConclusion>  $conclusions
     * @return list<OperationalConclusion>
     */
    public static function immediate(array $conclusions): array
    {
        $immediate = array_values(array_filter(
            $conclusions,
            static fn (OperationalConclusion $c): bool => $c->priority()->isImmediate(),
        ));

        return self::byUrgency($immediate);
    }

    /**
     * The subset NOT flagged immediate — the forward-looking "warning" items (medium/low/
     * informational), ordered by urgency. The complement of {@see immediate()}; selection by
     * an existing flag, not a threshold decision.
     *
     * @param  list<OperationalConclusion>  $conclusions
     * @return list<OperationalConclusion>
     */
    public static function nonImmediate(array $conclusions): array
    {
        $warnings = array_values(array_filter(
            $conclusions,
            static fn (OperationalConclusion $c): bool => ! $c->priority()->isImmediate(),
        ));

        return self::byUrgency($warnings);
    }

    /**
     * Map conclusions to cards, ordered by urgency.
     *
     * @param  list<OperationalConclusion>  $conclusions
     * @return list<PresentationCard>
     */
    public static function cards(array $conclusions): array
    {
        return array_map(
            static fn (OperationalConclusion $c): PresentationCard => PresentationCard::fromConclusion($c),
            self::byUrgency($conclusions),
        );
    }

    /**
     * Build one queue per KPI, each labelled by that KPI's business question, cards ordered
     * by urgency. Queues are emitted in first-seen KPI order for determinism.
     *
     * @param  list<OperationalConclusion>  $conclusions
     * @return list<PresentationQueue>
     */
    public static function queuesByKpi(array $conclusions): array
    {
        $queues = [];
        foreach (self::byKpi($conclusions) as $kpiCode => $group) {
            $queues[] = new PresentationQueue(
                $kpiCode,
                $group[0]->finding()->businessQuestion(),
                self::cards($group),
            );
        }

        return $queues;
    }
}
