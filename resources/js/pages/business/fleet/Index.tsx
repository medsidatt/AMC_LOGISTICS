import { BusinessReport, type BusinessReportProps } from '@/components/business/BusinessReport';

/**
 * R4.5 — Fleet BI report. Display only; reuses the shared BusinessReport body.
 */
export default function FleetBusinessReport(props: BusinessReportProps) {
    return <BusinessReport title="Fleet Report" report={props} />;
}
