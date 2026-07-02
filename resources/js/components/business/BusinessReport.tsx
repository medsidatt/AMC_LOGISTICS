import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import { BusinessSummary, type BusinessReportSummary } from './BusinessSummary';
import { BusinessSection, type BusinessReportSection } from './BusinessSection';

/**
 * R4.5 — shared BI report body: renders the summary and sections a Business Command Center
 * produced. Presentation only; reused by every BI dashboard page so the layout lives once.
 */

export interface BusinessReportProps {
    reportKey: string;
    version: number;
    generatedAt: string;
    summary: BusinessReportSummary;
    sections: BusinessReportSection[];
}

export function BusinessReport({ title, report }: { title: string; report: BusinessReportProps }) {
    return (
        <AuthenticatedLayout>
            <Head title={title} />
            <div className="space-y-6 max-w-4xl">
                <BusinessSummary summary={report.summary} generatedAt={report.generatedAt} version={report.version} />
                {report.sections.map((section) => <BusinessSection key={section.key} section={section} />)}
            </div>
        </AuthenticatedLayout>
    );
}
