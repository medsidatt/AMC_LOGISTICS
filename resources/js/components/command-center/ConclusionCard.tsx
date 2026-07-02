/**
 * Shared presentation for one Operational Conclusion card, rendered by every Command Center
 * page (Executive, Operations, …). Presentation only — it displays the fields the Command
 * Center response already produced; it holds no business logic, no calculation, no formatting
 * of business values. Extracted to remove the duplicated card markup the command-center pages
 * previously each carried.
 */

export interface Conclusion {
    id: string;
    kpi: string;
    event: string;
    question: string;
    headline: string;
    explanation: string;
    decision: string;
    requiredAction: string;
    drillDown: string;
    severity: string;
    impact: string;
    owner: string;
    priorityRank: number;
    immediate: boolean;
    entityType: string;
    entityId: number | string | null;
    occurredAt: string;
}

export function ConclusionCard({ card }: { card: Conclusion }) {
    return (
        <li className="border rounded p-3 text-sm">
            <div className="flex justify-between gap-2">
                <span className="font-medium">{card.headline}</span>
                <span className="uppercase text-xs">{card.severity}</span>
            </div>
            <div className="text-xs opacity-70">
                {card.kpi} · {card.owner} · rank {card.priorityRank}
            </div>
            <div className="mt-1">{card.explanation}</div>
            <div className="mt-1 text-xs">
                Action: {card.requiredAction} → {card.drillDown}
            </div>
        </li>
    );
}
