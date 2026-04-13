import { Head } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import DataTable from '@/components/ui/DataTable';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import ActionButtons from '@/components/ui/ActionButtons';
import ConfirmDialog from '@/components/ui/ConfirmDialog';
import { Plus, AlertTriangle, Wifi, WifiOff, Fuel } from 'lucide-react';

interface Truck {
    id: number;
    matricule: string;
    transporter: string | null;
    maintenance_type: string;
    is_active: boolean;
    total_kilometers: number;
    fleeti_connected: boolean;
    fleeti_last_fuel_level: number | null;
    fleeti_last_synced_at: string | null;
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

    const levelBadge = (level: string) => {
        const variant = level === 'red' ? 'danger' : level === 'yellow' ? 'warning' : 'success';
        const label = level === 'red' ? 'Urgent' : level === 'yellow' ? 'Bientôt' : 'OK';
        return <Badge variant={variant}>{label}</Badge>;
    };

    const fuelBadge = (litres: number | null) => {
        if (litres == null) return <span className="text-[var(--color-text-muted)]">-</span>;
        const n = Number(litres);
        if (!Number.isFinite(n)) return <span className="text-[var(--color-text-muted)]">-</span>;
        const variant = n < 30 ? 'danger' : n < 80 ? 'warning' : 'success';
        return <Badge variant={variant}>{n.toFixed(0)} L</Badge>;
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

            <div className="flex justify-end mb-4">
                <Button icon={<Plus size={16} />} onClick={() => window.location.href = '/trucks/create-page'}>
                    Ajouter
                </Button>
            </div>

            <Card padding={false}>
                <div className="p-5">
                    <DataTable
                        data={trucks}
                        columns={[
                            { key: 'matricule', label: 'Matricule' },
                            { key: 'transporter', label: 'Transporteur', hideOnMobile: true },
                            { key: 'total_kilometers', label: 'Compteur (km)', hideOnMobile: true, render: (r) => Number(r.total_kilometers).toLocaleString('fr-FR') },
                            {
                                key: 'fleeti_connected', label: 'GPS', sortable: false,
                                render: (r) => r.fleeti_connected
                                    ? <Wifi size={14} className="inline text-emerald-500" />
                                    : <WifiOff size={14} className="inline text-[var(--color-text-muted)]" />,
                            },
                            { key: 'fleeti_last_fuel_level', label: 'Carburant', render: (r) => fuelBadge(r.fleeti_last_fuel_level) },
                            { key: 'level', label: 'État Maintenance', render: (r) => levelBadge(r.level) },
                            { key: 'remaining', label: 'Restant', hideOnMobile: true, render: (r) => `${r.remaining} ${r.unit}` },
                            { key: 'fleeti_last_synced_at', label: 'Dernière sync', hideOnMobile: true, render: (r) => r.fleeti_last_synced_at ?? '-' },
                            { key: 'is_active', label: 'Actif', render: (r) => <Badge variant={r.is_active ? 'success' : 'muted'}>{r.is_active ? 'Oui' : 'Non'}</Badge> },
                            {
                                key: 'actions', label: 'Actions', sortable: false,
                                render: (r) => (
                                    <ActionButtons
                                        viewHref={`/trucks/${r.id}/show-page`}
                                        editHref={`/trucks/${r.id}/edit-page`}
                                        onDelete={() => setDeleteUrl(`/trucks/${r.id}/destroy`)}
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
