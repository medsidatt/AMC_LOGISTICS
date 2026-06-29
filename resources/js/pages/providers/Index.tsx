import { Head } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import PageHeader from '@/components/ui/PageHeader';
import DataTable from '@/components/ui/DataTable';
import Button from '@/components/ui/Button';
import ActionButtons from '@/components/ui/ActionButtons';
import ConfirmDialog from '@/components/ui/ConfirmDialog';
import Pagination from '@/components/ui/Pagination';
import CounterpartyFormDrawer from '@/components/counterparty/CounterpartyFormDrawer';
import CounterpartyDetailsDrawer from '@/components/counterparty/CounterpartyDetailsDrawer';
import { Plus, Building2 } from 'lucide-react';
import { usePermission } from '@/hooks/usePermission';
import { useWorkspaceDrawer } from '@/hooks/useWorkspaceDrawer';
import type { Provider, ProviderPaginator } from './types';

interface Props {
    providers: ProviderPaginator;
    filters: { search?: string };
}

const icon = <Building2 size={18} className="text-[var(--color-primary)]" />;

export default function ProvidersWorkspace({ providers }: Props) {
    const { can } = usePermission();
    const canCreate = can('provider-create');
    const canEdit = can('provider-edit');
    const canDelete = can('provider-delete');

    const { drawer, openCreate, openView, openEdit, close } = useWorkspaceDrawer('/providers');
    const [deleteUrl, setDeleteUrl] = useState<string | null>(null);

    const byId = (id: number | null): Provider | null => providers.data.find((p) => p.id === id) ?? null;
    const viewed = drawer.mode === 'view' ? byId(drawer.id) : null;
    const editing = drawer.mode === 'edit' ? byId(drawer.id) : null;

    return (
        <AuthenticatedLayout title="Fournisseurs">
            <Head title="Fournisseurs" />

            <PageHeader
                icon={<Building2 size={22} className="text-[var(--color-primary)]" />}
                title="Fournisseurs"
                subtitle="Fournisseurs de matériaux"
                actions={canCreate ? <Button icon={<Plus size={16} />} onClick={() => openCreate()}>Ajouter</Button> : undefined}
            />

            <Card padding={false}>
                <div className="p-5">
                    <DataTable
                        data={providers.data}
                        columns={[
                            { key: 'name', label: 'Nom' },
                            { key: 'phone', label: 'Téléphone', hideOnMobile: true },
                            { key: 'email', label: 'Email', hideOnMobile: true },
                            { key: 'address', label: 'Adresse', hideOnMobile: true },
                            {
                                key: 'actions', label: 'Actions', sortable: false,
                                render: (r) => (
                                    <ActionButtons
                                        onView={() => openView(r.id)}
                                        onEdit={canEdit ? () => openEdit(r.id) : undefined}
                                        onDelete={canDelete ? () => setDeleteUrl(`/providers/${r.id}/destroy`) : undefined}
                                    />
                                ),
                            },
                        ]}
                        perPage={providers.per_page}
                        searchable
                        exportable
                        emptyMessage="Aucun fournisseur"
                    />
                </div>
                <div className="px-5 pb-5">
                    <Pagination meta={providers} />
                </div>
            </Card>

            {/* One drawer at a time, derived from the URL via the shared hook + shared Counterparty drawers. */}
            {drawer.mode === 'create' && (
                <CounterpartyFormDrawer mode="create" basePath="/providers" entityLabel="fournisseur" icon={icon} onClose={() => close()} onSaved={() => close({ replace: true })} />
            )}
            {viewed && (
                <CounterpartyDetailsDrawer entity={viewed} icon={icon} canEdit={canEdit} onEdit={() => openEdit(viewed.id)} onClose={() => close()} />
            )}
            {editing && (
                <CounterpartyFormDrawer mode="edit" basePath="/providers" entityLabel="fournisseur" icon={icon} record={editing} onClose={() => openView(editing.id)} onSaved={() => openView(editing.id, { replace: true })} />
            )}

            <ConfirmDialog open={!!deleteUrl} onClose={() => setDeleteUrl(null)} deleteUrl={deleteUrl ?? undefined} />
        </AuthenticatedLayout>
    );
}
