/**
 * R4.5 — presentation-only card for one descriptive BI metric. Displays the value the
 * Business Command Center already produced; no calculation, no formatting beyond plain display.
 */

export interface BusinessMetric {
    kpiId: string;
    value: number;
    unit: string;
    components: Record<string, number>;
}

export function BusinessMetricCard({ metric }: { metric: BusinessMetric }) {
    return (
        <li className="border rounded p-3 text-sm">
            <div className="flex justify-between gap-2">
                <span className="font-medium">{metric.kpiId}</span>
                <span className="text-xs uppercase opacity-70">{metric.unit}</span>
            </div>
            <div className="text-lg">{metric.value}</div>
        </li>
    );
}
