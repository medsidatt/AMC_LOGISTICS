import { Head } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import DataTable from '@/components/ui/DataTable';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import ActionButtons from '@/components/ui/ActionButtons';
import ConfirmDialog from '@/components/ui/ConfirmDialog';
import { Plus, AlertTriangle } from 'lucide-react';
import { usePermission } from '@/hooks/usePermission';

interface Truck {
    id: number;
    matricule: string;
    transporter: string | null;
    is_active: boolean;
    is_available: boolean;
    total_kilometers: number;
    fleeti_last_fuel_level: number | null;
    level: string;
    remaining: number | string;
    unit: string;
}

interface Props {
    trucks: Truck[];
    maintenanceDueCount: number;
}

export default function TrucksIndex({ trucks, maintenanceDueCount }: Props) {
    const [deleteUrl, setDeleteUrl] = useState<string | null>(null);
    const { can } = usePermission();
    const canCreate = can('truck-create');
    const canEdit = can('truck-edit');
    const canDelete = can('truck-delete');

    const maintenanceCell = (r: Truck) => {
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

    const statusCell = (r: Truck) => {
        if (!r.is_active) return <Badge variant="muted">Hors service</Badge>;
        if (!r.is_available) return <Badge variant="danger">Indisponible</Badge>;
        return <Badge variant="success">Disponible</Badge>;
    };

    return (
        <AuthenticatedLayout title="Camions">
            <Head title="Camions" />

            {maintenanceDueCount > 0 && (
                <div className="mb-4 flex items-center gap-2 rounded-xl border border-amber-300 bg-amber-50 dark:bg-amber-900/20 dark:border-amber-700 px-4 py-3 text-sm text-amber-800 dark:text-amber-300">
                    <AlertTriangle size={16} />
                    <span>{maintenanceDueCount} camion(s) nécessitent une maintenance</span>
                </div>
            )}

            {canCreate && (
                <div className="flex justify-end mb-4">
                    <Button icon={<Plus size={16} />} onClick={() => window.location.href = '/trucks/create-page'}>
                        Ajouter
                    </Button>
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
                                        viewHref={`/trucks/${r.id}/show-page`}
                                        editHref={canEdit ? `/trucks/${r.id}/edit-page` : undefined}
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

            <ConfirmDialog open={!!deleteUrl} onClose={() => setDeleteUrl(null)} deleteUrl={deleteUrl ?? undefined} />
        </AuthenticatedLayout>
    );
}
