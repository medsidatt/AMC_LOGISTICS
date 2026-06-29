import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import PageHeader from '@/components/ui/PageHeader';
import Tabs from '@/components/ui/Tabs';
import Pagination from '@/components/ui/Pagination';
import EmptyState from '@/components/ui/EmptyState';
import FuelFilters from './components/FuelFilters';
import FuelImportDrawer from './components/FuelImportDrawer';
import FuelDetailsDrawer from './components/FuelDetailsDrawer';
import { formatNumber } from '@/utils/formatters';
import { Fuel, Upload, Download, RefreshCw } from 'lucide-react';

interface Opt { id: number | string; name: string }
interface FuelRecord {
    id: number;
    date: string | null;
    truck: string | null;
    driver?: string | null;
    amount?: number;
    litres?: number;
    transaction_id?: string | null;
    kilometers?: number;
    consumed?: number;
    consumed_per_100km?: number | null;
    refills_volume?: number;
    refills_count?: number;
}

interface Props {
    tab: 'edk' | 'fleeti';
    records: { data: FuelRecord[]; current_page: number; last_page: number; per_page: number; total: number; from: number | null; to: number | null };
    filters: Record<string, string>;
    trucks: Opt[];
    drivers: Opt[];
    totals: { edk_transactions: number; edk_litres: number; edk_fcfa: number; fleeti_days: number; fleeti_litres: number };
    pricePerLitre: number;
}

function Stat({ label, value }: { label: string; value: string }) {
    return (
        <Card>
            <div className="text-xs text-[var(--color-text-muted)] uppercase tracking-wide">{label}</div>
            <div className="text-xl font-bold text-[var(--color-text)] mt-1">{value}</div>
        </Card>
    );
}

export default function FuelIndex({ tab, records, filters, trucks, drivers, totals, pricePerLitre }: Props) {
    const [importOpen, setImportOpen] = useState(false);
    const [details, setDetails] = useState<{ type: 'edk' | 'fleeti'; id: number } | null>(null);

    const switchTab = (t: string) => {
        const params: Record<string, string> = { tab: t, ...filters };
        if (t === 'fleeti') delete params.driver_id; // Fleeti has no driver dimension
        router.get('/fuel', params, { preserveState: true, preserveScroll: true });
    };

    const exportUrl = (() => {
        const p = new URLSearchParams({ tab });
        Object.entries(filters).forEach(([k, v]) => { if (v) p.set(k, String(v)); });
        return '/fuel/export?' + p.toString();
    })();

    const edkColumns: { label: string; align?: string; render: (r: FuelRecord) => React.ReactNode }[] = [
        { label: 'Date', render: (r) => r.date ?? '-' },
        { label: 'Camion', render: (r) => <span className="font-medium">{r.truck ?? '-'}</span> },
        { label: 'Chauffeur', render: (r) => r.driver ?? '-' },
        { label: 'FCFA', align: 'text-right', render: (r) => formatNumber(r.amount ?? 0, 0) },
        { label: 'Litres', align: 'text-right', render: (r) => <span className="font-medium">{formatNumber(r.litres ?? 0, 1)}</span> },
        { label: 'Transaction', render: (r) => <span className="text-[var(--color-text-muted)]">{r.transaction_id ?? '-'}</span> },
    ];
    const fleetiColumns: { label: string; align?: string; render: (r: FuelRecord) => React.ReactNode }[] = [
        { label: 'Date', render: (r) => r.date ?? '-' },
        { label: 'Camion', render: (r) => <span className="font-medium">{r.truck ?? '-'}</span> },
        { label: 'Km', align: 'text-right', render: (r) => formatNumber(r.kilometers ?? 0, 0) },
        { label: 'Consommé L', align: 'text-right', render: (r) => <span className="font-medium">{formatNumber(r.consumed ?? 0, 1)}</span> },
        { label: 'L/100km', align: 'text-right', render: (r) => r.consumed_per_100km != null ? formatNumber(r.consumed_per_100km, 1) : '-' },
        { label: 'Remplis L', align: 'text-right', render: (r) => (r.refills_volume ?? 0) > 0 ? `${formatNumber(r.refills_volume ?? 0, 0)} (${r.refills_count})` : '-' },
    ];
    const columns = tab === 'edk' ? edkColumns : fleetiColumns;

    return (
        <AuthenticatedLayout title="Carburant">
            <Head title="Carburant" />

            <PageHeader
                icon={<Fuel size={22} className="text-[var(--color-primary)]" />}
                title="Carburant"
                actions={
                    <>
                        <Button variant="ghost" icon={<RefreshCw size={15} />} onClick={() => router.reload()}>Actualiser</Button>
                        {records.total > 0 && (
                            <a href={exportUrl} className="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium transition" title="Exporter (CSV)">
                                <Download size={15} /> Export
                            </a>
                        )}
                        <Button icon={<Upload size={16} />} onClick={() => setImportOpen(true)}>Importer</Button>
                    </>
                }
            />

            <div className="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-4">
                <Stat label="Transactions EDK" value={formatNumber(totals.edk_transactions)} />
                <Stat label="Litres EDK" value={`${formatNumber(totals.edk_litres, 0)} L`} />
                <Stat label="Montant EDK" value={`${formatNumber(totals.edk_fcfa, 0)} F`} />
                <Stat label="Jours Fleeti" value={formatNumber(totals.fleeti_days)} />
                <Stat label="Litres Fleeti" value={`${formatNumber(totals.fleeti_litres, 0)} L`} />
            </div>

            <Tabs
                active={tab}
                onChange={switchTab}
                tabs={[
                    { key: 'edk', label: 'Transactions EDK', icon: <Fuel size={15} /> },
                    { key: 'fleeti', label: 'Consommation Fleeti', icon: <Download size={15} /> },
                ]}
                className="mb-4"
            />

            <FuelFilters tab={tab} filters={filters} trucks={trucks} drivers={drivers} />

            <Card padding={false}>
                <div className="p-5">
                    {records.data.length === 0 ? (
                        <EmptyState icon={<Fuel size={28} />} title="Aucun enregistrement" description="Importez des données EDK ou Fleeti, ou ajustez les filtres." />
                    ) : (
                        <div className="overflow-x-auto rounded-lg border border-[var(--color-border)]">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="bg-[var(--color-surface-hover)]">
                                        {columns.map((c) => (
                                            <th key={c.label} className={`px-4 py-3 text-xs font-semibold uppercase tracking-wider text-[var(--color-text-secondary)] ${c.align ?? 'text-left'}`}>{c.label}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-[var(--color-border)]">
                                    {records.data.map((r) => (
                                        <tr key={r.id} onClick={() => setDetails({ type: tab, id: r.id })} className="hover:bg-[var(--color-surface-hover)] transition-colors cursor-pointer">
                                            {columns.map((c) => (
                                                <td key={c.label} className={`px-4 py-3 text-[var(--color-text)] ${c.align ?? ''}`}>{c.render(r)}</td>
                                            ))}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
                <div className="px-5 pb-5">
                    <Pagination meta={records} />
                </div>
            </Card>

            {details && (
                <FuelDetailsDrawer type={details.type} id={details.id} onClose={() => setDetails(null)} />
            )}

            {importOpen && (
                <FuelImportDrawer pricePerLitre={pricePerLitre} onClose={() => setImportOpen(false)} />
            )}
        </AuthenticatedLayout>
    );
}
