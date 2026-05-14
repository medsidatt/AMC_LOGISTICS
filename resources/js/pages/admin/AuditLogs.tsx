import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import { Activity, Search, X, ChevronDown, ChevronRight } from 'lucide-react';
import { clsx } from 'clsx';

interface LogRow {
    id: number;
    user_name: string;
    user_email: string | null;
    action: string;
    subject_type: string | null;
    subject_label: string | null;
    subject_id: string | null;
    changes: { before?: Record<string, any>; after?: Record<string, any> } | null;
    ip_address: string | null;
    created_at: string | null;
}

interface Props {
    logs: { data: LogRow[]; links: any[]; meta?: any };
    users: { id: number; name: string }[];
    actions: string[];
    filters: {
        user_id: number | null;
        action: string | null;
        subject_type: string | null;
        search: string | null;
        from: string | null;
        to: string | null;
    };
}

const ACTION_VARIANT: Record<string, 'default' | 'success' | 'warning' | 'danger' | 'info'> = {
    created: 'success',
    updated: 'info',
    deleted: 'danger',
    restored: 'success',
    login: 'default',
    logout: 'default',
    login_failed: 'warning',
};

const ACTION_LABEL: Record<string, string> = {
    created: 'Création',
    updated: 'Modification',
    deleted: 'Suppression',
    restored: 'Restauration',
    login: 'Connexion',
    logout: 'Déconnexion',
    login_failed: 'Échec connexion',
};

export default function AuditLogs({ logs, users, actions, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [userId, setUserId] = useState<string>(filters.user_id?.toString() ?? '');
    const [action, setAction] = useState<string>(filters.action ?? '');
    const [from, setFrom] = useState(filters.from ?? '');
    const [to, setTo] = useState(filters.to ?? '');
    const [expanded, setExpanded] = useState<number | null>(null);

    const apply = () => {
        router.get('/admin/audit-logs', {
            search: search || undefined,
            user_id: userId || undefined,
            action: action || undefined,
            from: from || undefined,
            to: to || undefined,
        }, { preserveState: true, replace: true });
    };

    const reset = () => {
        setSearch(''); setUserId(''); setAction(''); setFrom(''); setTo('');
        router.get('/admin/audit-logs', {}, { preserveState: true, replace: true });
    };

    return (
        <AuthenticatedLayout title="Journal d'activité">
            <Head title="Journal d'activité" />

            <div className="flex items-center gap-2 mb-4">
                <Activity size={22} className="text-[var(--color-primary)]" />
                <h1 className="text-xl font-semibold">Journal d'activité</h1>
            </div>

            {/* Filters */}
            <Card className="mb-4">
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                    <div className="lg:col-span-2 relative">
                        <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-[var(--color-text-muted)]" />
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && apply()}
                            placeholder="Recherche (utilisateur, sujet, action)..."
                            className="w-full pl-9 pr-3 py-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm"
                        />
                    </div>
                    <select
                        value={userId}
                        onChange={(e) => setUserId(e.target.value)}
                        className="w-full px-3 py-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm"
                    >
                        <option value="">— Tous les utilisateurs —</option>
                        {users.map((u) => <option key={u.id} value={u.id}>{u.name}</option>)}
                    </select>
                    <select
                        value={action}
                        onChange={(e) => setAction(e.target.value)}
                        className="w-full px-3 py-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm"
                    >
                        <option value="">— Toutes actions —</option>
                        {actions.map((a) => <option key={a} value={a}>{ACTION_LABEL[a] ?? a}</option>)}
                    </select>
                    <div className="flex gap-2">
                        <input type="date" value={from} onChange={(e) => setFrom(e.target.value)} className="flex-1 px-2 py-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm" />
                        <input type="date" value={to} onChange={(e) => setTo(e.target.value)} className="flex-1 px-2 py-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm" />
                    </div>
                </div>
                <div className="flex gap-2 mt-3">
                    <Button type="button" onClick={apply}>Filtrer</Button>
                    <Button type="button" variant="secondary" onClick={reset}>
                        <X size={14} className="mr-1" />Réinitialiser
                    </Button>
                </div>
            </Card>

            {/* Logs table */}
            <Card>
                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="text-left border-b border-[var(--color-border)]">
                                <th className="py-2 px-2 w-8"></th>
                                <th className="py-2 px-3">Date</th>
                                <th className="py-2 px-3">Utilisateur</th>
                                <th className="py-2 px-3">Action</th>
                                <th className="py-2 px-3">Sujet</th>
                                <th className="py-2 px-3">IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            {logs.data.length === 0 ? (
                                <tr><td colSpan={6} className="py-6 text-center text-[var(--color-text-muted)]">Aucune entrée.</td></tr>
                            ) : logs.data.map((row) => {
                                const isOpen = expanded === row.id;
                                const hasDetail = !!row.changes;
                                return (
                                    <>
                                        <tr key={row.id} className={clsx('border-b border-[var(--color-border)]', hasDetail && 'cursor-pointer hover:bg-[var(--color-surface-hover)]')} onClick={() => hasDetail && setExpanded(isOpen ? null : row.id)}>
                                            <td className="py-2 px-2 text-[var(--color-text-muted)]">
                                                {hasDetail ? (isOpen ? <ChevronDown size={14} /> : <ChevronRight size={14} />) : null}
                                            </td>
                                            <td className="py-2 px-3 whitespace-nowrap font-mono text-xs">{row.created_at}</td>
                                            <td className="py-2 px-3">
                                                <div className="font-medium">{row.user_name}</div>
                                                {row.user_email && <div className="text-xs text-[var(--color-text-muted)]">{row.user_email}</div>}
                                            </td>
                                            <td className="py-2 px-3">
                                                <Badge variant={ACTION_VARIANT[row.action] ?? 'default'}>
                                                    {ACTION_LABEL[row.action] ?? row.action}
                                                </Badge>
                                            </td>
                                            <td className="py-2 px-3">
                                                {row.subject_type ? (
                                                    <>
                                                        <span className="font-medium">{row.subject_type}</span>
                                                        {row.subject_label && <span className="text-[var(--color-text-muted)]"> — {row.subject_label}</span>}
                                                    </>
                                                ) : <span className="text-[var(--color-text-muted)]">—</span>}
                                            </td>
                                            <td className="py-2 px-3 font-mono text-xs text-[var(--color-text-muted)]">{row.ip_address ?? '—'}</td>
                                        </tr>
                                        {isOpen && row.changes && (
                                            <tr className="border-b border-[var(--color-border)] bg-[var(--color-surface-hover)]/50">
                                                <td colSpan={6} className="px-6 py-3">
                                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                                                        {row.changes.before && (
                                                            <div>
                                                                <p className="text-xs uppercase text-[var(--color-text-muted)] mb-1 font-semibold">Avant</p>
                                                                <pre className="text-xs bg-[var(--color-surface)] p-2 rounded border border-[var(--color-border)] overflow-x-auto">{JSON.stringify(row.changes.before, null, 2)}</pre>
                                                            </div>
                                                        )}
                                                        {row.changes.after && (
                                                            <div>
                                                                <p className="text-xs uppercase text-[var(--color-text-muted)] mb-1 font-semibold">Après</p>
                                                                <pre className="text-xs bg-[var(--color-surface)] p-2 rounded border border-[var(--color-border)] overflow-x-auto">{JSON.stringify(row.changes.after, null, 2)}</pre>
                                                            </div>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        )}
                                    </>
                                );
                            })}
                        </tbody>
                    </table>
                </div>

                {/* Pagination */}
                {logs.links && logs.links.length > 3 && (
                    <div className="flex flex-wrap gap-1 mt-4 justify-center">
                        {logs.links.map((link: any, idx: number) => (
                            <Link
                                key={idx}
                                href={link.url ?? '#'}
                                preserveScroll
                                className={clsx(
                                    'px-3 py-1 rounded text-xs border',
                                    link.active
                                        ? 'bg-[var(--color-primary)] text-white border-[var(--color-primary)]'
                                        : 'bg-[var(--color-surface)] border-[var(--color-border)] text-[var(--color-text-secondary)]',
                                    !link.url && 'opacity-50 pointer-events-none'
                                )}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}
            </Card>
        </AuthenticatedLayout>
    );
}
