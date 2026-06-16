import { Head, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import FormInput from '@/components/ui/FormInput';
import PermissionMatrix, { PermissionItem, PermissionMeta } from '@/components/permissions/PermissionMatrix';
import { ArrowLeft } from 'lucide-react';

interface Props {
    role: { id: number; name: string; guard_name: string; permissions: PermissionItem[] };
    allPermissions: PermissionItem[];
    permissionMeta: PermissionMeta;
}

export default function RolesEdit({ role, allPermissions, permissionMeta }: Props) {
    const form = useForm({
        name: role.name,
        permissions: role.permissions.map((p) => p.id),
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.put(`/roles/update/${role.id}`);
    };

    return (
        <AuthenticatedLayout title={`Modifier ${role.name}`}>
            <Head title={`Modifier ${role.name}`} />

            <div className="mb-4">
                <Button variant="ghost" icon={<ArrowLeft size={16} />} onClick={() => window.history.back()}>Retour</Button>
            </div>

            <form onSubmit={submit}>
                <Card className="mb-6">
                    <FormInput label="Nom du rôle" name="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} error={form.errors.name} required autoFocus />
                </Card>

                <Card>
                    <h4 className="font-semibold text-[var(--color-text)] mb-4">Permissions</h4>
                    {form.errors.permissions && <p className="mb-3 text-xs text-[var(--color-danger)]">{form.errors.permissions}</p>}
                    <PermissionMatrix
                        allPermissions={allPermissions}
                        selected={form.data.permissions}
                        onChange={(ids) => form.setData('permissions', ids)}
                        meta={permissionMeta}
                    />
                </Card>

                <div className="flex gap-2 mt-6">
                    <Button variant="secondary" onClick={() => window.history.back()}>Annuler</Button>
                    <Button type="submit" loading={form.processing}>Enregistrer</Button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
