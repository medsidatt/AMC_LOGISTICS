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

interface PermissionMeta {
    groups: Record<string, string[]>;
    labels: Record<string, string>;
}

interface Props {
    role: { id: number; name: string; guard_name: string; permissions: Permission[] };
    permissionMeta: PermissionMeta;
}

export default function RolesShow({ role, permissionMeta }: Props) {
    const grouped = useMemo(() => {
        const have = new Set(role.permissions.map((p) => p.name));
        const out: { label: string; names: string[] }[] = [];
        const seen = new Set<string>();
        for (const [label, codes] of Object.entries(permissionMeta.groups)) {
            const names = codes.filter((c) => have.has(c));
            names.forEach((n) => seen.add(n));
            if (names.length) out.push({ label, names });
        }
        const rest = role.permissions.filter((p) => !seen.has(p.name)).map((p) => p.name);
        if (rest.length) out.push({ label: 'Autres', names: rest });
        return out;
    }, [role.permissions, permissionMeta.groups]);

    return (
        <AuthenticatedLayout title={role.name}>
            <Head title={role.name} />

            <div className="flex items-center justify-between mb-4">
                <Button variant="ghost" icon={<ArrowLeft size={16} />} onClick={() => window.history.back()}>Retour</Button>
                <Button variant="secondary" icon={<Pencil size={16} />} onClick={() => window.location.href = `/roles/edit/${role.id}`}>Modifier</Button>
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
                    {grouped.map((group) => (
                        <div key={group.label} className="rounded-lg border border-[var(--color-border)] p-3">
                            <p className="text-sm font-semibold text-[var(--color-text)] mb-2">{group.label}</p>
                            <div className="flex flex-wrap gap-1">
                                {group.names.map((name) => <Badge key={name} variant="muted">{permissionMeta.labels[name] ?? name}</Badge>)}
                            </div>
                        </div>
                    ))}
                </div>
            </Card>
        </AuthenticatedLayout>
    );
}
