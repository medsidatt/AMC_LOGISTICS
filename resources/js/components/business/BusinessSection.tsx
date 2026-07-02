import { BusinessMetricCard, type BusinessMetric } from './BusinessMetricCard';
import { BusinessTrendCard, type BusinessTrend } from './BusinessTrendCard';

/**
 * R4.5 — renders one report section (metric + trend cards). Presentation only; reuses the
 * shared metric/trend card components.
 */

export interface BusinessReportSection {
    key: string;
    title: string;
    metrics: BusinessMetric[];
    trends: BusinessTrend[];
}

export function BusinessSection({ section }: { section: BusinessReportSection }) {
    return (
        <section>
            <h2 className="text-sm font-semibold mb-2">{section.title}</h2>
            {section.metrics.length === 0 ? null : (
                <ul className="space-y-2">
                    {section.metrics.map((m) => <BusinessMetricCard key={m.kpiId} metric={m} />)}
                </ul>
            )}
            {section.trends.length === 0 ? null : (
                <ul className="space-y-2 mt-2">
                    {section.trends.map((t) => <BusinessTrendCard key={`trend-${t.kpiId}`} trend={t} />)}
                </ul>
            )}
        </section>
    );
}
