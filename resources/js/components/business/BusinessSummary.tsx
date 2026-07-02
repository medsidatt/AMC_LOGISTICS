/**
 * R4.5 — presentation-only report header. Displays the report identity and structural counts
 * the Report Translator already produced; no calculation.
 */

export interface BusinessReportSummary {
    reportKey: string;
    title: string;
    sectionCount: number;
    metricCount: number;
    trendCount: number;
}

export function BusinessSummary({ summary, generatedAt, version }: { summary: BusinessReportSummary; generatedAt: string; version: number }) {
    return (
        <header>
            <h1 className="text-xl font-semibold">{summary.title}</h1>
            <p className="text-xs opacity-70">
                {summary.reportKey} · v{version} · generated {generatedAt} · {summary.sectionCount} section(s) ·{' '}
                {summary.metricCount} metric(s) · {summary.trendCount} trend(s)
            </p>
        </header>
    );
}
