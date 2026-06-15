import { Head, useForm, router, usePage } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import DataTable from '@/components/ui/DataTable';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import Modal from '@/components/ui/Modal';
import FormInput from '@/components/ui/FormInput';
import ActionButtons from '@/components/ui/ActionButtons';
import ConfirmDialog from '@/components/ui/ConfirmDialog';
import Pagination from '@/components/ui/Pagination';
import { Plus, Ban, CheckCircle2 } from 'lucide-react';
import { usePermission } from '@/hooks/usePermission';

interface Role {
    id: number;
    name: string;
}

interface Permission {
    id: number;
    name: string;
}

interface User {
    id: number;
    name: string;
    email: string;
    is_suspended: boolean;
    roles: Role[];
    direct_permissions: string[];
    role_permissions: string[];
    created_at: string | null;
}

interface Props {
    users: { data: User[]; current_page: number; last_page: number; per_page: number; total: number; from: number | null; to: number | null };
    roles: Role[];
    allPermissions: Permission[];
}

// Group permissions by module: everything before the last dash.
// e.g. "transport-tracking-list" -> "transport-tracking", "role-show" -> "role".
const moduleOf = (name: string) => (name.includes('-') ? name.slice(0, name.lastIndexOf('-')) : name);

export default function UsersIndex({ users, roles, allPermissions }: Props) {
    const page = usePage().props as unknown as { auth: { user: { id: number } | null; roles: string[] } };
    const currentUserId = page.auth?.user?.id ?? null;
    const isSuperAdmin = (page.auth?.roles ?? []).includes('Super Admin');
    const { can } = usePermission();
    const canCreate = can('user-create');
    const canEdit = can('user-edit');
    const canDelete = can('user-delete');
    const canSuspend = can('user-suspend');

    // A row can be managed (edit/suspend/delete) unless it's the current user's
    // own account, or a Super Admin account being viewed by a non-Super-Admin.
    const canManageUser = (u: User) =>
        u.id !== currentUserId &&
        (isSuperAdmin || !u.roles.some((r) => r.name === 'Super Admin'));

    const [modal, setModal] = useState<'create' | 'edit' | 'show' | null>(null);
    const [selected, setSelected] = useState<User | null>(null);
    const [deleteUrl, setDeleteUrl] = useState<string | null>(null);

    const createForm = useForm({ name: '', email: '', roles: [] as number[] });
    const editForm = useForm({ name: '', email: '', roles: [] as number[], permissions: [] as number[] });

    // Permissions grouped by module, computed once.
    const permissionGroups = useMemo(() => {
        const groups: Record<string, Permission[]> = {};
        for (const p of allPermissions) {
            (groups[moduleOf(p.name)] ??= []).push(p);
        }
        return Object.entries(groups).sort(([a], [b]) => a.localeCompare(b));
    }, [allPermissions]);

    // Names inherited from the selected user's saved roles — locked in the editor.
    const inheritedPermissions = useMemo(
        () => new Set(selected?.role_permissions ?? []),
        [selected],
    );

    const openEdit = (u: User) => {
        setSelected(u);
        const directIds = allPermissions.filter((p) => u.direct_permissions.includes(p.name)).map((p) => p.id);
        editForm.setData({ name: u.name, email: u.email, roles: u.roles.map((r) => r.id), permissions: directIds });
        setModal('edit');
    };

    const togglePermission = (id: number) => {
        const current = editForm.data.permissions;
        editForm.setData('permissions', current.includes(id) ? current.filter((p) => p !== id) : [...current, id]);
    };

    const openShow = (u: User) => { setSelected(u); setModal('show'); };

    const submitCreate = (e: React.FormEvent) => {
        e.preventDefault();
        createForm.post('/users/store', { onSuccess: () => { setModal(null); createForm.reset(); } });
    };

    const submitEdit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selected) return;
        editForm.put(`/users/update/${selected.id}`, { onSuccess: () => setModal(null) });
    };

    const toggleRole = (form: typeof createForm, roleId: number) => {
        const current = form.data.roles;
        form.setData('roles', current.includes(roleId) ? current.filter((r) => r !== roleId) : [...current, roleId]);
    };

    const RoleCheckboxes = ({ form }: { form: typeof createForm }) => (
        <div className="mb-4">
            <label className="block text-sm font-medium text-[var(--color-text)] mb-2">Rôles</label>
            <div className="flex flex-wrap gap-2">
                {roles.map((role) => (
                    <label key={role.id} className="flex items-center gap-2 rounded-lg border border-[var(--color-border)] px-3 py-2 text-sm cursor-pointer hover:bg-[var(--color-surface-hover)] transition">
                        <input type="checkbox" checked={form.data.roles.includes(role.id)} onChange={() => toggleRole(form, role.id)} className="rounded" />
                        <span className="text-[var(--color-text)]">{role.name}</span>
                    </label>
                ))}
            </div>
            {form.errors.roles && <p className="mt-1 text-xs text-[var(--color-danger)]">{form.errors.roles}</p>}
        </div>
    );

    return (
        <AuthenticatedLayout title="Utilisateurs">
            <Head title="Utilisateurs" />

            {canCreate && (
                <div className="flex justify-end mb-4">
                    <Button icon={<Plus size={16} />} onClick={() => { createForm.reset(); setModal('create'); }}>Ajouter</Button>
                </div>
            )}

            <Card padding={false}>
                <div className="p-5">
                    <DataTable
                        data={users.data}
                        columns={[
                            { key: 'name', label: 'Nom' },
                            { key: 'email', label: 'Email', hideOnMobile: true },
                            { key: 'roles', label: 'Rôles', render: (r) => (
                                <div className="flex flex-wrap gap-1">
                                    {r.roles.map((role) => <Badge key={role.id} variant="primary">{role.name}</Badge>)}
                                </div>
                            )},
                            { key: 'is_suspended', label: 'Statut', render: (r) => (
                                <Badge variant={r.is_suspended ? 'danger' : 'success'}>{r.is_suspended ? 'Suspendu' : 'Actif'}</Badge>
                            )},
                            {
                                key: 'actions', label: 'Actions', sortable: false,
                                render: (r) => {
                                    const manageable = canManageUser(r);
                                    return (
                                    <div className="flex items-center gap-1">
                                        <ActionButtons
                                            onView={() => openShow(r)}
                                            onEdit={manageable && canEdit ? () => openEdit(r) : undefined}
                                            onDelete={manageable && canDelete ? () => setDeleteUrl(`/users/destroy/${r.id}`) : undefined}
                                        />
                                        {manageable && canSuspend && (
                                            <button
                                                onClick={() => {
                                                    if (confirm(r.is_suspended ? `Réactiver ${r.name} ?` : `Suspendre ${r.name} ?`)) {
                                                        router.put(`/users/suspend/${r.id}`, {}, { preserveScroll: true });
                                                    }
                                                }}
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

            <Modal open={modal === 'create'} onClose={() => setModal(null)} title="Nouvel utilisateur">
                <form onSubmit={submitCreate}>
                    <FormInput label="Nom" name="name" value={createForm.data.name} onChange={(e) => createForm.setData('name', e.target.value)} error={createForm.errors.name} required autoFocus />
                    <FormInput label="Email" type="email" name="email" value={createForm.data.email} onChange={(e) => createForm.setData('email', e.target.value)} error={createForm.errors.email} required />
                    <RoleCheckboxes form={createForm} />
                    <div className="flex justify-end gap-2 mt-6">
                        <Button variant="secondary" onClick={() => setModal(null)}>Annuler</Button>
                        <Button type="submit" loading={createForm.processing}>Créer</Button>
                    </div>
                </form>
            </Modal>

            <Modal open={modal === 'edit'} onClose={() => setModal(null)} title="Modifier utilisateur">
                <form onSubmit={submitEdit}>
                    <FormInput label="Nom" name="name" value={editForm.data.name} onChange={(e) => editForm.setData('name', e.target.value)} error={editForm.errors.name} required autoFocus />
                    <FormInput label="Email" type="email" name="email" value={editForm.data.email} onChange={(e) => editForm.setData('email', e.target.value)} error={editForm.errors.email} required />
                    <RoleCheckboxes form={editForm} />

                    <div className="mb-2">
                        <label className="block text-sm font-medium text-[var(--color-text)] mb-1">Permissions supplémentaires</label>
                        <p className="text-xs text-[var(--color-text-muted)] mb-2">
                            Les permissions héritées du rôle sont verrouillées. Cochez-en d'autres pour les accorder à cet utilisateur uniquement.
                        </p>
                        <div className="max-h-64 overflow-y-auto rounded-lg border border-[var(--color-border)] p-3 space-y-3">
                            {permissionGroups.map(([module, perms]) => (
                                <div key={module}>
                                    <p className="text-xs font-semibold uppercase tracking-wider text-[var(--color-text-muted)] mb-1">{module}</p>
                                    <div className="flex flex-wrap gap-2">
                                        {perms.map((p) => {
                                            const inherited = inheritedPermissions.has(p.name);
                                            const checked = inherited || editForm.data.permissions.includes(p.id);
                                            return (
                                                <label
                                                    key={p.id}
                                                    className={
                                                        'flex items-center gap-2 rounded-lg border border-[var(--color-border)] px-2.5 py-1.5 text-xs transition ' +
                                                        (inherited ? 'opacity-60 cursor-not-allowed bg-[var(--color-surface-hover)]' : 'cursor-pointer hover:bg-[var(--color-surface-hover)]')
                                                    }
                                                    title={inherited ? 'Hérité du rôle' : undefined}
                                                >
                                                    <input
                                                        type="checkbox"
                                                        checked={checked}
                                                        disabled={inherited}
                                                        onChange={() => togglePermission(p.id)}
                                                        className="rounded"
                                                    />
                                                    <span className="text-[var(--color-text)]">{p.name}</span>
                                                </label>
                                            );
                                        })}
                                    </div>
                                </div>
                            ))}
                        </div>
                        {editForm.errors.permissions && <p className="mt-1 text-xs text-[var(--color-danger)]">{editForm.errors.permissions}</p>}
                    </div>

                    <div className="flex justify-end gap-2 mt-6">
                        <Button variant="secondary" onClick={() => setModal(null)}>Annuler</Button>
                        <Button type="submit" loading={editForm.processing}>Enregistrer</Button>
                    </div>
                </form>
            </Modal>

            <Modal open={modal === 'show'} onClose={() => setModal(null)} title={selected?.name ?? 'Utilisateur'}>
                {selected && (
                    <div className="space-y-3">
                        <div>
                            <p className="text-xs text-[var(--color-text-muted)] uppercase">Nom</p>
                            <p className="text-sm text-[var(--color-text)]">{selected.name}</p>
                        </div>
                        <div>
                            <p className="text-xs text-[var(--color-text-muted)] uppercase">Email</p>
                            <p className="text-sm text-[var(--color-text)]">{selected.email}</p>
                        </div>
                        <div>
                            <p className="text-xs text-[var(--color-text-muted)] uppercase">Rôles</p>
                            <div className="flex flex-wrap gap-1 mt-1">
                                {selected.roles.map((r) => <Badge key={r.id} variant="primary">{r.name}</Badge>)}
                            </div>
                        </div>
                        <div>
                            <p className="text-xs text-[var(--color-text-muted)] uppercase">Statut</p>
                            <Badge variant={selected.is_suspended ? 'danger' : 'success'}>{selected.is_suspended ? 'Suspendu' : 'Actif'}</Badge>
                        </div>
                        <div>
                            <p className="text-xs text-[var(--color-text-muted)] uppercase">Créé le</p>
                            <p className="text-sm text-[var(--color-text)]">{selected.created_at ?? '-'}</p>
                        </div>
                    </div>
                )}
            </Modal>

            <ConfirmDialog open={!!deleteUrl} onClose={() => setDeleteUrl(null)} deleteUrl={deleteUrl ?? undefined} />
        </AuthenticatedLayout>
    );
}
