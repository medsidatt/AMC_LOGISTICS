import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import DataTable from '@/components/ui/DataTable';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import PageHeader from '@/components/ui/PageHeader';
import ActionButtons from '@/components/ui/ActionButtons';
import ConfirmDialog from '@/components/ui/ConfirmDialog';
import Pagination from '@/components/ui/Pagination';
import DriverDetailsDrawer, { type DriverRow } from './components/DriverDetailsDrawer';
import DriverFormDrawer, { type DriverEditData } from './components/DriverFormDrawer';
import { apiFetch } from '@/utils/csrf';
import { Plus, Power, PowerOff, Users, Loader2 } from 'lucide-react';
import { usePermission } from '@/hooks/usePermission';

interface Props {
    drivers: { data: DriverRow[]; current_page: number; last_page: number; per_page: number; total: number; from: number | null; to: number | null };
    totals: { active: number; total: number };
}

type FormState = { mode: 'create' } | { mode: 'edit'; driver: DriverEditData };

const toEdit = (d: DriverRow): DriverEditData => ({
    id: d.id, name: d.name, email: d.email, phone: d.phone, address: d.address, is_active: d.is_active,
});

export default function DriversIndex({ drivers, totals }: Props) {
    const [details, setDetails] = useState<DriverRow | null>(null);
    const [formState, setFormState] = useState<FormState | null>(null);
    const [deleteUrl, setDeleteUrl] = useState<string | null>(null);
    const [editLoadingId, setEditLoadingId] = useState<number | null>(null);
    const { can } = usePermission();
    const canCreate = can('driver-create');
    const canEdit = can('driver-edit');
    const canDelete = can('driver-delete');

    const openEdit = (d: DriverRow) => { setDetails(null); setFormState({ mode: 'edit', driver: toEdit(d) }); };

    // Edit by id — uses the in-page row when present, else fetches edit-data (the
    // driver may be on another paginated page, e.g. from a Dispatch deep-link).
    const openEditById = async (id: number) => {
        const row = drivers.data.find((d) => d.id === id);
        if (row) { openEdit(row); return; }
        setEditLoadingId(id);
        try {
            const res = await apiFetch(`/drivers/${id}/edit-data`);
            if (res.ok) { const j = await res.json(); setFormState({ mode: 'edit', driver: j.driver }); }
        } finally {
            setEditLoadingId(null);
        }
    };

    // Deep-links: /drivers?edit=ID (Dispatch board), /drivers?view=ID.
    useEffect(() => {
        const params = new URLSearchParams(window.location.search);
        const view = params.get('view');
        const edit = params.get('edit');
        if (view) { const row = drivers.data.find((d) => String(d.id) === view); if (row) setDetails(row); }
        else if (edit) openEditById(Number(edit));
        if (view || edit) {
            params.delete('view'); params.delete('edit');
            const qs = params.toString();
            window.history.replaceState({}, '', '/drivers' + (qs ? `?${qs}` : ''));
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const toggleActive = (d: DriverRow) => router.post(`/drivers/${d.id}/toggle-active`, {}, { preserveScroll: true });

    return (
        <AuthenticatedLayout title="Conducteurs">
            <Head title="Conducteurs" />

            <PageHeader
                icon={<Users size={22} className="text-[var(--color-primary)]" />}
                title="Conducteurs"
                subtitle={<><span className="font-semibold text-[var(--color-success)]">{totals.active}</span> actifs sur <span className="font-semibold">{totals.total}</span> chauffeurs</>}
                actions={canCreate ? (
                    <Button icon={<Plus size={16} />} onClick={() => setFormState({ mode: 'create' })}>Ajouter</Button>
                ) : undefined}
            />

            <Card padding={false}>
                <div className="p-5">
                    <DataTable
                        data={drivers.data}
                        columns={[
                            { key: 'name', label: 'Nom' },
                            { key: 'is_active', label: 'Actif', render: (r) => <Badge variant={r.is_active ? 'success' : 'muted'}>{r.is_active ? 'Oui' : 'Non'}</Badge> },
                            { key: 'email', label: 'Email', hideOnMobile: true },
                            { key: 'phone', label: 'Téléphone', hideOnMobile: true },
                            {
                                key: 'actions', label: 'Actions', sortable: false,
                                render: (r) => (
                                    <div className="flex items-center gap-1">
                                        {editLoadingId === r.id && <Loader2 size={14} className="animate-spin text-[var(--color-text-muted)]" />}
                                        {canEdit && (
                                            <button
                                                type="button" onClick={() => toggleActive(r)}
                                                title={r.is_active ? 'Désactiver' : 'Activer'}
                                                className="p-1.5 rounded hover:bg-[var(--color-surface-hover)] text-[var(--color-text-muted)]"
                                            >
                                                {r.is_active ? <PowerOff size={14} /> : <Power size={14} className="text-[var(--color-success)]" />}
                                            </button>
                                        )}
                                        <ActionButtons
                                            onView={() => setDetails(r)}
                                            onEdit={canEdit ? () => openEdit(r) : undefined}
                                            onDelete={canDelete ? () => setDeleteUrl(`/drivers/${r.id}/destroy`) : undefined}
                                        />
                                    </div>
                                ),
                            },
                        ]}
                        perPage={drivers.per_page}
                        searchable
                        exportable
                    />
                </div>
                <div className="px-5 pb-5">
                    <Pagination meta={drivers} />
                </div>
            </Card>

            {details && (
                <DriverDetailsDrawer
                    driver={details}
                    canEdit={canEdit}
                    onEdit={() => openEdit(details)}
                    onClose={() => setDetails(null)}
                />
            )}

            {formState && (
                <DriverFormDrawer
                    key={formState.mode === 'edit' ? `edit-${formState.driver.id}` : 'create'}
                    mode={formState.mode}
                    driver={formState.mode === 'edit' ? formState.driver : null}
                    onClose={() => setFormState(null)}
                />
            )}

            <ConfirmDialog open={!!deleteUrl} onClose={() => setDeleteUrl(null)} deleteUrl={deleteUrl ?? undefined} />
        </AuthenticatedLayout>
    );
}
