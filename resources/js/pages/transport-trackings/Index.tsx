import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import FormSelect from '@/components/ui/FormSelect';
import ActionButtons from '@/components/ui/ActionButtons';
import ConfirmDialog from '@/components/ui/ConfirmDialog';
import Pagination from '@/components/ui/Pagination';
import { Plus, FileText, Image, Filter, ChevronUp, ChevronDown, ChevronsUpDown, Search, X, Download } from 'lucide-react';
import { exportToCsv } from '@/utils/csv-export';
import { clsx } from 'clsx';

interface Document {
    id: number;
    original_name: string;
    mime_type: string;
    type: string;
    file_url: string;
}

interface Tracking {
    id: number;
    reference: string;
    product: string;
    base: string;
    provider_date: string | null;
    client_date: string | null;
    provider_net_weight: number | null;
    client_net_weight: number | null;
    gap: number;
    has_files: boolean;
    documents: Document[];
    truck: { id: number; matricule: string } | null;
    driver: { id: number; name: string } | null;
    provider: { id: number; name: string } | null;
}

interface DropdownItem {
    id: number | string;
    name?: string;
    matricule?: string;
}

interface Props {
    trackings: { data: Tracking[]; current_page: number; last_page: number; per_page: number; total: number; from: number | null; to: number | null };
    filters: Record<string, string>;
    sort: { by: string; dir: string };
    transporters: DropdownItem[];
    trucks: DropdownItem[];
    drivers: DropdownItem[];
    providers: DropdownItem[];
    products: DropdownItem[];
}

type SortKey = 'reference' | 'client_date' | 'provider_date' | 'provider_net_weight' | 'client_net_weight' | 'gap' | 'product' | 'base';

export default function TrackingsIndex({ trackings, filters, sort, transporters, trucks, drivers, providers, products }: Props) {
    const [deleteUrl, setDeleteUrl] = useState<string | null>(null);
    const [showFilters, setShowFilters] = useState(Object.keys(filters).length > 0);
    const [search, setSearch] = useState('');

    const hasActiveFilters = Object.keys(filters).length > 0;

    // Build query params from current state
    const buildParams = (overrides: Record<string, string | null> = {}) => {
        const params: Record<string, string> = { ...filters };
        if (sort.by !== 'client_date' || sort.dir !== 'desc') {
            params.sort_by = sort.by;
            params.sort_dir = sort.dir;
        }
        Object.entries(overrides).forEach(([k, v]) => {
            if (v) params[k] = v;
            else delete params[k];
        });
        // Clean empty values
        Object.keys(params).forEach((k) => { if (!params[k]) delete params[k]; });
        return params;
    };

    const applyFilter = (key: string, value: string | number | null) => {
        router.get('/transport_tracking', buildParams({ [key]: value ? String(value) : null }), { preserveState: true, preserveScroll: true });
    };

    const clearFilters = () => {
        router.get('/transport_tracking', {}, { preserveState: true, preserveScroll: true });
    };

    // Server-side sort — sends new request
    const handleSort = (key: SortKey) => {
        let newDir: string;
        if (sort.by === key) {
            newDir = sort.dir === 'asc' ? 'desc' : 'asc';
        } else {
            newDir = 'asc';
        }
        const params = buildParams({ sort_by: key, sort_dir: newDir });
        router.get('/transport_tracking', params, { preserveState: true, preserveScroll: true });
    };

    const SortIcon = ({ col }: { col: SortKey }) => {
        if (sort.by !== col) return <ChevronsUpDown size={14} className="opacity-30" />;
        if (sort.dir === 'asc') return <ChevronUp size={14} />;
        return <ChevronDown size={14} />;
    };

    const toOptions = (items: DropdownItem[], key: 'name' | 'matricule' = 'name') =>
        items.map((i) => ({ value: i.id, label: (i as any)[key] ?? String(i.id) }));

    const isPdf = (doc: Document) => doc.mime_type === 'application/pdf';

    // Client-side search within current page (quick filter for visible rows)
    const displayData = search
        ? trackings.data.filter((row) => {
            const text = [
                row.reference, row.truck?.matricule, row.driver?.name,
                row.provider?.name, row.product, row.client_date, row.provider_date,
            ].filter(Boolean).join(' ').toLowerCase();
            return text.includes(search.toLowerCase());
        })
        : trackings.data;

    const columns: { key: SortKey | 'truck' | 'driver' | 'provider' | 'files' | 'actions'; label: string; sortable: boolean; hideOnMobile?: boolean; render: (r: Tracking) => React.ReactNode }[] = [
        { key: 'reference', label: 'Rf.', sortable: true, render: (r) => r.reference },
        { key: 'truck', label: 'Camion', sortable: false, hideOnMobile: true, render: (r) => r.truck?.matricule ?? '-' },
        { key: 'driver', label: 'Conducteur', sortable: false, hideOnMobile: true, render: (r) => r.driver?.name ?? '-' },
        { key: 'provider', label: 'Fournisseur', sortable: false, hideOnMobile: true, render: (r) => r.provider?.name ?? '-' },
        { key: 'provider_net_weight', label: 'Poids Fourn.', sortable: true, render: (r) => r.provider_net_weight?.toLocaleString('fr-FR') ?? '-' },
        { key: 'client_net_weight', label: 'Poids Client', sortable: true, render: (r) => r.client_net_weight?.toLocaleString('fr-FR') ?? '-' },
        { key: 'gap', label: 'Perte / Exc.', sortable: true, render: (r) => {
            const g = r.gap ?? 0;
            if (g < 0) return <Badge variant="danger">{g.toLocaleString('fr-FR')}</Badge>;
            if (g > 0) return <Badge variant="info">+{g.toLocaleString('fr-FR')}</Badge>;
            return <Badge variant="success">0</Badge>;
        }},
        { key: 'client_date', label: 'Date', sortable: true, hideOnMobile: true, render: (r) => r.client_date ?? r.provider_date ?? '-' },
        { key: 'files', label: 'Fichiers', sortable: false, hideOnMobile: true, render: (r) => (
            r.documents && r.documents.length > 0 ? (
                <div className="flex items-center gap-1">
                    {r.documents.map((doc) => (
                        <a key={doc.id} href={doc.file_url} target="_blank" rel="noreferrer"
                           className="p-1 rounded hover:bg-[var(--color-surface-hover)] transition-colors" title={doc.original_name}>
                            {isPdf(doc) ? <FileText size={16} className="text-red-500" /> : <Image size={16} className="text-blue-500" />}
                        </a>
                    ))}
                </div>
            ) : <span className="text-[var(--color-text-muted)]">-</span>
        )},
        { key: 'actions', label: 'Actions', sortable: false, render: (r) => (
            <ActionButtons
                viewHref={`/transport_tracking/${r.id}/show-page`}
                editHref={`/transport_tracking/${r.id}/edit-page`}
                onDelete={() => setDeleteUrl(`/transport_tracking/${r.id}/destroy`)}
            />
        )},
    ];

    return (
        <AuthenticatedLayout title="Suivi Transport">
            <Head title="Suivi Transport" />

            <div className="flex flex-wrap items-center justify-between gap-3 mb-4">
                <Button variant="secondary" icon={<Filter size={16} />} onClick={() => setShowFilters(!showFilters)}>
                    Filtres
                    {hasActiveFilters && <Badge variant="primary" className="ml-1">{Object.keys(filters).length}</Badge>}
                </Button>
                <Button icon={<Plus size={16} />} onClick={() => router.visit('/transport_tracking/create-page')}>
                    Ajouter
                </Button>
            </div>

            {showFilters && (
                <Card className="mb-4">
                    <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <FormSelect label="Camion" placeholder="Tous" options={toOptions(trucks, 'matricule')} value={filters.truck_id ?? null} onChange={(v) => applyFilter('truck_id', v)} wrapperClass="mb-0" />
                        <FormSelect label="Conducteur" placeholder="Tous" options={toOptions(drivers)} value={filters.driver_id ?? null} onChange={(v) => applyFilter('driver_id', v)} wrapperClass="mb-0" />
                        <FormSelect label="Fournisseur" placeholder="Tous" options={toOptions(providers)} value={filters.provider_id ?? null} onChange={(v) => applyFilter('provider_id', v)} wrapperClass="mb-0" />
                        <FormSelect label="Produit" placeholder="Tous" options={toOptions(products)} value={filters.product ?? null} onChange={(v) => applyFilter('product', v)} wrapperClass="mb-0" />
                    </div>
                    <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
                        <FormSelect label="Transporteur" placeholder="Tous" options={toOptions(transporters)} value={filters.transporter_id ?? null} onChange={(v) => applyFilter('transporter_id', v)} wrapperClass="mb-0" />
                        <div>
                            <label className="block text-sm font-medium text-[var(--color-text)] mb-1.5">Date du</label>
                            <input
                                type="date"
                                value={filters.start_date ?? ''}
                                onChange={(e) => applyFilter('start_date', e.target.value || null)}
                                className="w-full px-3 py-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-[var(--color-text)] mb-1.5">Date au</label>
                            <input
                                type="date"
                                value={filters.end_date ?? ''}
                                onChange={(e) => applyFilter('end_date', e.target.value || null)}
                                className="w-full px-3 py-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm"
                            />
                        </div>
                        <div className="flex items-end">
                            {hasActiveFilters && (
                                <button onClick={clearFilters} className="text-xs text-[var(--color-danger)] hover:underline flex items-center gap-1 pb-2">
                                    <X size={12} /> Rinitialiser
                                </button>
                            )}
                        </div>
                    </div>
                </Card>
            )}

            <Card padding={false}>
                <div className="p-5">
                    {/* Search + Export + Total */}
                    <div className="mb-4 flex flex-wrap items-center gap-2">
                        <div className="relative flex-1 min-w-[200px]">
                            <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-[var(--color-text-muted)]" />
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Rechercher dans la page..."
                                className="w-full sm:w-80 pl-9 pr-4 py-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm text-[var(--color-text)] placeholder:text-[var(--color-text-muted)] focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]/20 focus:border-[var(--color-primary)] transition"
                            />
                        </div>
                        <span className="text-xs text-[var(--color-text-muted)]">{trackings.total} rotation(s)</span>
                        {displayData.length > 0 && (
                            <button
                                onClick={() => exportToCsv(displayData, [
                                    { key: 'reference', label: 'Rfrence' },
                                    { key: 'truck', label: 'Camion' },
                                    { key: 'driver', label: 'Conducteur' },
                                    { key: 'provider', label: 'Fournisseur' },
                                    { key: 'provider_net_weight', label: 'Poids Fournisseur' },
                                    { key: 'client_net_weight', label: 'Poids Client' },
                                    { key: 'gap', label: 'Perte' },
                                    { key: 'client_date', label: 'Date Client' },
                                    { key: 'product', label: 'Produit' },
                                ], `suivi-transport-${new Date().toISOString().slice(0, 10)}.csv`)}
                                className="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium transition"
                                title="Exporter en Excel"
                            >
                                <Download size={14} /> Excel
                            </button>
                        )}
                    </div>

                    {/* Desktop table */}
                    <div className="hidden md:block overflow-x-auto rounded-lg border border-[var(--color-border)]">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="bg-[var(--color-surface-hover)]">
                                    {columns.map((col) => (
                                        <th
                                            key={col.key}
                                            className={clsx(
                                                'px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-[var(--color-text-secondary)]',
                                                col.sortable && 'cursor-pointer select-none hover:text-[var(--color-text)]',
                                            )}
                                            onClick={() => col.sortable && handleSort(col.key as SortKey)}
                                        >
                                            <span className="inline-flex items-center gap-1">
                                                {col.label}
                                                {col.sortable && <SortIcon col={col.key as SortKey} />}
                                            </span>
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[var(--color-border)]">
                                {displayData.length === 0 ? (
                                    <tr>
                                        <td colSpan={columns.length} className="px-4 py-8 text-center text-[var(--color-text-muted)]">
                                            Aucune donne
                                        </td>
                                    </tr>
                                ) : displayData.map((row) => (
                                    <tr key={row.id} className="hover:bg-[var(--color-surface-hover)] transition-colors">
                                        {columns.map((col) => (
                                            <td key={col.key} className="px-4 py-3 text-[var(--color-text)]">
                                                {col.render(row)}
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {/* Mobile cards */}
                    <div className="md:hidden space-y-3">
                        {displayData.length === 0 ? (
                            <p className="text-center py-8 text-[var(--color-text-muted)]">Aucune donne</p>
                        ) : displayData.map((row) => (
                            <div key={row.id} className="bg-[var(--color-surface)] rounded-xl border border-[var(--color-border)] p-4 space-y-2">
                                {columns.filter((c) => !c.hideOnMobile).map((col) => (
                                    <div key={col.key} className="flex justify-between items-start gap-2">
                                        <span className="text-xs font-medium text-[var(--color-text-muted)] uppercase">{col.label}</span>
                                        <span className="text-sm text-[var(--color-text)] text-right">{col.render(row)}</span>
                                    </div>
                                ))}
                            </div>
                        ))}
                    </div>
                </div>
                <div className="px-5 pb-5">
                    <Pagination meta={trackings} />
                </div>
            </Card>

            <ConfirmDialog open={!!deleteUrl} onClose={() => setDeleteUrl(null)} deleteUrl={deleteUrl ?? undefined} />
        </AuthenticatedLayout>
    );
}
