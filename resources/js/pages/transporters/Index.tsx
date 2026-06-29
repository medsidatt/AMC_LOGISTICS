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
import { Plus, Truck } from 'lucide-react';
import { usePermission } from '@/hooks/usePermission';
import { useWorkspaceDrawer } from '@/hooks/useWorkspaceDrawer';
import type { Transporter, TransporterPaginator } from './types';

interface Props {
    transporters: TransporterPaginator;
    filters: { search?: string };
}

const icon = <Truck size={18} className="text-[var(--color-primary)]" />;

export default function TransportersWorkspace({ transporters }: Props) {
    const { can } = usePermission();
    const canCreate = can('transporter-create');
    const canEdit = can('transporter-edit');
    const canDelete = can('transporter-delete');

    const { drawer, openCreate, openView, openEdit, close } = useWorkspaceDrawer('/transporters');
    const [deleteUrl, setDeleteUrl] = useState<string | null>(null);

    const byId = (id: number | null): Transporter | null => transporters.data.find((t) => t.id === id) ?? null;
    const viewed = drawer.mode === 'view' ? byId(drawer.id) : null;
    const editing = drawer.mode === 'edit' ? byId(drawer.id) : null;

    return (
        <AuthenticatedLayout title="Transporteurs">
            <Head title="Transporteurs" />

            <PageHeader
                icon={<Truck size={22} className="text-[var(--color-primary)]" />}
                title="Transporteurs"
                subtitle="Sociétés de transport"
                actions={canCreate ? <Button icon={<Plus size={16} />} onClick={() => openCreate()}>Ajouter</Button> : undefined}
            />

            <Card padding={false}>
                <div className="p-5">
                    <DataTable
                        data={transporters.data}
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
                                        onDelete={canDelete ? () => setDeleteUrl(`/transporters/${r.id}/destroy`) : undefined}
                                    />
                                ),
                            },
                        ]}
                        perPage={transporters.per_page}
                        searchable
                        exportable
                        emptyMessage="Aucun transporteur"
                    />
                </div>
                <div className="px-5 pb-5">
                    <Pagination meta={transporters} />
                </div>
            </Card>

            {/* One drawer at a time, derived from the URL via the shared hook + shared Counterparty drawers. */}
            {drawer.mode === 'create' && (
                <CounterpartyFormDrawer mode="create" basePath="/transporters" entityLabel="transporteur" icon={icon} onClose={() => close()} onSaved={() => close({ replace: true })} />
            )}
            {viewed && (
                <CounterpartyDetailsDrawer entity={viewed} icon={icon} canEdit={canEdit} onEdit={() => openEdit(viewed.id)} onClose={() => close()} />
            )}
            {editing && (
                <CounterpartyFormDrawer mode="edit" basePath="/transporters" entityLabel="transporteur" icon={icon} record={editing} onClose={() => openView(editing.id)} onSaved={() => openView(editing.id, { replace: true })} />
            )}

            <ConfirmDialog open={!!deleteUrl} onClose={() => setDeleteUrl(null)} deleteUrl={deleteUrl ?? undefined} />
        </AuthenticatedLayout>
    );
}
