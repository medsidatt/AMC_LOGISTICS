import { Head, router } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import FormSelect from '@/components/ui/FormSelect';
import ActionButtons from '@/components/ui/ActionButtons';
import ConfirmDialog from '@/components/ui/ConfirmDialog';
import Pagination from '@/components/ui/Pagination';
import { Plus, FileText, Image, Filter, ChevronUp, ChevronDown, ChevronsUpDown, ChevronLeft, ChevronRight, Search, X } from 'lucide-react';
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
    transporters: DropdownItem[];
    trucks: DropdownItem[];
    drivers: DropdownItem[];
    providers: DropdownItem[];
    products: DropdownItem[];
}

type SortKey = 'reference' | 'truck' | 'driver' | 'provider' | 'provider_net_weight' | 'client_net_weight' | 'gap' | 'client_date';
type SortDir = 'asc' | 'desc' | null;

export default function TrackingsIndex({ trackings, filters, transporters, trucks, drivers, providers, products }: Props) {
    const [deleteUrl, setDeleteUrl] = useState<string | null>(null);
    const [showFilters, setShowFilters] = useState(Object.keys(filters).length > 0);
    const [search, setSearch] = useState('');
    const [sortKey, setSortKey] = useState<SortKey | null>(null);
    const [sortDir, setSortDir] = useState<SortDir>(null);

    const hasActiveFilters = Object.keys(filters).length > 0;

    const applyFilter = (key: string, value: string | number | null) => {
        const newFilters = { ...filters, [key]: value ? String(value) : '' };
        Object.keys(newFilters).forEach((k) => { if (!newFilters[k]) delete newFilters[k]; });
        router.get('/transport_tracking', newFilters, { preserveState: true, preserveScroll: true });
    };

    const clearFilters = () => {
        router.get('/transport_tracking', {}, { preserveState: true, preserveScroll: true });
    };

    const toOptions = (items: DropdownItem[], key: 'name' | 'matricule' = 'name') =>
        items.map((i) => ({ value: i.id, label: (i as any)[key] ?? String(i.id) }));

    const isPdf = (doc: Document) => doc.mime_type === 'application/pdf';

    // Get sortable value for a row
    const getSortValue = (row: Tracking, key: SortKey): string | number => {
        switch (key) {
            case 'truck': return row.truck?.matricule ?? '';
            case 'driver': return row.driver?.name ?? '';
            case 'provider': return row.provider?.name ?? '';
            case 'provider_net_weight': return row.provider_net_weight ?? 0;
            case 'client_net_weight': return row.client_net_weight ?? 0;
            case 'gap': return row.gap ?? 0;
            case 'client_date': return row.client_date ?? row.provider_date ?? '';
            default: return row[key] ?? '';
        }
    };

    // Get searchable text for a row
    const getSearchText = (row: Tracking): string => {
        return [
            row.reference,
            row.truck?.matricule,
            row.driver?.name,
            row.provider?.name,
            row.product,
            row.base,
            row.provider_net_weight?.toString(),
            row.client_net_weight?.toString(),
            row.gap?.toString(),
            row.client_date,
            row.provider_date,
        ].filter(Boolean).join(' ').toLowerCase();
    };

    // Filter and sort data
    const processedData = useMemo(() => {
        let data = [...trackings.data];

        // Search
        if (search) {
            const q = search.toLowerCase();
            data = data.filter((row) => getSearchText(row).includes(q));
        }

        // Sort
        if (sortKey && sortDir) {
            data.sort((a, b) => {
                const av = getSortValue(a, sortKey);
                const bv = getSortValue(b, sortKey);
                const cmp = typeof av === 'number' && typeof bv === 'number'
                    ? av - bv
                    : String(av).localeCompare(String(bv));
                return sortDir === 'desc' ? -cmp : cmp;
            });
        }

        return data;
    }, [trackings.data, search, sortKey, sortDir]);

    const handleSort = (key: SortKey) => {
        if (sortKey === key) {
            setSortDir(sortDir === 'asc' ? 'desc' : sortDir === 'desc' ? null : 'asc');
            if (sortDir === 'desc') setSortKey(null);
        } else {
            setSortKey(key);
            setSortDir('asc');
        }
    };

    const SortIcon = ({ col }: { col: SortKey }) => {
        if (sortKey !== col) return <ChevronsUpDown size={14} className="opacity-30" />;
        if (sortDir === 'asc') return <ChevronUp size={14} />;
        return <ChevronDown size={14} />;
    };

    const columns: { key: SortKey | 'files' | 'actions'; label: string; sortable?: boolean; hideOnMobile?: boolean; render: (r: Tracking) => React.ReactNode }[] = [
        { key: 'reference', label: 'Réf.', render: (r) => r.reference },
        { key: 'truck', label: 'Camion', hideOnMobile: true, render: (r) => r.truck?.matricule ?? '-' },
        { key: 'driver', label: 'Conducteur', hideOnMobile: true, render: (r) => r.driver?.name ?? '-' },
        { key: 'provider', label: 'Fournisseur', hideOnMobile: true, render: (r) => r.provider?.name ?? '-' },
        { key: 'provider_net_weight', label: 'Poids Fourn.', render: (r) => r.provider_net_weight?.toLocaleString('fr-FR') ?? '-' },
        { key: 'client_net_weight', label: 'Poids Client', render: (r) => r.client_net_weight?.toLocaleString('fr-FR') ?? '-' },
        { key: 'gap', label: 'Écart', render: (r) => (
            <Badge variant={r.gap < 0 ? 'danger' : r.gap > 0 ? 'warning' : 'success'}>
                {r.gap?.toLocaleString('fr-FR')}
            </Badge>
        )},
        { key: 'client_date', label: 'Date', hideOnMobile: true, render: (r) => r.client_date ?? r.provider_date ?? '-' },
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
                    {hasActiveFilters && <Badge variant="primary">{Object.keys(filters).length}</Badge>}
                </Button>
                <Button icon={<Plus size={16} />} onClick={() => router.visit('/transport_tracking/create-page')}>
                    Ajouter
                </Button>
            </div>

            {showFilters && (
                <Card className="mb-4">
                    <div className="grid sm:grid-cols-2 lg:grid-cols-5 gap-4">
                        <FormSelect label="Camion" placeholder="Tous" options={toOptions(trucks, 'matricule')} value={filters.truck_id ?? null} onChange={(v) => applyFilter('truck_id', v)} wrapperClass="mb-0" />
                        <FormSelect label="Conducteur" placeholder="Tous" options={toOptions(drivers)} value={filters.driver_id ?? null} onChange={(v) => applyFilter('driver_id', v)} wrapperClass="mb-0" />
                        <FormSelect label="Fournisseur" placeholder="Tous" options={toOptions(providers)} value={filters.provider_id ?? null} onChange={(v) => applyFilter('provider_id', v)} wrapperClass="mb-0" />
                        <FormSelect label="Produit" placeholder="Tous" options={toOptions(products)} value={filters.product ?? null} onChange={(v) => applyFilter('product', v)} wrapperClass="mb-0" />
                        <FormSelect label="Transporteur" placeholder="Tous" options={toOptions(transporters)} value={filters.transporter_id ?? null} onChange={(v) => applyFilter('transporter_id', v)} wrapperClass="mb-0" />
                    </div>
                    {hasActiveFilters && (
                        <div className="mt-3 flex justify-end">
                            <button onClick={clearFilters} className="text-xs text-[var(--color-danger)] hover:underline flex items-center gap-1">
                                <X size={12} /> Réinitialiser les filtres
                            </button>
                        </div>
                    )}
                </Card>
            )}

            <Card padding={false}>
                <div className="p-5">
                    {/* Search */}
                    <div className="mb-4 relative">
                        <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-[var(--color-text-muted)]" />
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Rechercher dans toutes les colonnes..."
                            className="w-full sm:w-80 pl-9 pr-4 py-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm text-[var(--color-text)] placeholder:text-[var(--color-text-muted)] focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]/20 focus:border-[var(--color-primary)] transition"
                        />
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
                                                col.sortable !== false && 'cursor-pointer select-none hover:text-[var(--color-text)]',
                                            )}
                                            onClick={() => col.sortable !== false && col.key !== 'files' && col.key !== 'actions' && handleSort(col.key as SortKey)}
                                        >
                                            <span className="inline-flex items-center gap-1">
                                                {col.label}
                                                {col.sortable !== false && col.key !== 'files' && col.key !== 'actions' && <SortIcon col={col.key as SortKey} />}
                                            </span>
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[var(--color-border)]">
                                {processedData.length === 0 ? (
                                    <tr>
                                        <td colSpan={columns.length} className="px-4 py-8 text-center text-[var(--color-text-muted)]">
                                            Aucune donnée
                                        </td>
                                    </tr>
                                ) : processedData.map((row) => (
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
                        {processedData.length === 0 ? (
                            <p className="text-center py-8 text-[var(--color-text-muted)]">Aucune donnée</p>
                        ) : processedData.map((row) => (
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
