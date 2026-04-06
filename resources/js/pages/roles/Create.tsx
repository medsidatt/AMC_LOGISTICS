import { Head, useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import FormInput from '@/components/ui/FormInput';
import { ArrowLeft } from 'lucide-react';

interface Permission {
    id: number;
    name: string;
}

interface Props {
    permissions: Permission[];
}

export default function RolesCreate({ permissions }: Props) {
    const form = useForm({ name: '', permissions: [] as number[] });

    const grouped = useMemo(() => {
        const groups: Record<string, Permission[]> = {};
        permissions.forEach((p) => {
            const prefix = p.name.split('-')[0] ?? 'other';
            (groups[prefix] ??= []).push(p);
        });
        return groups;
    }, [permissions]);

    const togglePermission = (id: number) => {
        const current = form.data.permissions;
        form.setData('permissions', current.includes(id) ? current.filter((p) => p !== id) : [...current, id]);
    };

    const toggleGroup = (perms: Permission[]) => {
        const ids = perms.map((p) => p.id);
        const allSelected = ids.every((id) => form.data.permissions.includes(id));
        if (allSelected) {
            form.setData('permissions', form.data.permissions.filter((id) => !ids.includes(id)));
        } else {
            form.setData('permissions', [...new Set([...form.data.permissions, ...ids])]);
        }
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/roles/store');
    };

    return (
        <AuthenticatedLayout title="Nouveau rôle">
            <Head title="Nouveau rôle" />

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
                    <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        {Object.entries(grouped).map(([group, perms]) => (
                            <div key={group} className="rounded-lg border border-[var(--color-border)] p-3">
                                <label className="flex items-center gap-2 mb-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={perms.every((p) => form.data.permissions.includes(p.id))}
                                        onChange={() => toggleGroup(perms)}
                                        className="rounded"
                                    />
                                    <span className="text-sm font-semibold text-[var(--color-text)] capitalize">{group}</span>
                                </label>
                                <div className="space-y-1 ml-6">
                                    {perms.map((p) => (
                                        <label key={p.id} className="flex items-center gap-2 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={form.data.permissions.includes(p.id)}
                                                onChange={() => togglePermission(p.id)}
                                                className="rounded"
                                            />
                                            <span className="text-xs text-[var(--color-text-secondary)]">{p.name}</span>
                                        </label>
                                    ))}
                                </div>
                            </div>
                        ))}
                    </div>
                </Card>

                <div className="flex gap-2 mt-6">
                    <Button variant="secondary" onClick={() => window.history.back()}>Annuler</Button>
                    <Button type="submit" loading={form.processing}>Créer</Button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
