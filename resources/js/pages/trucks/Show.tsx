import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import DataTable from '@/components/ui/DataTable';
import { ArrowLeft, Pencil } from 'lucide-react';

interface Tracking {
    id: number;
    reference: string;
    driver: string | null;
    provider: string | null;
    provider_net_weight: number | null;
    client_net_weight: number | null;
    client_date: string | null;
}

interface Maintenance {
    id: number;
    maintenance_date: string;
    maintenance_type: string;
    kilometers_at_maintenance: number;
    notes: string | null;
}

interface Props {
    truck: {
        id: number;
        matricule: string;
        transporter: string | null;
        maintenance_type: string;
        is_active: boolean;
        total_kilometers: number;
        fleeti_id: string | null;
    };
    maintenanceInfo: Record<string, any>;
    recentTrackings: Tracking[];
    maintenances: Maintenance[];
}

export default function TrucksShow({ truck, maintenanceInfo, recentTrackings, maintenances }: Props) {
    const fields = [
        ['Matricule', truck.matricule],
        ['Transporteur', truck.transporter],
        ['Type Maintenance', truck.maintenance_type],
        ['Compteur (km)', truck.total_kilometers?.toLocaleString('fr-FR')],
        ['Actif', truck.is_active ? 'Oui' : 'Non'],
        ['Fleeti ID', truck.fleeti_id],
    ];

    return (
        <AuthenticatedLayout title={truck.matricule}>
            <Head title={truck.matricule} />

            <div className="flex items-center justify-between mb-4">
                <Button variant="ghost" icon={<ArrowLeft size={16} />} onClick={() => window.history.back()}>
                    Retour
                </Button>
                <Button variant="secondary" icon={<Pencil size={16} />} onClick={() => window.location.href = `/trucks/${truck.id}/edit`}>
                    Modifier
                </Button>
            </div>

            <div className="grid gap-6">
                <Card>
                    <h3 className="text-lg font-semibold text-[var(--color-text)] mb-4">Informations</h3>
                    <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        {fields.map(([label, value]) => (
                            <div key={label as string}>
                                <p className="text-xs text-[var(--color-text-muted)] uppercase">{label}</p>
                                <p className="text-sm text-[var(--color-text)] mt-0.5">{value || '-'}</p>
                            </div>
                        ))}
                    </div>
                </Card>

                <Card>
                    <h3 className="text-lg font-semibold text-[var(--color-text)] mb-4">Derniers transports</h3>
                    <DataTable
                        data={recentTrackings}
                        columns={[
                            { key: 'reference', label: 'Référence' },
                            { key: 'driver', label: 'Conducteur', hideOnMobile: true },
                            { key: 'provider', label: 'Fournisseur', hideOnMobile: true },
                            { key: 'provider_net_weight', label: 'Poids Fourn.', render: (r) => r.provider_net_weight?.toLocaleString('fr-FR') ?? '-' },
                            { key: 'client_net_weight', label: 'Poids Client', render: (r) => r.client_net_weight?.toLocaleString('fr-FR') ?? '-' },
                            { key: 'client_date', label: 'Date', render: (r) => r.client_date ?? '-' },
                        ]}
                        searchable={false}
                        emptyMessage="Aucun transport récent"
                    />
                </Card>

                <Card>
                    <h3 className="text-lg font-semibold text-[var(--color-text)] mb-4">Historique maintenance</h3>
                    <DataTable
                        data={maintenances}
                        columns={[
                            { key: 'maintenance_date', label: 'Date' },
                            { key: 'maintenance_type', label: 'Type' },
                            { key: 'kilometers_at_maintenance', label: 'Km', render: (r) => r.kilometers_at_maintenance?.toLocaleString('fr-FR') ?? '-' },
                            { key: 'notes', label: 'Notes', hideOnMobile: true },
                        ]}
                        searchable={false}
                        emptyMessage="Aucune maintenance enregistrée"
                    />
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
