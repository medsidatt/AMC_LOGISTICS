import { Head } from '@inertiajs/react';
import { useMemo } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import { ArrowLeft, Pencil } from 'lucide-react';

interface Permission {
    id: number;
    name: string;
}

interface Props {
    role: { id: number; name: string; guard_name: string; permissions: Permission[] };
}

export default function RolesShow({ role }: Props) {
    const grouped = useMemo(() => {
        const groups: Record<string, Permission[]> = {};
        role.permissions.forEach((p) => {
            const prefix = p.name.split('-')[0] ?? 'other';
            (groups[prefix] ??= []).push(p);
        });
        return groups;
    }, [role.permissions]);

    return (
        <AuthenticatedLayout title={role.name}>
            <Head title={role.name} />

            <div className="flex items-center justify-between mb-4">
                <Button variant="ghost" icon={<ArrowLeft size={16} />} onClick={() => window.history.back()}>Retour</Button>
                <Button variant="secondary" icon={<Pencil size={16} />} onClick={() => window.location.href = `/roles/${role.id}/edit`}>Modifier</Button>
            </div>

            <Card className="mb-6">
                <div className="grid sm:grid-cols-2 gap-4">
                    <div>
                        <p className="text-xs text-[var(--color-text-muted)] uppercase">Nom</p>
                        <p className="text-sm text-[var(--color-text)] mt-0.5">{role.name}</p>
                    </div>
                    <div>
                        <p className="text-xs text-[var(--color-text-muted)] uppercase">Permissions</p>
                        <p className="text-sm text-[var(--color-text)] mt-0.5">{role.permissions.length}</p>
                    </div>
                </div>
            </Card>

            <Card>
                <h4 className="font-semibold text-[var(--color-text)] mb-4">Permissions</h4>
                <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    {Object.entries(grouped).map(([group, perms]) => (
                        <div key={group} className="rounded-lg border border-[var(--color-border)] p-3">
                            <p className="text-sm font-semibold text-[var(--color-text)] capitalize mb-2">{group}</p>
                            <div className="flex flex-wrap gap-1">
                                {perms.map((p) => <Badge key={p.id} variant="muted">{p.name.split('-').slice(1).join('-')}</Badge>)}
                            </div>
                        </div>
                    ))}
                </div>
            </Card>
        </AuthenticatedLayout>
    );
}
