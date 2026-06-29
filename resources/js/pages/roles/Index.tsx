import { Head } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import PageHeader from '@/components/ui/PageHeader';
import DataTable from '@/components/ui/DataTable';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import ActionButtons from '@/components/ui/ActionButtons';
import ConfirmDialog from '@/components/ui/ConfirmDialog';
import Pagination from '@/components/ui/Pagination';
import RoleFormDrawer from './components/RoleFormDrawer';
import RoleDetailsDrawer from './components/RoleDetailsDrawer';
import { Plus, ShieldCheck } from 'lucide-react';
import { usePermission } from '@/hooks/usePermission';
import { useWorkspaceDrawer } from '@/hooks/useWorkspaceDrawer';
import type { PermissionItem, PermissionMeta, Role, RolePaginator } from './types';

interface Props {
    roles: RolePaginator;
    roleDescriptions: Record<string, string>;
    permissions: PermissionItem[];
    permissionMeta: PermissionMeta;
}

export default function RolesWorkspace({ roles, roleDescriptions, permissions, permissionMeta }: Props) {
    const { can } = usePermission();
    const canCreate = can('role-create');
    const canEdit = can('role-edit');
    const canDelete = can('role-delete');
    const canView = can('role-show');

    // URL-driven drawer state (platform standard): /roles ?create=1 / ?view={id} / ?edit={id}.
    const { drawer, openCreate, openView, openEdit, close } = useWorkspaceDrawer('/roles');
    const [deleteUrl, setDeleteUrl] = useState<string | null>(null);
    const roleById = (id: number | null): Role | null => roles.data.find((r) => r.id === id) ?? null;

    return (
        <AuthenticatedLayout title="Rôles">
            <Head title="Rôles" />

            <PageHeader
                icon={<ShieldCheck size={22} className="text-[var(--color-primary)]" />}
                title="Rôles"
                subtitle="Rôles et permissions de la plateforme"
                actions={canCreate ? <Button icon={<Plus size={16} />} onClick={() => openCreate()}>Ajouter</Button> : undefined}
            />

            <Card padding={false}>
                <div className="p-5">
                    <DataTable
                        data={roles.data}
                        columns={[
                            {
                                key: 'name', label: 'Rôle', render: (r) => (
                                    <div>
                                        <div className="font-medium text-[var(--color-text)]">{r.name}</div>
                                        {roleDescriptions[r.name] && (
                                            <div className="text-xs text-[var(--color-text-muted)]">{roleDescriptions[r.name]}</div>
                                        )}
                                    </div>
                                ),
                            },
                            {
                                key: 'permissions', label: 'Accès', render: (r) => (
                                    <Badge variant="muted">{r.permissions.length} permission{r.permissions.length > 1 ? 's' : ''}</Badge>
                                ),
                            },
                            {
                                key: 'actions', label: 'Actions', sortable: false,
                                render: (r) => (
                                    <ActionButtons
                                        onView={canView ? () => openView(r.id) : undefined}
                                        onEdit={canEdit ? () => openEdit(r.id) : undefined}
                                        onDelete={canDelete ? () => setDeleteUrl(`/roles/destroy/${r.id}`) : undefined}
                                    />
                                ),
                            },
                        ]}
                        perPage={roles.per_page}
                        searchable
                        exportable
                        emptyMessage="Aucun rôle"
                    />
                </div>
                <div className="px-5 pb-5">
                    <Pagination meta={roles} />
                </div>
            </Card>

            {/* One drawer at a time, derived from the URL via the shared hook. */}
            {drawer.mode === 'create' && (
                <RoleFormDrawer
                    mode="create"
                    permissions={permissions}
                    permissionMeta={permissionMeta}
                    onClose={() => close()}
                    onSaved={() => close({ replace: true })}
                />
            )}

            {drawer.mode === 'view' && roleById(drawer.id) && (
                <RoleDetailsDrawer
                    role={roleById(drawer.id)!}
                    permissionMeta={permissionMeta}
                    description={roleDescriptions[roleById(drawer.id)!.name]}
                    canEdit={canEdit}
                    onEdit={() => openEdit(drawer.id!)}
                    onClose={() => close()}
                />
            )}

            {drawer.mode === 'edit' && roleById(drawer.id) && (
                <RoleFormDrawer
                    mode="edit"
                    role={roleById(drawer.id)}
                    permissions={permissions}
                    permissionMeta={permissionMeta}
                    onClose={() => openView(drawer.id!)}
                    onSaved={() => openView(drawer.id!, { replace: true })}
                />
            )}

            <ConfirmDialog open={!!deleteUrl} onClose={() => setDeleteUrl(null)} deleteUrl={deleteUrl ?? undefined} />
        </AuthenticatedLayout>
    );
}
