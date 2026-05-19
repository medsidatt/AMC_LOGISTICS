import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import DataTable from '@/components/ui/DataTable';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import ActionButtons from '@/components/ui/ActionButtons';
import ConfirmDialog from '@/components/ui/ConfirmDialog';
import { Plus } from 'lucide-react';

interface NameRef { id: number; name?: string; code?: string }

interface Demand {
    id: number;
    week_start_date: string;
    project: NameRef | null;
    provider: NameRef | null;
    client_name: string | null;
    required_tons: number;
    required_trucks: number | null;
    product: string | null;
    priority: number;
    priority_label: string;
    allocated_tons: number;
    coverage_rate: number;
    creator: NameRef | null;
    notes: string | null;
}

interface Props {
    demands: Demand[];
    weekFilter: string | null;
}

export default function DemandsIndex({ demands, weekFilter }: Props) {
    const [deleteUrl, setDeleteUrl] = useState<string | null>(null);

    return (
        <AuthenticatedLayout title="Demandes client">
            <Head title="Demandes client" />

            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                <div className="text-sm text-[var(--color-text-muted)]">
                    {weekFilter ? `Semaine du ${weekFilter}` : 'Toutes les demandes'}
                </div>
                <div className="flex gap-2">
                    {weekFilter && (
                        <Button variant="secondary" onClick={() => router.get('/logistics/demands')}>Voir tout</Button>
                    )}
                    <Button icon={<Plus size={16} />} onClick={() => router.get('/logistics/demands/create')}>Nouvelle demande</Button>
                </div>
            </div>

            <Card padding={false}>
                <div className="p-5">
                    <DataTable
                        data={demands}
                        columns={[
                            { key: 'week_start_date', label: 'Semaine' },
                            { key: 'project', label: 'Projet / Client', render: (r) => r.project?.code ?? r.project?.name ?? r.client_name ?? '—' },
                            { key: 'provider', label: 'Carrière', render: (r) => r.provider?.name ?? 'Libre' },
                            { key: 'product', label: 'Produit', render: (r) => r.product ?? '—' },
                            { key: 'required_tons', label: 'Demande (t)', render: (r) => r.required_tons.toLocaleString('fr-FR') },
                            { key: 'required_trucks', label: 'Camions', render: (r) => r.required_trucks ?? '—' },
                            { key: 'priority_label', label: 'Priorité', render: (r) => <Badge variant="muted">{r.priority_label}</Badge> },
                            {
                                key: 'coverage_rate', label: 'Couverture',
                                render: (r) => {
                                    const pct = Math.round(r.coverage_rate * 100);
                                    const variant = pct >= 100 ? 'success' : pct >= 70 ? 'warning' : 'danger';
                                    return <Badge variant={variant}>{pct} %</Badge>;
                                },
                            },
                            {
                                key: 'actions', label: '', sortable: false,
                                render: (r) => (
                                    <ActionButtons
                                        editHref={`/logistics/demands/${r.id}/edit`}
                                        onDelete={() => setDeleteUrl(`/logistics/demands/${r.id}`)}
                                    />
                                ),
                            },
                        ]}
                        searchable
                        searchKeys={['client_name']}
                    />
                </div>
            </Card>

            <ConfirmDialog
                open={deleteUrl !== null}
                title="Supprimer cette demande ?"
                message="Cette action est irréversible. Les affectations liées seront détachées."
                onClose={() => setDeleteUrl(null)}
                onConfirm={() => {
                    if (deleteUrl) router.delete(deleteUrl);
                    setDeleteUrl(null);
                }}
            />
        </AuthenticatedLayout>
    );
}
