import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import DataTable from '@/components/ui/DataTable';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import FormSelect from '@/components/ui/FormSelect';
import ActionButtons from '@/components/ui/ActionButtons';
import ConfirmDialog from '@/components/ui/ConfirmDialog';
import Pagination from '@/components/ui/Pagination';
import { Plus, Filter, Paperclip } from 'lucide-react';

interface Tracking {
    id: number;
    reference: string;
    product: string;
    base: string;
    provider_date: string | null;
    client_date: string | null;
    provider_net_weight: number | null;
    client_net_weight: number | null;
    gap: number;
    has_files: boolean;
    truck: { id: number; matricule: string } | null;
    driver: { id: number; name: string } | null;
    provider: { id: number; name: string } | null;
}

interface DropdownItem {
    id: number | string;
    name?: string;
    matricule?: string;
}

interface Props {
    trackings: { data: Tracking[]; current_page: number; last_page: number; per_page: number; total: number; from: number | null; to: number | null };
    filters: Record<string, string>;
    transporters: DropdownItem[];
    trucks: DropdownItem[];
    drivers: DropdownItem[];
    providers: DropdownItem[];
    products: DropdownItem[];
}

export default function TrackingsIndex({ trackings, filters, transporters, trucks, drivers, providers, products }: Props) {
    const [deleteUrl, setDeleteUrl] = useState<string | null>(null);
    const [showFilters, setShowFilters] = useState(false);
    const [localFilters, setLocalFilters] = useState(filters);

    const applyFilters = () => {
        router.get('/transport_tracking', localFilters as any, { preserveState: true });
    };

    const clearFilters = () => {
        setLocalFilters({});
        router.get('/transport_tracking', {}, { preserveState: true });
    };

    const toOptions = (items: DropdownItem[], key: 'name' | 'matricule' = 'name') =>
        items.map((i) => ({ value: i.id, label: (i as any)[key] ?? String(i.id) }));

    return (
        <AuthenticatedLayout title="Suivi Transport">
            <Head title="Suivi Transport" />

            <div className="flex flex-wrap items-center justify-between gap-3 mb-4">
                <Button variant="secondary" icon={<Filter size={16} />} onClick={() => setShowFilters(!showFilters)}>
                    Filtres
                </Button>
                <Button icon={<Plus size={16} />} onClick={() => router.visit('/transport_tracking/create-page')}>
                    Ajouter
                </Button>
            </div>

            {showFilters && (
                <Card className="mb-4">
                    <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <FormSelect label="Camion" placeholder="Tous" options={toOptions(trucks, 'matricule')} value={localFilters.truck_id ?? null} onChange={(v) => setLocalFilters({ ...localFilters, truck_id: v ? String(v) : '' })} />
                        <FormSelect label="Conducteur" placeholder="Tous" options={toOptions(drivers)} value={localFilters.driver_id ?? null} onChange={(v) => setLocalFilters({ ...localFilters, driver_id: v ? String(v) : '' })} />
                        <FormSelect label="Fournisseur" placeholder="Tous" options={toOptions(providers)} value={localFilters.provider_id ?? null} onChange={(v) => setLocalFilters({ ...localFilters, provider_id: v ? String(v) : '' })} />
                        <FormSelect label="Produit" placeholder="Tous" options={toOptions(products)} value={localFilters.product ?? null} onChange={(v) => setLocalFilters({ ...localFilters, product: v ? String(v) : '' })} />
                    </div>
                    <div className="flex gap-2 mt-4">
                        <Button size="sm" onClick={applyFilters}>Appliquer</Button>
                        <Button size="sm" variant="secondary" onClick={clearFilters}>Réinitialiser</Button>
                    </div>
                </Card>
            )}

            <Card padding={false}>
                <div className="p-5">
                    <DataTable
                        data={trackings.data}
                        columns={[
                            { key: 'reference', label: 'Réf.', render: (r) => (
                                <span className="flex items-center gap-1">
                                    {r.reference}
                                    {r.has_files && <Paperclip size={12} className="text-[var(--color-text-muted)]" />}
                                </span>
                            )},
                            { key: 'truck', label: 'Camion', render: (r) => r.truck?.matricule ?? '-', hideOnMobile: true },
                            { key: 'driver', label: 'Conducteur', render: (r) => r.driver?.name ?? '-', hideOnMobile: true },
                            { key: 'provider', label: 'Fournisseur', render: (r) => r.provider?.name ?? '-', hideOnMobile: true },
                            { key: 'provider_net_weight', label: 'Poids Fourn.', render: (r) => r.provider_net_weight?.toLocaleString('fr-FR') ?? '-' },
                            { key: 'client_net_weight', label: 'Poids Client', render: (r) => r.client_net_weight?.toLocaleString('fr-FR') ?? '-' },
                            { key: 'gap', label: 'Écart', render: (r) => (
                                <Badge variant={r.gap < 0 ? 'danger' : r.gap > 0 ? 'warning' : 'success'}>
                                    {r.gap?.toLocaleString('fr-FR')}
                                </Badge>
                            )},
                            { key: 'client_date', label: 'Date', hideOnMobile: true, render: (r) => r.client_date ?? r.provider_date ?? '-' },
                            {
                                key: 'actions', label: 'Actions', sortable: false,
                                render: (r) => (
                                    <ActionButtons
                                        viewHref={`/transport_tracking/${r.id}/show`}
                                        editHref={`/transport_tracking/${r.id}/edit`}
                                        onDelete={() => setDeleteUrl(`/transport_tracking/${r.id}`)}
                                    />
                                ),
                            },
                        ]}
                        perPage={trackings.per_page}
                        searchable
                        searchKeys={['reference']}
                    />
                </div>
                <div className="px-5 pb-5">
                    <Pagination meta={trackings} />
                </div>
            </Card>

            <ConfirmDialog open={!!deleteUrl} onClose={() => setDeleteUrl(null)} deleteUrl={deleteUrl ?? undefined} />
        </AuthenticatedLayout>
    );
}
