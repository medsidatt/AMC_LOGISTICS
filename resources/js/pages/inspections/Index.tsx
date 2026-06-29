import { Head, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import PageHeader from '@/components/ui/PageHeader';
import Button from '@/components/ui/Button';
import DataTable from '@/components/ui/DataTable';
import InspectionFormDrawer from './components/InspectionFormDrawer';
import InspectionDetailsDrawer from './components/InspectionDetailsDrawer';
import { ShieldCheck, Plus, Eye, CheckCircle2, Clock, AlertTriangle } from 'lucide-react';
import { usePermission } from '@/hooks/usePermission';
import type { InspectionRow, InspectionDetail } from './types';

interface Props {
    inspections: InspectionRow[];
    cutoff: string;
    options: { categories: Record<string, string> };
}

type FormState = { mode: 'create' } | { mode: 'edit'; id: number } | null;

export default function InspectionsWorkspace({ inspections, cutoff, options }: Props) {
    const { can } = usePermission();
    const canCreate = can('inspection-create');
    const canEdit = can('inspection-edit');

    const [detailsId, setDetailsId] = useState<number | null>(null);
    const [formState, setFormState] = useState<FormState>(null);

    const page = usePage();
    useEffect(() => {
        const params = new URLSearchParams(window.location.search);
        const view = params.get('view');
        if (view) {
            setDetailsId(Number(view));
            params.delete('view');
            const qs = params.toString();
            window.history.replaceState({}, '', window.location.pathname + (qs ? `?${qs}` : ''));
        }
    }, [page.url]);

    const rows = inspections.map((i) => ({
        ...i,
        truck_matricule: i.truck?.matricule ?? '—',
        category_label: options.categories[i.category] ?? i.category,
        status_label: i.status === 'validated' ? 'Signée' : 'En attente',
    }));
    type Row = (typeof rows)[number];

    const statusPill = (r: Row) =>
        r.status === 'validated'
            ? <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold ring-1 bg-emerald-100 text-emerald-800 ring-emerald-200"><CheckCircle2 size={11} /> Signée</span>
            : <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold ring-1 bg-amber-100 text-amber-800 ring-amber-200"><Clock size={11} /> En attente</span>;

    const columns = [
        {
            key: 'vehicle_photo_url', label: 'Photo', render: (r: Row) => r.vehicle_photo_url
                ? <img src={r.vehicle_photo_url} alt="" className="h-9 w-12 object-cover rounded" />
                : <div className="h-9 w-12 rounded bg-[var(--color-surface-hover)] flex items-center justify-center text-[var(--color-text-muted)]"><ShieldCheck size={14} /></div>,
        },
        { key: 'inspection_date', label: 'Date', sortable: true },
        { key: 'truck_matricule', label: 'Camion', sortable: true },
        { key: 'inspector', label: 'Inspecteur', sortable: true, hideOnMobile: true },
        { key: 'category_label', label: 'Type', sortable: true, hideOnMobile: true },
        {
            key: 'issues_count', label: 'Anomalies', sortable: true, render: (r: Row) => r.issues_count > 0
                ? <span className="inline-flex items-center gap-1 text-amber-700"><AlertTriangle size={13} /> {r.issues_count}</span>
                : <span className="text-[var(--color-text-muted)]">—</span>,
        },
        { key: 'status_label', label: 'Statut', sortable: true, render: (r: Row) => statusPill(r) },
        {
            key: 'actions', label: '', render: (r: Row) => (
                <Button variant="ghost" icon={<Eye size={15} />} onClick={() => setDetailsId(r.id)}>Voir</Button>
            ),
        },
    ];

    const mobileCard = (r: Row) => (
        <button type="button" onClick={() => setDetailsId(r.id)} className="w-full text-left flex items-center gap-3 p-3">
            {r.vehicle_photo_url
                ? <img src={r.vehicle_photo_url} alt="" className="h-12 w-16 object-cover rounded" />
                : <div className="h-12 w-16 rounded bg-[var(--color-surface-hover)] flex items-center justify-center text-[var(--color-text-muted)]"><ShieldCheck size={16} /></div>}
            <div className="flex-1 min-w-0">
                <div className="font-semibold text-[var(--color-text)]">{r.truck_matricule}</div>
                <div className="text-xs text-[var(--color-text-muted)]">{r.inspection_date} · {r.inspector ?? '—'}</div>
            </div>
            {statusPill(r)}
        </button>
    );

    return (
        <AuthenticatedLayout title="Inspections">
            <Head title="Inspections" />

            <PageHeader
                icon={<ShieldCheck size={22} className="text-[var(--color-primary)]" />}
                title="Inspections"
                subtitle={`Depuis le ${cutoff}`}
                actions={canCreate ? <Button icon={<Plus size={16} />} onClick={() => setFormState({ mode: 'create' })}>Nouvelle inspection</Button> : undefined}
            />

            <DataTable
                data={rows}
                columns={columns}
                searchKeys={['truck_matricule', 'inspector', 'inspection_date', 'category_label', 'status_label']}
                emptyMessage="Aucune inspection"
                mobileCard={mobileCard}
                exportable
                exportFilename="inspections"
            />

            {detailsId !== null && (
                <InspectionDetailsDrawer
                    inspectionId={detailsId}
                    canEdit={canEdit}
                    onEdit={(inspection: InspectionDetail) => { setDetailsId(null); setFormState({ mode: 'edit', id: inspection.id }); }}
                    onClose={() => setDetailsId(null)}
                />
            )}

            {formState !== null && (
                <InspectionFormDrawer
                    mode={formState.mode}
                    inspectionId={formState.mode === 'edit' ? formState.id : undefined}
                    onClose={() => setFormState(null)}
                />
            )}
        </AuthenticatedLayout>
    );
}
