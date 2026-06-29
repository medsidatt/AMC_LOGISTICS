import { Head } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import DataTable from '@/components/ui/DataTable';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import PageHeader from '@/components/ui/PageHeader';
import ActionButtons from '@/components/ui/ActionButtons';
import ConfirmDialog from '@/components/ui/ConfirmDialog';
import TruckDetailsDrawer, { type TruckRow } from './components/TruckDetailsDrawer';
import TruckFormDrawer, { type TruckEditData } from './components/TruckFormDrawer';
import { Plus, AlertTriangle, Truck as TruckIcon } from 'lucide-react';
import { usePermission } from '@/hooks/usePermission';

interface Props {
    trucks: TruckRow[];
    maintenanceDueCount: number;
    transporters: { value: number; label: string }[];
    defaultCapacityTonnage: number;
    defaultTargetRotationsPerWeek: number;
}

type FormState = { mode: 'create' } | { mode: 'edit'; truck: TruckEditData };

const toEditData = (r: TruckRow): TruckEditData => ({
    id: r.id,
    matricule: r.matricule,
    transporter_id: r.transporter_id,
    km_maintenance_interval: r.km_maintenance_interval,
    target_rotations_per_week: r.target_rotations_per_week,
    is_available: r.is_available,
});

export default function TrucksIndex({ trucks, maintenanceDueCount, transporters, defaultCapacityTonnage, defaultTargetRotationsPerWeek }: Props) {
    const [details, setDetails] = useState<TruckRow | null>(null);
    const [formState, setFormState] = useState<FormState | null>(null);
    const [deleteUrl, setDeleteUrl] = useState<string | null>(null);
    const { can } = usePermission();
    const canCreate = can('truck-create');
    const canEdit = can('truck-edit');
    const canDelete = can('truck-delete');

    const openEdit = (r: TruckRow) => { setDetails(null); setFormState({ mode: 'edit', truck: toEditData(r) }); };

    // Deep-link: /trucks?edit=ID (e.g. from the Truck Profile page) opens the edit drawer.
    useEffect(() => {
        const editId = new URLSearchParams(window.location.search).get('edit');
        if (!editId) return;
        const row = trucks.find((t) => String(t.id) === editId);
        if (row) setFormState({ mode: 'edit', truck: toEditData(row) });
        window.history.replaceState({}, '', '/trucks');
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const maintenanceCell = (r: TruckRow) => {
        const variant = r.level === 'red' ? 'danger' : r.level === 'yellow' ? 'warning' : 'success';
        const label = r.level === 'red'
            ? 'Urgent'
            : `${r.remaining} ${r.unit} restant${Number(r.remaining) > 1 ? 's' : ''}`;
        return <Badge variant={variant}>{label}</Badge>;
    };

    const fuelCell = (litres: number | null) => {
        if (litres == null) return <span className="text-[var(--color-text-muted)]">—</span>;
        const n = Number(litres);
        if (!Number.isFinite(n)) return <span className="text-[var(--color-text-muted)]">—</span>;
        const variant = n < 30 ? 'danger' : n < 80 ? 'warning' : 'success';
        return <Badge variant={variant}>{n.toFixed(0)} L</Badge>;
    };

    const statusCell = (r: TruckRow) => {
        if (!r.is_active) return <Badge variant="muted">Hors service</Badge>;
        if (!r.is_available) return <Badge variant="danger">Indisponible</Badge>;
        return <Badge variant="success">Disponible</Badge>;
    };

    return (
        <AuthenticatedLayout title="Camions">
            <Head title="Camions" />

            <PageHeader
                icon={<TruckIcon size={22} className="text-[var(--color-primary)]" />}
                title="Camions"
                actions={canCreate ? (
                    <Button icon={<Plus size={16} />} onClick={() => setFormState({ mode: 'create' })}>Ajouter</Button>
                ) : undefined}
            />

            {maintenanceDueCount > 0 && (
                <div className="mb-4 flex items-center gap-2 rounded-xl border border-amber-300 bg-amber-50 dark:bg-amber-900/20 dark:border-amber-700 px-4 py-3 text-sm text-amber-800 dark:text-amber-300">
                    <AlertTriangle size={16} />
                    <span>{maintenanceDueCount} camion(s) nécessitent une maintenance</span>
                </div>
            )}

            <Card padding={false}>
                <div className="p-5">
                    <DataTable
                        data={trucks}
                        columns={[
                            { key: 'matricule', label: 'Matricule' },
                            { key: 'transporter', label: 'Transporteur', hideOnMobile: true },
                            { key: 'total_kilometers', label: 'Compteur', render: (r) => `${Number(r.total_kilometers).toLocaleString('fr-FR')} km` },
                            { key: 'fleeti_last_fuel_level', label: 'Carburant', render: (r) => fuelCell(r.fleeti_last_fuel_level) },
                            { key: 'level', label: 'Maintenance', render: maintenanceCell },
                            { key: 'is_available', label: 'Statut', render: statusCell },
                            {
                                key: 'actions', label: '', sortable: false,
                                render: (r) => (
                                    <ActionButtons
                                        onView={() => setDetails(r)}
                                        onEdit={canEdit ? () => openEdit(r) : undefined}
                                        onDelete={canDelete ? () => setDeleteUrl(`/trucks/${r.id}/destroy`) : undefined}
                                    />
                                ),
                            },
                        ]}
                        searchable
                        exportable
                        exportFilename={`camions-${new Date().toISOString().slice(0, 10)}.csv`}
                        searchKeys={['matricule', 'transporter']}
                    />
                </div>
            </Card>

            {details && (
                <TruckDetailsDrawer
                    truck={details}
                    canEdit={canEdit}
                    onEdit={() => openEdit(details)}
                    onClose={() => setDetails(null)}
                />
            )}

            {formState && (
                <TruckFormDrawer
                    key={formState.mode === 'edit' ? `edit-${formState.truck.id}` : 'create'}
                    mode={formState.mode}
                    truck={formState.mode === 'edit' ? formState.truck : null}
                    transporters={transporters}
                    defaultCapacityTonnage={defaultCapacityTonnage}
                    defaultTargetRotationsPerWeek={defaultTargetRotationsPerWeek}
                    onClose={() => setFormState(null)}
                />
            )}

            <ConfirmDialog open={!!deleteUrl} onClose={() => setDeleteUrl(null)} deleteUrl={deleteUrl ?? undefined} />
        </AuthenticatedLayout>
    );
}
