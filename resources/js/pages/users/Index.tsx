import { Head, router, usePage } from '@inertiajs/react';
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
import UserFormDrawer from './components/UserFormDrawer';
import UserDetailsDrawer from './components/UserDetailsDrawer';
import { Plus, Ban, CheckCircle2, Users as UsersIcon } from 'lucide-react';
import { usePermission } from '@/hooks/usePermission';
import { useWorkspaceDrawer } from '@/hooks/useWorkspaceDrawer';
import type { Permission, PermissionMeta, Role, User, UserPaginator } from './types';

interface Props {
    users: UserPaginator;
    roles: Role[];
    allPermissions: Permission[];
    permissionMeta: PermissionMeta;
}

export default function UsersWorkspace({ users, roles, allPermissions, permissionMeta }: Props) {
    const page = usePage().props as unknown as { auth: { user: { id: number } | null; roles: string[] } };
    const currentUserId = page.auth?.user?.id ?? null;
    const isSuperAdmin = (page.auth?.roles ?? []).includes('Super Admin');
    const { can } = usePermission();
    const canCreate = can('user-create');
    const canEdit = can('user-edit');
    const canDelete = can('user-delete');
    const canSuspend = can('user-suspend');

    // A row can be managed (edit/suspend/delete) unless it's the current user's
    // own account, or a Super Admin account viewed by a non-Super-Admin. Unchanged.
    const canManageUser = (u: User) =>
        u.id !== currentUserId &&
        (isSuperAdmin || !u.roles.some((r) => r.name === 'Super Admin'));

    const { drawer, openCreate, openView, openEdit, close } = useWorkspaceDrawer('/users');
    const [deleteUrl, setDeleteUrl] = useState<string | null>(null);
    const [suspendTarget, setSuspendTarget] = useState<User | null>(null);

    const userById = (id: number | null): User | null => users.data.find((u) => u.id === id) ?? null;
    const viewed = drawer.mode === 'view' ? userById(drawer.id) : null;
    const editing = drawer.mode === 'edit' ? userById(drawer.id) : null;

    return (
        <AuthenticatedLayout title="Utilisateurs">
            <Head title="Utilisateurs" />

            <PageHeader
                icon={<UsersIcon size={22} className="text-[var(--color-primary)]" />}
                title="Utilisateurs"
                subtitle="Comptes, rôles et accès de la plateforme"
                actions={canCreate ? <Button icon={<Plus size={16} />} onClick={() => openCreate()}>Ajouter</Button> : undefined}
            />

            <Card padding={false}>
                <div className="p-5">
                    <DataTable
                        data={users.data}
                        columns={[
                            { key: 'name', label: 'Nom' },
                            { key: 'email', label: 'Email', hideOnMobile: true },
                            {
                                key: 'roles', label: 'Rôles', render: (r) => (
                                    <div className="flex flex-wrap gap-1">
                                        {r.roles.map((role) => <Badge key={role.id} variant="primary">{role.name}</Badge>)}
                                    </div>
                                ),
                            },
                            {
                                key: 'is_suspended', label: 'Statut', render: (r) => (
                                    <Badge variant={r.is_suspended ? 'danger' : 'success'}>{r.is_suspended ? 'Suspendu' : 'Actif'}</Badge>
                                ),
                            },
                            {
                                key: 'actions', label: 'Actions', sortable: false,
                                render: (r) => {
                                    const manageable = canManageUser(r);
                                    return (
                                        <div className="flex items-center gap-1">
                                            <ActionButtons
                                                onView={() => openView(r.id)}
                                                onEdit={manageable && canEdit ? () => openEdit(r.id) : undefined}
                                                onDelete={manageable && canDelete ? () => setDeleteUrl(`/users/destroy/${r.id}`) : undefined}
                                            />
                                            {manageable && canSuspend && (
                                                <button
                                                    onClick={() => setSuspendTarget(r)}
                                                    className="p-1.5 rounded-lg transition-colors text-[var(--color-warning)] hover:bg-[var(--color-warning)]/10"
                                                    title={r.is_suspended ? 'Activer' : 'Suspendre'}
                                                >
                                                    {r.is_suspended ? <CheckCircle2 size={14} /> : <Ban size={14} />}
                                                </button>
                                            )}
                                        </div>
                                    );
                                },
                            },
                        ]}
                        perPage={users.per_page}
                        searchable
                        exportable
                    />
                </div>
                <div className="px-5 pb-5">
                    <Pagination meta={users} />
                </div>
            </Card>

            {/* One drawer at a time, derived from the URL via the shared hook. */}
            {drawer.mode === 'create' && (
                <UserFormDrawer
                    mode="create"
                    roles={roles}
                    allPermissions={allPermissions}
                    permissionMeta={permissionMeta}
                    onClose={() => close()}
                    onSaved={() => close({ replace: true })}
                />
            )}

            {viewed && (
                <UserDetailsDrawer
                    user={viewed}
                    canEdit={canManageUser(viewed) && canEdit}
                    onEdit={() => openEdit(viewed.id)}
                    onClose={() => close()}
                />
            )}

            {editing && (
                <UserFormDrawer
                    mode="edit"
                    user={editing}
                    roles={roles}
                    allPermissions={allPermissions}
                    permissionMeta={permissionMeta}
                    onClose={() => openView(editing.id)}
                    onSaved={() => openView(editing.id, { replace: true })}
                />
            )}

            <ConfirmDialog open={!!deleteUrl} onClose={() => setDeleteUrl(null)} deleteUrl={deleteUrl ?? undefined} />

            <ConfirmDialog
                open={!!suspendTarget}
                onClose={() => setSuspendTarget(null)}
                title={suspendTarget?.is_suspended ? "Réactiver l'utilisateur" : "Suspendre l'utilisateur"}
                message={suspendTarget ? `${suspendTarget.is_suspended ? 'Réactiver' : 'Suspendre'} ${suspendTarget.name} ?` : ''}
                confirmLabel={suspendTarget?.is_suspended ? 'Réactiver' : 'Suspendre'}
                onConfirm={() => { if (suspendTarget) router.put(`/users/suspend/${suspendTarget.id}`, {}, { preserveScroll: true }); }}
            />
        </AuthenticatedLayout>
    );
}
