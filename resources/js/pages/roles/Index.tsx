import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';
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
import type { PermissionItem, PermissionMeta, Role, RolePaginator } from './types';

interface Props {
    roles: RolePaginator;
    roleDescriptions: Record<string, string>;
    permissions: PermissionItem[];
    permissionMeta: PermissionMeta;
}

type DrawerState =
    | { kind: 'none' }
    | { kind: 'create' }
    | { kind: 'view'; id: number }
    | { kind: 'edit'; id: number };

function parseDrawer(search: string): DrawerState {
    const p = new URLSearchParams(search);
    if (p.get('create') === '1') return { kind: 'create' };
    const view = p.get('view');
    if (view) return { kind: 'view', id: Number(view) };
    const edit = p.get('edit');
    if (edit) return { kind: 'edit', id: Number(edit) };
    return { kind: 'none' };
}

function drawerToUrl(d: DrawerState): string {
    if (d.kind === 'create') return '/roles?create=1';
    if (d.kind === 'view') return `/roles?view=${d.id}`;
    if (d.kind === 'edit') return `/roles?edit=${d.id}`;
    return '/roles';
}

export default function RolesWorkspace({ roles, roleDescriptions, permissions, permissionMeta }: Props) {
    const { can } = usePermission();
    const canCreate = can('role-create');
    const canEdit = can('role-edit');
    const canDelete = can('role-delete');
    const canView = can('role-show');

    // URL-driven drawer state: /roles ?create=1 / ?view={id} / ?edit={id}.
    // History API only (no server request) — everything runs from the index payload.
    const [search, setSearch] = useState<string>(() => (typeof window !== 'undefined' ? window.location.search : ''));
    const drawer = parseDrawer(search);

    useEffect(() => {
        const onPop = () => setSearch(window.location.search);
        window.addEventListener('popstate', onPop);
        return () => window.removeEventListener('popstate', onPop);
    }, []);

    const navigate = (d: DrawerState, replace = false) => {
        const url = drawerToUrl(d);
        if (replace) window.history.replaceState(window.history.state, '', url);
        else window.history.pushState(window.history.state, '', url);
        setSearch(window.location.search);
    };

    const [deleteUrl, setDeleteUrl] = useState<string | null>(null);
    const roleById = (id: number): Role | null => roles.data.find((r) => r.id === id) ?? null;

    return (
        <AuthenticatedLayout title="Rôles">
            <Head title="Rôles" />

            <PageHeader
                icon={<ShieldCheck size={22} className="text-[var(--color-primary)]" />}
                title="Rôles"
                subtitle="Rôles et permissions de la plateforme"
                actions={canCreate ? <Button icon={<Plus size={16} />} onClick={() => navigate({ kind: 'create' })}>Ajouter</Button> : undefined}
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
                                        onView={canView ? () => navigate({ kind: 'view', id: r.id }) : undefined}
                                        onEdit={canEdit ? () => navigate({ kind: 'edit', id: r.id }) : undefined}
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

            {/* One drawer at a time, derived from the URL. */}
            {drawer.kind === 'create' && (
                <RoleFormDrawer
                    mode="create"
                    permissions={permissions}
                    permissionMeta={permissionMeta}
                    onClose={() => navigate({ kind: 'none' })}
                    onSaved={() => navigate({ kind: 'none' }, true)}
                />
            )}

            {drawer.kind === 'view' && roleById(drawer.id) && (
                <RoleDetailsDrawer
                    role={roleById(drawer.id)!}
                    permissionMeta={permissionMeta}
                    description={roleDescriptions[roleById(drawer.id)!.name]}
                    canEdit={canEdit}
                    onEdit={() => navigate({ kind: 'edit', id: drawer.id })}
                    onClose={() => navigate({ kind: 'none' })}
                />
            )}

            {drawer.kind === 'edit' && roleById(drawer.id) && (
                <RoleFormDrawer
                    mode="edit"
                    role={roleById(drawer.id)}
                    permissions={permissions}
                    permissionMeta={permissionMeta}
                    onClose={() => navigate({ kind: 'view', id: drawer.id })}
                    onSaved={() => navigate({ kind: 'view', id: drawer.id }, true)}
                />
            )}

            <ConfirmDialog open={!!deleteUrl} onClose={() => setDeleteUrl(null)} deleteUrl={deleteUrl ?? undefined} />
        </AuthenticatedLayout>
    );
}
