import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import PageHeader from '@/components/ui/PageHeader';
import ActionButtons from '@/components/ui/ActionButtons';
import ConfirmDialog from '@/components/ui/ConfirmDialog';
import Pagination from '@/components/ui/Pagination';
import TransportFilters from './components/TransportFilters';
import TransportDetailsDrawer from './components/TransportDetailsDrawer';
import TransportFormDrawer, { type TransportFormRefs, type TransportEditRecord, type TransportPrefill } from './components/TransportFormDrawer';
import { apiFetch } from '@/utils/csrf';
import { Plus, FileText, Image as ImageIcon, ChevronUp, ChevronDown, ChevronsUpDown, Search, Download, Package, Loader2 } from 'lucide-react';
import { clsx } from 'clsx';
import { usePermission } from '@/hooks/usePermission';

interface TrackingDocument { id: number; original_name: string; mime_type: string; type: string; file_url: string }

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
    documents: TrackingDocument[];
    truck: { id: number; matricule: string } | null;
    driver: { id: number; name: string } | null;
    provider: { id: number; name: string } | null;
}

interface DropdownItem { id: number | string; name?: string; matricule?: string }

interface Props {
    trackings: { data: Tracking[]; current_page: number; last_page: number; per_page: number; total: number; from: number | null; to: number | null };
    filters: Record<string, string>;
    sort: { by: string; dir: string };
    transporters: DropdownItem[];
    trucks: DropdownItem[];
    drivers: DropdownItem[];
    providers: DropdownItem[];
    products: DropdownItem[];
    formRefs: TransportFormRefs;
}

type SortKey = 'reference' | 'client_date' | 'provider_date' | 'provider_net_weight' | 'client_net_weight' | 'gap' | 'product' | 'base';
type FormState = { mode: 'create'; prefill?: TransportPrefill | null } | { mode: 'edit'; record: TransportEditRecord };

export default function TrackingsIndex({ trackings, filters, sort, transporters, trucks, drivers, providers, products, formRefs }: Props) {
    const [deleteUrl, setDeleteUrl] = useState<string | null>(null);
    const [search, setSearch] = useState('');
    const [detailsId, setDetailsId] = useState<number | null>(null);
    const [formState, setFormState] = useState<FormState | null>(null);
    const [editLoadingId, setEditLoadingId] = useState<number | null>(null);
    const { can } = usePermission();
    const canCreate = can('transport-tracking-create');
    const canEdit = can('transport-tracking-edit');
    const canDelete = can('transport-tracking-delete');

    const openEdit = async (id: number) => {
        setEditLoadingId(id);
        try {
            const res = await apiFetch(`/transport_tracking/${id}/edit-data`);
            if (res.ok) {
                const j = await res.json();
                setDetailsId(null);
                setFormState({ mode: 'edit', record: j.transportTracking });
            }
        } finally {
            setEditLoadingId(null);
        }
    };

    // Deep-links from external surfaces (Réconciliation, Reports): ?view=ID / ?edit=ID.
    useEffect(() => {
        const params = new URLSearchParams(window.location.search);
        const view = params.get('view');
        const edit = params.get('edit');
        const create = params.get('create');
        if (view) setDetailsId(Number(view));
        else if (edit) openEdit(Number(edit));
        else if (create) {
            setFormState({
                mode: 'create',
                prefill: {
                    truck_id: params.get('truck_id') ?? undefined,
                    provider_id: params.get('provider_id') ?? undefined,
                    provider_date: params.get('provider_date') ?? undefined,
                },
            });
        }
        if (view || edit || create) {
            ['view', 'edit', 'create', 'truck_id', 'provider_id', 'provider_date'].forEach((k) => params.delete(k));
            const qs = params.toString();
            window.history.replaceState({}, '', '/transport_tracking' + (qs ? `?${qs}` : ''));
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const buildParams = (overrides: Record<string, string | null> = {}) => {
        const params: Record<string, string> = { ...filters };
        if (sort.by !== 'client_date' || sort.dir !== 'desc') { params.sort_by = sort.by; params.sort_dir = sort.dir; }
        Object.entries(overrides).forEach(([k, v]) => { if (v) params[k] = v; else delete params[k]; });
        Object.keys(params).forEach((k) => { if (!params[k]) delete params[k]; });
        return params;
    };

    const handleSort = (key: SortKey) => {
        const newDir = sort.by === key ? (sort.dir === 'asc' ? 'desc' : 'asc') : 'asc';
        router.get('/transport_tracking', buildParams({ sort_by: key, sort_dir: newDir }), { preserveState: true, preserveScroll: true });
    };

    const SortIcon = ({ col }: { col: SortKey }) => {
        if (sort.by !== col) return <ChevronsUpDown size={14} className="opacity-30" />;
        return sort.dir === 'asc' ? <ChevronUp size={14} /> : <ChevronDown size={14} />;
    };

    const isPdf = (doc: TrackingDocument) => doc.mime_type === 'application/pdf';

    const displayData = search
        ? trackings.data.filter((row) => [row.reference, row.truck?.matricule, row.driver?.name, row.provider?.name, row.product, row.client_date, row.provider_date]
            .filter(Boolean).join(' ').toLowerCase().includes(search.toLowerCase()))
        : trackings.data;

    const exportUrl = (() => {
        const params = new URLSearchParams();
        Object.entries(filters).forEach(([k, v]) => { if (v) params.set(k, String(v)); });
        const qs = params.toString();
        return '/transport_tracking/export' + (qs ? `?${qs}` : '');
    })();

    const columns: { key: SortKey | 'truck' | 'driver' | 'provider' | 'files' | 'actions'; label: string; sortable: boolean; hideOnMobile?: boolean; render: (r: Tracking) => React.ReactNode }[] = [
        { key: 'reference', label: 'Réf.', sortable: true, render: (r) => r.reference },
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
        } },
        { key: 'client_date', label: 'Date', sortable: true, hideOnMobile: true, render: (r) => r.client_date ?? r.provider_date ?? '-' },
        { key: 'files', label: 'Fichiers', sortable: false, hideOnMobile: true, render: (r) => (
            r.documents && r.documents.length > 0 ? (
                <div className="flex items-center gap-1">
                    {r.documents.map((doc) => (
                        <a key={doc.id} href={doc.file_url} target="_blank" rel="noreferrer" className="p-1 rounded hover:bg-[var(--color-surface-hover)]" title={doc.original_name}>
                            {isPdf(doc) ? <FileText size={16} className="text-red-500" /> : <ImageIcon size={16} className="text-blue-500" />}
                        </a>
                    ))}
                </div>
            ) : <span className="text-[var(--color-text-muted)]">-</span>
        ) },
        { key: 'actions', label: 'Actions', sortable: false, render: (r) => (
            <div className="flex items-center gap-1">
                {editLoadingId === r.id && <Loader2 size={14} className="animate-spin text-[var(--color-text-muted)]" />}
                <ActionButtons
                    onView={() => setDetailsId(r.id)}
                    onEdit={canEdit ? () => openEdit(r.id) : undefined}
                    onDelete={canDelete ? () => setDeleteUrl(`/transport_tracking/${r.id}/destroy`) : undefined}
                />
            </div>
        ) },
    ];

    return (
        <AuthenticatedLayout title="Suivi Transport">
            <Head title="Suivi Transport" />

            <PageHeader
                icon={<Package size={22} className="text-[var(--color-primary)]" />}
                title="Suivi Transport"
                actions={canCreate ? (
                    <Button icon={<Plus size={16} />} onClick={() => setFormState({ mode: 'create' })}>Ajouter</Button>
                ) : undefined}
            />

            <TransportFilters filters={filters} sort={sort} trucks={trucks} drivers={drivers} providers={providers} transporters={transporters} products={products} />

            <Card padding={false}>
                <div className="p-5">
                    <div className="mb-4 flex flex-wrap items-center gap-2">
                        <div className="relative flex-1 min-w-[200px]">
                            <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-[var(--color-text-muted)]" />
                            <input
                                type="text" value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Rechercher dans la page..."
                                className="w-full sm:w-80 pl-9 pr-4 py-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm text-[var(--color-text)] placeholder:text-[var(--color-text-muted)] focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]/20 focus:border-[var(--color-primary)] transition"
                            />
                        </div>
                        <span className="text-xs text-[var(--color-text-muted)]">{trackings.total} rotation(s)</span>
                        {trackings.total > 0 && (
                            <a href={exportUrl} className="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium transition" title="Exporter toutes les rotations filtrées (Excel)">
                                <Download size={14} /> Excel ({trackings.total})
                            </a>
                        )}
                    </div>

                    <div className="hidden md:block overflow-x-auto rounded-lg border border-[var(--color-border)]">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="bg-[var(--color-surface-hover)]">
                                    {columns.map((col) => (
                                        <th key={col.key} onClick={() => col.sortable && handleSort(col.key as SortKey)}
                                            className={clsx('px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-[var(--color-text-secondary)]', col.sortable && 'cursor-pointer select-none hover:text-[var(--color-text)]')}>
                                            <span className="inline-flex items-center gap-1">{col.label}{col.sortable && <SortIcon col={col.key as SortKey} />}</span>
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[var(--color-border)]">
                                {displayData.length === 0 ? (
                                    <tr><td colSpan={columns.length} className="px-4 py-8 text-center text-[var(--color-text-muted)]">Aucune donnée</td></tr>
                                ) : displayData.map((row) => (
                                    <tr key={row.id} className="hover:bg-[var(--color-surface-hover)] transition-colors">
                                        {columns.map((col) => (
                                            <td key={col.key} className="px-4 py-3 text-[var(--color-text)]">{col.render(row)}</td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <div className="md:hidden space-y-3">
                        {displayData.length === 0 ? (
                            <p className="text-center py-8 text-[var(--color-text-muted)]">Aucune donnée</p>
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

            {detailsId !== null && (
                <TransportDetailsDrawer
                    id={detailsId}
                    canEdit={canEdit}
                    onEdit={() => openEdit(detailsId)}
                    onClose={() => setDetailsId(null)}
                />
            )}

            {formState && (
                <TransportFormDrawer
                    key={formState.mode === 'edit' ? `edit-${formState.record.id}` : 'create'}
                    mode={formState.mode}
                    refs={formRefs}
                    record={formState.mode === 'edit' ? formState.record : null}
                    prefill={formState.mode === 'create' ? formState.prefill : null}
                    onClose={() => setFormState(null)}
                />
            )}

            <ConfirmDialog open={!!deleteUrl} onClose={() => setDeleteUrl(null)} deleteUrl={deleteUrl ?? undefined} />
        </AuthenticatedLayout>
    );
}
