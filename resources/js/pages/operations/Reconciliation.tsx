import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import ReconciliationPanel, { type ReconciliationRow } from '@/components/operations/ReconciliationPanel';

interface Props {
    rows: ReconciliationRow[];
    counts: { expected: number; missing: number; matched: number };
}

export default function Reconciliation({ rows, counts }: Props) {
    return (
        <AuthenticatedLayout title="Réconciliation">
            <Head title="Réconciliation (GPS vs tickets)" />
            <ReconciliationPanel rows={rows} counts={counts} />
        </AuthenticatedLayout>
    );
}
