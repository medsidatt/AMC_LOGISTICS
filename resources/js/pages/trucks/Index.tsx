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

interface Truck {
    id: number;
    matricule: string;
    transporter: string | null;
    maintenance_type: string;
    is_active: boolean;
    total_kilometers: number;
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
                            { key: 'maintenance_type', label: 'Type Maintenance', hideOnMobile: true },
                            { key: 'total_kilometers', label: 'Compteur (km)', hideOnMobile: true, render: (r) => r.total_kilometers?.toLocaleString('fr-FR') ?? '-' },
                            { key: 'level', label: 'État Maintenance', render: (r) => levelBadge(r.level) },
                            { key: 'remaining', label: 'Restant', hideOnMobile: true, render: (r) => `${r.remaining} ${r.unit}` },
                            { key: 'is_active', label: 'Actif', render: (r) => <Badge variant={r.is_active ? 'success' : 'muted'}>{r.is_active ? 'Oui' : 'Non'}</Badge> },
                            {
                                key: 'actions', label: 'Actions', sortable: false,
                                render: (r) => (
                                    <ActionButtons
                                        viewHref={`/trucks/${r.id}/show`}
                                        editHref={`/trucks/${r.id}/edit`}
                                        onDelete={() => setDeleteUrl(`/trucks/${r.id}`)}
                                    />
                                ),
                            },
                        ]}
                        searchable
                        searchKeys={['matricule', 'transporter']}
                    />
                </div>
            </Card>

            <ConfirmDialog open={!!deleteUrl} onClose={() => setDeleteUrl(null)} deleteUrl={deleteUrl ?? undefined} />
        </AuthenticatedLayout>
    );
}
