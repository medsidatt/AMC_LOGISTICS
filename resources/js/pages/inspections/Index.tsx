import { Head, Link, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import DataTable from '@/components/ui/DataTable';
import { ShieldCheck, Plus, Wrench } from 'lucide-react';

interface InspectionRow {
    id: number;
    inspection_date: string | null;
    truck: { id: number; matricule: string } | null;
    inspector: string | null;
    category: string;
    status: string;
    issues_count: number;
    validator: string | null;
    validated_at: string | null;
    vehicle_photo_url: string | null;
}

interface MaintenanceRow {
    id: number;
    maintenance_date: string | null;
    truck: { id: number; matricule: string } | null;
    oil_type: string | null;
    oil_change_km: number | null;
    next_oil_change_km: number | null;
    hydraulic_status: string | null;
    gearbox_status: string | null;
    differential_status: string | null;
    greasing_status: string | null;
    filter_oil_changed: boolean;
    filter_hydraulic_changed: boolean;
    filter_air_changed: boolean;
    filter_fuel_changed: boolean;
    notes: string | null;
}

interface Props {
    inspectionsByCategory: Record<string, InspectionRow[]>;
    maintenance: MaintenanceRow[];
    cutoff: string;
    options: {
        categories: Record<string, string>;
        conditions: Record<string, string>;
        oilTypes: Record<string, string>;
    };
}

export default function InspectionsIndex({ inspectionsByCategory, maintenance, options }: Props) {
    const { auth } = usePage().props as any;
    const canCreate = Array.isArray(auth?.permissions) && auth.permissions.includes('inspection-create');

    const categoryOrder = ['safety', 'compliance', 'mechanical', 'comprehensive'];

    // Flatten + enrich with searchable top-level fields
    const inspectionRows = categoryOrder
        .flatMap((key) => inspectionsByCategory[key] ?? [])
        .map((row) => ({
            ...row,
            matricule: row.truck?.matricule ?? '',
            inspector_name: row.inspector ?? '',
        }));

    const totalInspections = inspectionRows.length;

    const maintenanceRows = maintenance.map((row) => {
        const filters: string[] = [];
        if (row.filter_oil_changed) filters.push('Huile');
        if (row.filter_hydraulic_changed) filters.push('Hydraulique');
        if (row.filter_air_changed) filters.push('Air');
        if (row.filter_fuel_changed) filters.push('Carburant');
        return {
            ...row,
            matricule: row.truck?.matricule ?? '',
            oil_type_label: row.oil_type ? (options.oilTypes[row.oil_type] ?? row.oil_type) : '',
            filters_label: filters.join(', '),
        };
    });

    return (
        <AuthenticatedLayout>
            <Head title="Inspections & Maintenance" />
            <div className="space-y-4">
                <div className="flex items-center justify-between flex-wrap gap-3">
                    <div className="flex items-center gap-2">
                        <ShieldCheck size={22} className="text-emerald-500" />
                        <h1 className="text-xl font-semibold">Inspections & Maintenance</h1>
                    </div>
                    {canCreate && (
                        <Link href="/logistics/inspections/create">
                            <Button>
                                <Plus size={16} className="mr-1" />
                                Nouvelle inspection
                            </Button>
                        </Link>
                    )}
                </div>

                <Card>
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="text-xs uppercase text-[var(--color-text-muted)] font-medium mr-2">Aperçu</span>
                        <Badge variant={maintenance.length === 0 ? 'muted' : 'warning'}>
                            Maintenance: {maintenance.length}
                        </Badge>
                        <Badge variant="muted">Total inspections: {totalInspections}</Badge>
                    </div>
                </Card>

                <Card padding={false}>
                    <div className="p-5 pb-2 flex items-center gap-2">
                        <ShieldCheck size={18} className="text-emerald-500" />
                        <h2 className="text-base font-semibold">Inspections</h2>
                        <Badge variant={totalInspections === 0 ? 'muted' : 'primary'}>{totalInspections}</Badge>
                    </div>
                    <div className="p-5 pt-0">
                        <DataTable
                            data={inspectionRows}
                            columns={[
                                {
                                    key: 'vehicle_photo_url', label: 'Photo', sortable: false,
                                    render: (r) => r.vehicle_photo_url ? (
                                        <a href={r.vehicle_photo_url} target="_blank" rel="noopener noreferrer" title="Ouvrir la photo">
                                            <img src={r.vehicle_photo_url} alt="Véhicule" className="w-16 h-12 object-cover rounded border border-[var(--color-border)] cursor-zoom-in hover:opacity-90 transition" />
                                        </a>
                                    ) : <span className="text-[var(--color-text-muted)] text-xs">—</span>,
                                },
                                { key: 'inspection_date', label: 'Date' },
                                { key: 'matricule', label: 'Camion' },
                                { key: 'inspector_name', label: 'Inspecteur' },
                                {
                                    key: 'actions', label: '', sortable: false,
                                    render: (r) => (
                                        <Link href={`/hse/inspections/${r.id}`} className="text-[var(--color-primary)] hover:underline text-sm">
                                            Voir
                                        </Link>
                                    ),
                                },
                            ]}
                            searchable
                            searchKeys={['matricule', 'inspector_name', 'inspection_date']}
                            emptyMessage="Aucune inspection."
                            exportable
                            exportFilename={`inspections-${new Date().toISOString().slice(0, 10)}.csv`}
                        />
                    </div>
                </Card>

                <Card padding={false}>
                    <div className="p-5 pb-2 flex items-center gap-2">
                        <Wrench size={18} className="text-amber-500" />
                        <h2 className="text-base font-semibold">Maintenance</h2>
                        <Badge variant={maintenance.length === 0 ? 'muted' : 'warning'}>{maintenance.length}</Badge>
                    </div>
                    <div className="p-5 pt-0">
                        <DataTable
                            data={maintenanceRows}
                            columns={[
                                { key: 'maintenance_date', label: 'Date' },
                                { key: 'matricule', label: 'Camion' },
                                { key: 'oil_type_label', label: 'Huile' },
                                {
                                    key: 'oil_change_km', label: 'Km vidange',
                                    render: (r) => r.oil_change_km != null ? r.oil_change_km.toLocaleString('fr-FR') : '—',
                                },
                                {
                                    key: 'next_oil_change_km', label: 'Prochaine',
                                    render: (r) => r.next_oil_change_km != null ? r.next_oil_change_km.toLocaleString('fr-FR') : '—',
                                },
                                {
                                    key: 'filters_label', label: 'Filtres',
                                    render: (r) => r.filters_label ? <Badge variant="success">{r.filters_label}</Badge> : <span className="text-[var(--color-text-muted)]">—</span>,
                                },
                                {
                                    key: 'gearbox_status', label: 'Boîte',
                                    render: (r) => r.gearbox_status ? <Badge variant="muted">{r.gearbox_status}</Badge> : '—',
                                },
                                {
                                    key: 'greasing_status', label: 'Graissage',
                                    render: (r) => r.greasing_status ? <Badge variant="muted">{r.greasing_status}</Badge> : '—',
                                },
                                {
                                    key: 'notes', label: 'Notes',
                                    render: (r) => r.notes
                                        ? <span className="text-xs text-[var(--color-text-secondary)] block max-w-[240px] truncate" title={r.notes}>{r.notes}</span>
                                        : <span className="text-[var(--color-text-muted)]">—</span>,
                                },
                            ]}
                            searchable
                            searchKeys={['matricule', 'maintenance_date', 'oil_type_label', 'notes']}
                            emptyMessage="Aucune maintenance enregistrée sur la période."
                            exportable
                            exportFilename={`maintenance-${new Date().toISOString().slice(0, 10)}.csv`}
                        />
                    </div>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
