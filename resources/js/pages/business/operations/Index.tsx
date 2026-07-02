import { BusinessReport, type BusinessReportProps } from '@/components/business/BusinessReport';

/**
 * R4.5 — Operations BI report. Display only; reuses the shared BusinessReport body.
 */
export default function OperationsBusinessReport(props: BusinessReportProps) {
    return <BusinessReport title="Operations Report" report={props} />;
}
