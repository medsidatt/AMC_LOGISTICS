/**
 * R4.5 — presentation-only card for one metric's movement. Displays the trend facts the
 * Trend Calculator already produced; no calculation.
 */

export interface BusinessTrend {
    kpiId: string;
    currentValue: number;
    previousValue: number;
    difference: number;
    percentChange: number;
    direction: string;
}

const ARROW: Record<string, string> = { up: '▲', down: '▼', stable: '▬' };

export function BusinessTrendCard({ trend }: { trend: BusinessTrend }) {
    return (
        <li className="border rounded p-3 text-sm">
            <div className="flex justify-between gap-2">
                <span className="font-medium">{trend.kpiId}</span>
                <span className="text-xs uppercase opacity-70">
                    {ARROW[trend.direction] ?? ''} {trend.direction}
                </span>
            </div>
            <div className="text-xs opacity-70">
                {trend.previousValue} → {trend.currentValue} (Δ {trend.difference} · {trend.percentChange}%)
            </div>
        </li>
    );
}
