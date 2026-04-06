import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import DataTable from '@/components/ui/DataTable';
import Pagination from '@/components/ui/Pagination';

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

export default function MyTrips({ driver, trips, truck }: Props) {
    return (
        <AuthenticatedLayout title="Mes voyages">
            <Head title="Mes voyages" />

            <div className="mb-4 flex flex-wrap items-center gap-4 text-sm text-[var(--color-text-secondary)]">
                <span>Conducteur : <strong className="text-[var(--color-text)]">{driver.name}</strong></span>
                {truck && <span>Camion : <strong className="text-[var(--color-text)]">{truck.matricule}</strong></span>}
            </div>

            <Card padding={false}>
                <div className="p-5">
                    <DataTable
                        data={trips.data}
                        columns={[
                            { key: 'reference', label: 'Référence' },
                            { key: 'product', label: 'Produit', hideOnMobile: true },
                            { key: 'provider', label: 'Fournisseur', hideOnMobile: true, render: (r) => r.provider?.name ?? '-' },
                            { key: 'truck', label: 'Camion', hideOnMobile: true, render: (r) => r.truck?.matricule ?? '-' },
                            { key: 'provider_net_weight', label: 'Poids Fourn.', render: (r) => r.provider_net_weight?.toLocaleString('fr-FR') ?? '-' },
                            { key: 'client_net_weight', label: 'Poids Client', render: (r) => r.client_net_weight?.toLocaleString('fr-FR') ?? '-' },
                            { key: 'provider_date', label: 'Date', render: (r) => r.provider_date ?? '-' },
                        ]}
                        perPage={trips.per_page}
                        searchable
                    />
                </div>
                <div className="px-5 pb-5">
                    <Pagination meta={trips} />
                </div>
            </Card>
        </AuthenticatedLayout>
    );
}
