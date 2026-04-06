import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import DataTable from '@/components/ui/DataTable';
import { Truck as TruckIcon, Route } from 'lucide-react';

interface MaintenanceRecord {
    id: number;
    maintenance_date: string;
    type: string;
    description: string | null;
    cost: number | null;
}

interface Props {
    driver: { id: number; name: string };
    truck: {
        id: number;
        matricule: string;
        total_kilometers: number;
        is_active: boolean;
        transporter: { id: number; name: string } | null;
        maintenances: MaintenanceRecord[];
    } | null;
    myTripsCount: number;
}

export default function MyTruck({ driver, truck, myTripsCount }: Props) {
    if (!truck) {
        return (
            <AuthenticatedLayout title="Mon camion">
                <Head title="Mon camion" />
                <Card>
                    <div className="text-center py-12 text-[var(--color-text-muted)]">
                        <TruckIcon size={48} className="mx-auto mb-3 opacity-40" />
                        <p>Aucun camion assigné</p>
                    </div>
                </Card>
            </AuthenticatedLayout>
        );
    }

    return (
        <AuthenticatedLayout title="Mon camion">
            <Head title="Mon camion" />

            <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                <Card>
                    <div className="flex items-center gap-3">
                        <div className="p-2 rounded-lg bg-[var(--color-primary)]/10">
                            <TruckIcon size={20} className="text-[var(--color-primary)]" />
                        </div>
                        <div>
                            <p className="text-xs text-[var(--color-text-muted)]">Matricule</p>
                            <p className="font-semibold text-[var(--color-text)]">{truck.matricule}</p>
                        </div>
                    </div>
                </Card>
                <Card>
                    <div className="flex items-center gap-3">
                        <div className="p-2 rounded-lg bg-emerald-500/10">
                            <Route size={20} className="text-emerald-500" />
                        </div>
                        <div>
                            <p className="text-xs text-[var(--color-text-muted)]">Mes voyages (ce mois)</p>
                            <p className="font-semibold text-[var(--color-text)]">{myTripsCount}</p>
                        </div>
                    </div>
                </Card>
                <Card>
                    <div className="space-y-2">
                        <div className="flex justify-between text-sm">
                            <span className="text-[var(--color-text-muted)]">Compteur</span>
                            <span className="text-[var(--color-text)]">{truck.total_kilometers?.toLocaleString('fr-FR')} km</span>
                        </div>
                        <div className="flex justify-between text-sm">
                            <span className="text-[var(--color-text-muted)]">Transporteur</span>
                            <span className="text-[var(--color-text)]">{truck.transporter?.name ?? '-'}</span>
                        </div>
                        <div className="flex justify-between text-sm">
                            <span className="text-[var(--color-text-muted)]">Actif</span>
                            <Badge variant={truck.is_active ? 'success' : 'muted'}>{truck.is_active ? 'Oui' : 'Non'}</Badge>
                        </div>
                    </div>
                </Card>
            </div>

            <Card>
                <h3 className="text-lg font-semibold text-[var(--color-text)] mb-4">Historique maintenance</h3>
                <DataTable
                    data={truck.maintenances ?? []}
                    columns={[
                        { key: 'maintenance_date', label: 'Date' },
                        { key: 'type', label: 'Type' },
                        { key: 'description', label: 'Description', hideOnMobile: true },
                        { key: 'cost', label: 'Coût', hideOnMobile: true, render: (r) => r.cost != null ? `${r.cost.toLocaleString('fr-FR')} MRU` : '-' },
                    ]}
                    searchable={false}
                    emptyMessage="Aucune maintenance enregistrée"
                />
            </Card>
        </AuthenticatedLayout>
    );
}
