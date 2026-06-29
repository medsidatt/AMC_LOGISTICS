import { useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import Drawer from '@/components/ui/Drawer';
import FormActions from '@/components/ui/FormActions';
import FormInput from '@/components/ui/FormInput';
import SectionTitle from '@/components/ui/SectionTitle';
import PermissionMatrix from '@/components/permissions/PermissionMatrix';
import { Users as UsersIcon } from 'lucide-react';
import type { Permission, PermissionMeta, Role, User } from '../types';

interface Props {
    mode: 'create' | 'edit';
    user?: User | null;
    roles: Role[];
    allPermissions: Permission[];
    permissionMeta: PermissionMeta;
    onClose: () => void;
    onSaved: () => void;
}

/**
 * Create / edit a user inside the workspace — replaces the legacy create/edit
 * Modals. Business logic is unchanged: create still posts to /users/store (which
 * emails credentials), edit posts to /users/update/{id} (syncRoles +
 * syncPermissions). The PermissionMatrix overlay (role-inherited locked +
 * direct extras) is reused UNCHANGED — only the container moved to a Drawer.
 */
export default function UserFormDrawer({ mode, user, roles, allPermissions, permissionMeta, onClose, onSaved }: Props) {
    const directIds = useMemo(
        () => (user ? allPermissions.filter((p) => user.direct_permissions.includes(p.name)).map((p) => p.id) : []),
        [user, allPermissions],
    );
    // Ids inherited from the user's saved roles — shown locked in the matrix.
    const lockedIds = useMemo(() => {
        const names = new Set(user?.role_permissions ?? []);
        return allPermissions.filter((p) => names.has(p.name)).map((p) => p.id);
    }, [user, allPermissions]);

    const form = useForm<{ name: string; email: string; roles: number[]; permissions: number[] }>({
        name: user?.name ?? '',
        email: user?.email ?? '',
        roles: user?.roles.map((r) => r.id) ?? [],
        permissions: directIds,
    });

    const toggleRole = (roleId: number) => {
        const current = form.data.roles;
        form.setData('roles', current.includes(roleId) ? current.filter((r) => r !== roleId) : [...current, roleId]);
    };

    const submit = () => {
        const opts = { preserveScroll: true, onSuccess: onSaved };
        if (mode === 'create') form.post('/users/store', opts);
        else if (user) form.put(`/users/update/${user.id}`, opts);
    };

    return (
        <Drawer
            open
            onClose={onClose}
            size="lg"
            icon={<UsersIcon size={18} className="text-[var(--color-primary)]" />}
            title={mode === 'create' ? 'Nouvel utilisateur' : `Modifier — ${user?.name ?? ''}`}
            footer={<FormActions onCancel={onClose} onSubmit={submit} submitLabel={mode === 'create' ? "Créer l'utilisateur" : 'Enregistrer'} loading={form.processing} disabled={!form.data.name.trim() || !form.data.email.trim()} />}
        >
            <SectionTitle>Identité</SectionTitle>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <FormInput label="Nom" name="name" wrapperClass="mb-0" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} error={form.errors.name} required autoFocus />
                <FormInput label="Email" type="email" name="email" wrapperClass="mb-0" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} error={form.errors.email} required />
            </div>
            {mode === 'create' && (
                <p className="text-xs text-[var(--color-text-muted)]">Un mot de passe est généré et envoyé par email à l'utilisateur.</p>
            )}

            <SectionTitle>Rôles</SectionTitle>
            <div className="flex flex-wrap gap-2">
                {roles.map((role) => (
                    <label key={role.id} className="flex items-center gap-2 rounded-lg border border-[var(--color-border)] px-3 py-2 text-sm cursor-pointer hover:bg-[var(--color-surface-hover)] transition">
                        <input type="checkbox" checked={form.data.roles.includes(role.id)} onChange={() => toggleRole(role.id)} className="rounded" />
                        <span className="text-[var(--color-text)]">{role.name}</span>
                    </label>
                ))}
            </div>
            {form.errors.roles && <p className="mt-1 text-xs text-[var(--color-danger)]">{form.errors.roles}</p>}

            {mode === 'edit' && (
                <>
                    <SectionTitle>Accès supplémentaires</SectionTitle>
                    <p className="text-xs text-[var(--color-text-muted)] -mt-1">
                        Les accès hérités du rôle sont verrouillés (marqués « rôle »). Cochez-en d'autres pour les accorder à cet utilisateur uniquement.
                    </p>
                    <PermissionMatrix
                        allPermissions={allPermissions}
                        selected={form.data.permissions}
                        onChange={(ids) => form.setData('permissions', ids)}
                        meta={permissionMeta}
                        lockedIds={lockedIds}
                    />
                    {form.errors.permissions && <p className="mt-1 text-xs text-[var(--color-danger)]">{form.errors.permissions}</p>}
                </>
            )}
        </Drawer>
    );
}
