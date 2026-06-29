import { useMemo } from 'react';
import Drawer from '@/components/ui/Drawer';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import DetailPanel, { DetailItem } from '@/components/ui/DetailPanel';
import { ShieldCheck, Pencil, KeyRound } from 'lucide-react';
import type { PermissionMeta, Role } from '../types';

interface Props {
    role: Role;
    permissionMeta: PermissionMeta;
    description?: string;
    canEdit: boolean;
    onEdit: () => void;
    onClose: () => void;
}

/**
 * Role details (read-only) — the Administration Details Drawer reference.
 * Shows role name, permission count, and permissions grouped by category
 * (the grouping reused by Users and the remaining Administration modules).
 */
export default function RoleDetailsDrawer({ role, permissionMeta, description, canEdit, onEdit, onClose }: Props) {
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
        <Drawer
            open
            onClose={onClose}
            size="lg"
            icon={<ShieldCheck size={18} className="text-[var(--color-primary)]" />}
            title={role.name}
            footer={canEdit ? <Button variant="secondary" icon={<Pencil size={15} />} onClick={onEdit}>Modifier</Button> : undefined}
        >
            <DetailPanel columns={2}>
                <DetailItem label="Rôle" value={role.name} />
                <DetailItem label="Permissions" value={role.permissions.length} icon={<KeyRound size={13} />} />
            </DetailPanel>

            {description && (
                <p className="text-sm text-[var(--color-text-muted)]">{description}</p>
            )}

            <section>
                <h3 className="text-xs font-semibold uppercase tracking-wide text-[var(--color-text-muted)] mb-2 border-l-2 border-[var(--color-primary)] pl-2">Permissions accordées</h3>
                {grouped.length === 0 ? (
                    <p className="text-sm text-[var(--color-text-muted)]">Aucune permission accordée.</p>
                ) : (
                    <div className="grid sm:grid-cols-2 gap-3">
                        {grouped.map((group) => (
                            <div key={group.label} className="rounded-lg border border-[var(--color-border)] p-3">
                                <p className="text-sm font-semibold text-[var(--color-text)] mb-2">{group.label}</p>
                                <div className="flex flex-wrap gap-1">
                                    {group.names.map((name) => (
                                        <Badge key={name} variant="muted">{permissionMeta.labels[name] ?? name}</Badge>
                                    ))}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </section>
        </Drawer>
    );
}
