import { useForm } from '@inertiajs/react';
import Drawer from '@/components/ui/Drawer';
import FormActions from '@/components/ui/FormActions';
import FormInput from '@/components/ui/FormInput';
import SectionTitle from '@/components/ui/SectionTitle';
import PermissionMatrix from '@/components/permissions/PermissionMatrix';
import { ShieldCheck } from 'lucide-react';
import type { PermissionItem, PermissionMeta, Role } from '../types';

interface Props {
    mode: 'create' | 'edit';
    role?: Role | null;
    permissions: PermissionItem[];
    permissionMeta: PermissionMeta;
    onClose: () => void;
    onSaved: () => void;
}

/**
 * Create / edit a role inside the workspace — replaces the legacy Create and Edit
 * pages. The PermissionMatrix (business core: grouping, toggling, sync) is reused
 * UNCHANGED; only the container moved to a Drawer. Posts to the existing
 * store/update endpoints (validation + syncPermissions unchanged).
 */
export default function RoleFormDrawer({ mode, role, permissions, permissionMeta, onClose, onSaved }: Props) {
    const form = useForm<{ name: string; permissions: number[] }>({
        name: mode === 'edit' && role ? role.name : '',
        permissions: mode === 'edit' && role ? role.permissions.map((p) => p.id) : [],
    });

    const submit = () => {
        const opts = { preserveScroll: true, onSuccess: onSaved };
        if (mode === 'create') {
            form.post('/roles/store', opts);
        } else if (role) {
            form.put(`/roles/update/${role.id}`, opts);
        }
    };

    return (
        <Drawer
            open
            onClose={onClose}
            size="lg"
            icon={<ShieldCheck size={18} className="text-[var(--color-primary)]" />}
            title={mode === 'create' ? 'Nouveau rôle' : `Modifier — ${role?.name ?? ''}`}
            footer={<FormActions onCancel={onClose} onSubmit={submit} submitLabel={mode === 'create' ? 'Créer le rôle' : 'Enregistrer'} loading={form.processing} disabled={!form.data.name.trim()} />}
        >
            <SectionTitle>Identité</SectionTitle>
            <FormInput
                label="Nom du rôle"
                name="name"
                wrapperClass="mb-0"
                value={form.data.name}
                onChange={(e) => form.setData('name', e.target.value)}
                error={form.errors.name}
                required
                autoFocus
            />

            <SectionTitle>Permissions</SectionTitle>
            {form.errors.permissions && <p className="mb-2 text-xs text-[var(--color-danger)]">{form.errors.permissions}</p>}
            <PermissionMatrix
                allPermissions={permissions}
                selected={form.data.permissions}
                onChange={(ids) => form.setData('permissions', ids)}
                meta={permissionMeta}
            />
        </Drawer>
    );
}
