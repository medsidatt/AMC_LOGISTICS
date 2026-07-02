import { Head } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import PageHeader from '@/components/ui/PageHeader';
import Pagination from '@/components/ui/Pagination';
import EmptyState from '@/components/ui/EmptyState';
import Badge from '@/components/ui/Badge';
import ReviewDrawer from './components/ReviewDrawer';
import { formatNumber } from '@/utils/formatters';
import { ClipboardCheck } from 'lucide-react';

interface Opt { id: number | string; name: string }
interface Outcome { value: string; label: string; requires_truck: boolean }
interface ReviewRecord {
    id: number;
    date: string | null;
    truck: string | null;
    detected_plate: string | null;
    amount: number;
    type: string | null;
    findings: string[];
    imported_by: string | null;
}
interface Props {
    records: { data: ReviewRecord[]; current_page: number; last_page: number; per_page: number; total: number; from: number | null; to: number | null };
    filters: Record<string, string>;
    trucks: Opt[];
    outcomes: Outcome[];
}

const FINDING_LABELS: Record<string, string> = {
    UNKNOWN_TRUCK: 'Camion inconnu',
    INACTIVE_TRUCK: 'Camion inactif',
    CARD_MISMATCH: 'Carte incohérente',
    DRIVER_MISMATCH: 'Chauffeur incohérent',
};

export default function FuelReview({ records, trucks, outcomes }: Props) {
    const [selected, setSelected] = useState<number | null>(null);

    return (
        <AuthenticatedLayout title="Revue carburant">
            <Head title="Revue carburant" />

            <PageHeader icon={<ClipboardCheck size={22} className="text-[var(--color-primary)]" />} title="Revue carburant" />

            <Card padding={false}>
                <div className="p-5">
                    {records.data.length === 0 ? (
                        <EmptyState icon={<ClipboardCheck size={28} />} title="Aucune revue en attente" description="Toutes les transactions sont traitées." />
                    ) : (
                        <div className="overflow-x-auto rounded-lg border border-[var(--color-border)]">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="bg-[var(--color-surface-hover)]">
                                        {['Date', 'Camion', 'Montant', 'Type', 'Anomalies', 'Importé par'].map((h) => (
                                            <th key={h} className="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-[var(--color-text-secondary)] text-left">{h}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-[var(--color-border)]">
                                    {records.data.map((r) => (
                                        <tr key={r.id} onClick={() => setSelected(r.id)} className="hover:bg-[var(--color-surface-hover)] transition-colors cursor-pointer">
                                            <td className="px-4 py-3">{r.date ?? '-'}</td>
                                            <td className="px-4 py-3 font-medium">{r.truck ?? r.detected_plate ?? '-'}</td>
                                            <td className="px-4 py-3">{formatNumber(r.amount, 0)} F</td>
                                            <td className="px-4 py-3">{r.type ?? '-'}</td>
                                            <td className="px-4 py-3">
                                                <div className="flex flex-wrap gap-1">
                                                    {r.findings.map((f) => <Badge key={f} variant="warning">{FINDING_LABELS[f] ?? f}</Badge>)}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-[var(--color-text-muted)]">{r.imported_by ?? '-'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
                <div className="px-5 pb-5"><Pagination meta={records} /></div>
            </Card>

            {selected && <ReviewDrawer id={selected} outcomes={outcomes} trucks={trucks} onClose={() => setSelected(null)} />}
        </AuthenticatedLayout>
    );
}
