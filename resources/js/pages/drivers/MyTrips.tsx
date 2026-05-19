import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import DataTable from '@/components/ui/DataTable';
import Pagination from '@/components/ui/Pagination';
import { Route, Weight, Truck as TruckIcon } from 'lucide-react';

interface Trip {
    id: number;
    reference: string;
    provider_date: string | null;
    client_date: string | null;
    provider_net_weight: number | null;
    client_net_weight: number | null;
    product: string | null;
    truck: { id: number; matricule: string } | null;
    provider: { id: number; name: string } | null;
}

interface Props {
    driver: { id: number; name: string };
    trips: { data: Trip[]; current_page: number; last_page: number; per_page: number; total: number; from: number | null; to: number | null };
    truck: { id: number; matricule: string } | null;
}

function KpiTile({ icon, label, value, color }: { icon: React.ReactNode; label: string; value: string | number; color: string }) {
    return (
        <Card>
            <div className="flex items-center gap-3">
                <div className={`p-2.5 rounded-lg ${color}`}>{icon}</div>
                <div className="min-w-0">
                    <div className="text-xs text-[var(--color-text-muted)] uppercase tracking-wide">{label}</div>
                    <div className="text-2xl font-bold leading-tight">{value}</div>
                </div>
            </div>
        </Card>
    );
}

export default function MyTrips({ driver, trips, truck }: Props) {
    const rows = trips.data.map((t) => ({
        ...t,
        truck_matricule: t.truck?.matricule ?? '',
        provider_name: t.provider?.name ?? '',
    }));

    // Page-level summary (over the current page only — server-side aggregates would be ideal but acceptable for now)
    const totalProviderTonnage = trips.data.reduce((s, t) => s + (Number(t.provider_net_weight) || 0), 0);
    const totalClientTonnage = trips.data.reduce((s, t) => s + (Number(t.client_net_weight) || 0), 0);

    return (
        <AuthenticatedLayout title="Mes voyages">
            <Head title="Mes voyages" />

            <div className="space-y-4">
                {/* Identity strip */}
                <Card>
                    <div className="flex flex-wrap items-center gap-4 text-sm">
                        <div className="flex items-center gap-2">
                            <span className="text-[var(--color-text-muted)]">Conducteur</span>
                            <strong>{driver.name}</strong>
                        </div>
                        {truck && (
                            <div className="flex items-center gap-2">
                                <TruckIcon size={14} className="text-[var(--color-primary)]" />
                                <strong>{truck.matricule}</strong>
                            </div>
                        )}
                    </div>
                </Card>

                {/* KPIs */}
                <div className="grid grid-cols-2 md:grid-cols-2 gap-3">
                    <KpiTile
                        icon={<Route size={18} />}
                        label="Total rotations"
                        value={trips.total}
                        color="bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400"
                    />
                    <KpiTile
                        icon={<Weight size={18} />}
                        label="Tonnage de la page"
                        value={`${totalProviderTonnage.toLocaleString('fr-FR', { maximumFractionDigits: 1 })} t`}
                        color="bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400"
                    />
                </div>

                {/* Table */}
                <Card padding={false}>
                    <div className="p-5">
                        <DataTable
                            data={rows}
                            columns={[
                                { key: 'provider_date', label: 'Date' },
                                { key: 'reference', label: 'Référence' },
                                { key: 'truck_matricule', label: 'Camion', hideOnMobile: true },
                                { key: 'provider_name', label: 'Fournisseur', hideOnMobile: true },
                                { key: 'product', label: 'Produit', hideOnMobile: true },
                                {
                                    key: 'provider_net_weight', label: 'Chargé',
                                    render: (r) => r.provider_net_weight != null
                                        ? `${Number(r.provider_net_weight).toLocaleString('fr-FR', { maximumFractionDigits: 2 })} t`
                                        : '—',
                                },
                                {
                                    key: 'client_net_weight', label: 'Livré',
                                    render: (r) => r.client_net_weight != null
                                        ? `${Number(r.client_net_weight).toLocaleString('fr-FR', { maximumFractionDigits: 2 })} t`
                                        : '—',
                                },
                            ]}
                            perPage={trips.per_page}
                            searchable
                            searchKeys={['reference', 'truck_matricule', 'provider_name', 'product']}
                            exportable
                            exportFilename={`mes-voyages-${new Date().toISOString().slice(0, 10)}.csv`}
                            emptyMessage="Aucun voyage enregistré sur cette page."
                        />
                    </div>
                    <div className="px-5 pb-5">
                        <Pagination meta={trips} />
                    </div>
                </Card>

                <p className="text-xs text-[var(--color-text-muted)] text-center">
                    Tonnage affiché en tonnes métriques. Total page = somme des rotations visibles uniquement.
                </p>
            </div>
        </AuthenticatedLayout>
    );
}
