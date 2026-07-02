import { BusinessReport, type BusinessReportProps } from '@/components/business/BusinessReport';

/**
 * R4.5 — Executive BI report. Display only; reuses the shared BusinessReport body.
 */
export default function ExecutiveBusinessReport(props: BusinessReportProps) {
    return <BusinessReport title="Executive Report" report={props} />;
}
