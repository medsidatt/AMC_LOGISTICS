import Drawer from '@/components/ui/Drawer';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import DetailPanel, { DetailItem } from '@/components/ui/DetailPanel';
import { Users as UsersIcon, Pencil, Mail, Calendar } from 'lucide-react';
import type { User } from '../types';

interface Props {
    user: User;
    canEdit: boolean;
    onEdit: () => void;
    onClose: () => void;
}

/** User details (read-only) — Administration Details Drawer (reuses the standard). */
export default function UserDetailsDrawer({ user, canEdit, onEdit, onClose }: Props) {
    return (
        <Drawer
            open
            onClose={onClose}
            size="lg"
            icon={<UsersIcon size={18} className="text-[var(--color-primary)]" />}
            title={user.name}
            footer={canEdit ? <Button variant="secondary" icon={<Pencil size={15} />} onClick={onEdit}>Modifier</Button> : undefined}
        >
            <DetailPanel columns={2}>
                <DetailItem label="Nom" value={user.name} />
                <DetailItem label="Email" value={user.email} icon={<Mail size={13} />} />
                <DetailItem
                    label="Statut"
                    value={<Badge variant={user.is_suspended ? 'danger' : 'success'}>{user.is_suspended ? 'Suspendu' : 'Actif'}</Badge>}
                />
                <DetailItem label="Créé le" value={user.created_at ?? '—'} icon={<Calendar size={13} />} />
            </DetailPanel>

            <section>
                <h3 className="text-xs font-semibold uppercase tracking-wide text-[var(--color-text-muted)] mb-2 border-l-2 border-[var(--color-primary)] pl-2">Rôles</h3>
                {user.roles.length === 0 ? (
                    <p className="text-sm text-[var(--color-text-muted)]">Aucun rôle assigné.</p>
                ) : (
                    <div className="flex flex-wrap gap-1">
                        {user.roles.map((r) => <Badge key={r.id} variant="primary">{r.name}</Badge>)}
                    </div>
                )}
            </section>

            {user.direct_permissions.length > 0 && (
                <section>
                    <h3 className="text-xs font-semibold uppercase tracking-wide text-[var(--color-text-muted)] mb-2 border-l-2 border-amber-500 pl-2">Accès supplémentaires (hors rôle)</h3>
                    <div className="flex flex-wrap gap-1">
                        {user.direct_permissions.map((p) => <Badge key={p} variant="muted">{p}</Badge>)}
                    </div>
                </section>
            )}
        </Drawer>
    );
}
