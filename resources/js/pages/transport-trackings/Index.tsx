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
import { Plus, FileText, Image } from 'lucide-react';

interface Document {
    id: number;
    original_name: string;
    mime_type: string;
    type: string;
    file_url: string;
}

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
    documents: Document[];
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

    const applyFilter = (key: string, value: string | number | null) => {
        const newFilters = { ...filters, [key]: value ? String(value) : '' };
        // Remove empty filters
        Object.keys(newFilters).forEach((k) => { if (!newFilters[k]) delete newFilters[k]; });
        router.get('/transport_tracking', newFilters, { preserveState: true, preserveScroll: true });
    };

    const toOptions = (items: DropdownItem[], key: 'name' | 'matricule' = 'name') =>
        items.map((i) => ({ value: i.id, label: (i as any)[key] ?? String(i.id) }));

    const isPdf = (doc: Document) => doc.mime_type === 'application/pdf';

    return (
        <AuthenticatedLayout title="Suivi Transport">
            <Head title="Suivi Transport" />

            <div className="flex justify-end mb-4">
                <Button icon={<Plus size={16} />} onClick={() => router.visit('/transport_tracking/create-page')}>
                    Ajouter
                </Button>
            </div>

            <Card className="mb-4">
                <div className="grid sm:grid-cols-2 lg:grid-cols-5 gap-4">
                    <FormSelect label="Camion" placeholder="Tous" options={toOptions(trucks, 'matricule')} value={filters.truck_id ?? null} onChange={(v) => applyFilter('truck_id', v)} wrapperClass="mb-0" />
                    <FormSelect label="Conducteur" placeholder="Tous" options={toOptions(drivers)} value={filters.driver_id ?? null} onChange={(v) => applyFilter('driver_id', v)} wrapperClass="mb-0" />
                    <FormSelect label="Fournisseur" placeholder="Tous" options={toOptions(providers)} value={filters.provider_id ?? null} onChange={(v) => applyFilter('provider_id', v)} wrapperClass="mb-0" />
                    <FormSelect label="Produit" placeholder="Tous" options={toOptions(products)} value={filters.product ?? null} onChange={(v) => applyFilter('product', v)} wrapperClass="mb-0" />
                    <FormSelect label="Transporteur" placeholder="Tous" options={toOptions(transporters)} value={filters.transporter_id ?? null} onChange={(v) => applyFilter('transporter_id', v)} wrapperClass="mb-0" />
                </div>
            </Card>

            <Card padding={false}>
                <div className="p-5">
                    <DataTable
                        data={trackings.data}
                        columns={[
                            { key: 'reference', label: 'Réf.' },
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
                            { key: 'files', label: 'Fichiers', sortable: false, hideOnMobile: true, render: (r) => (
                                r.documents && r.documents.length > 0 ? (
                                    <div className="flex items-center gap-1">
                                        {r.documents.map((doc) => (
                                            <a
                                                key={doc.id}
                                                href={doc.file_url}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="p-1 rounded hover:bg-[var(--color-surface-hover)] transition-colors"
                                                title={doc.original_name}
                                            >
                                                {isPdf(doc)
                                                    ? <FileText size={16} className="text-red-500" />
                                                    : <Image size={16} className="text-blue-500" />
                                                }
                                            </a>
                                        ))}
                                    </div>
                                ) : <span className="text-[var(--color-text-muted)]">-</span>
                            )},
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
