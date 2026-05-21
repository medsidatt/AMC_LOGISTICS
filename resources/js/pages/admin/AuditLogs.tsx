// PLAN-NOTE (à retirer plus tard) ────────────────────────────────────────────
// Itération actuelle : filtres enrichis (sujet, presets de période), nouveau
// diff field-par-field, bouton Exporter (xlsx).
// Prochaines itérations envisagées :
//   - Page détail dédiée par entrée (route /admin/audit-logs/{log}) au lieu
//     de l'ouverture en ligne — utile pour partager un lien direct.
//   - Lien profond depuis la cellule "Sujet" vers la ressource correspondante
//     (truck/driver/user/project show page) si subject_id est résoluble.
//   - Composant `AuditTrail` réutilisable affichant l'historique d'une
//     ressource (à intégrer dans les pages Show des modèles trackés).
//   - Sélecteur multi-action (cocher plusieurs actions à la fois).
// ─────────────────────────────────────────────────────────────────────────────

import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import { Activity, Search, X, ChevronDown, ChevronRight, Download, SlidersHorizontal, Calendar } from 'lucide-react';
import { clsx } from 'clsx';

interface LogRow {
    id: number;
    user_name: string;
    user_email: string | null;
    action: string;
    subject_type: string | null;
    subject_type_full: string | null;
    subject_label: string | null;
    subject_id: string | null;
    changes: { before?: Record<string, any>; after?: Record<string, any> } | null;
    ip_address: string | null;
    user_agent: string | null;
    created_at: string | null;
}

interface SubjectType {
    value: string;
    label: string;
}

interface Props {
    logs: { data: LogRow[]; links: any[]; meta?: any };
    users: { id: number; name: string }[];
    actions: string[];
    subjectTypes: SubjectType[];
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

function toIsoDate(d: Date): string {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

function formatValue(v: any): { text: string; isObject: boolean } {
    if (v === null || v === undefined) return { text: '—', isObject: false };
    if (typeof v === 'object') return { text: JSON.stringify(v, null, 2), isObject: true };
    return { text: String(v), isObject: false };
}

export default function AuditLogs({ logs, users, actions, subjectTypes, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [userId, setUserId] = useState<string>(filters.user_id?.toString() ?? '');
    const [action, setAction] = useState<string>(filters.action ?? '');
    const [subjectType, setSubjectType] = useState<string>(filters.subject_type ?? '');
    const [from, setFrom] = useState(filters.from ?? '');
    const [to, setTo] = useState(filters.to ?? '');
    const [expanded, setExpanded] = useState<number | null>(null);

    const buildQuery = (overrides: Partial<Record<string, string>> = {}) => ({
        search: (overrides.search ?? search) || undefined,
        user_id: (overrides.user_id ?? userId) || undefined,
        action: (overrides.action ?? action) || undefined,
        subject_type: (overrides.subject_type ?? subjectType) || undefined,
        from: (overrides.from ?? from) || undefined,
        to: (overrides.to ?? to) || undefined,
    });

    const apply = (overrides: Partial<Record<string, string>> = {}) => {
        router.get('/admin/audit-logs', buildQuery(overrides), { preserveState: true, replace: true });
    };

    const reset = () => {
        setSearch(''); setUserId(''); setAction(''); setSubjectType(''); setFrom(''); setTo('');
        router.get('/admin/audit-logs', {}, { preserveState: true, replace: true });
    };

    const applyPreset = (kind: 'today' | '7d' | '30d' | 'month') => {
        const now = new Date();
        let start: Date;
        const end = now;
        if (kind === 'today') {
            start = now;
        } else if (kind === '7d') {
            start = new Date(now); start.setDate(now.getDate() - 6);
        } else if (kind === '30d') {
            start = new Date(now); start.setDate(now.getDate() - 29);
        } else {
            start = new Date(now.getFullYear(), now.getMonth(), 1);
        }
        const f = toIsoDate(start);
        const t = toIsoDate(end);
        setFrom(f); setTo(t);
        apply({ from: f, to: t });
    };

    const exportXlsx = () => {
        const params = new URLSearchParams();
        const q = buildQuery();
        Object.entries(q).forEach(([k, v]) => {
            if (v) params.set(k, String(v));
        });
        const qs = params.toString();
        window.location.href = '/admin/audit-logs/export' + (qs ? `?${qs}` : '');
    };

    // Debounce search: auto-apply 400ms after the user stops typing.
    const isFirstRender = useRef(true);
    useEffect(() => {
        if (isFirstRender.current) { isFirstRender.current = false; return; }
        const t = setTimeout(() => apply({ search }), 400);
        return () => clearTimeout(t);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const activePreset = useMemo<'today' | '7d' | '30d' | 'month' | null>(() => {
        if (!from || !to) return null;
        const today = toIsoDate(new Date());
        if (from === today && to === today) return 'today';
        const d7 = new Date(); d7.setDate(d7.getDate() - 6);
        if (from === toIsoDate(d7) && to === today) return '7d';
        const d30 = new Date(); d30.setDate(d30.getDate() - 29);
        if (from === toIsoDate(d30) && to === today) return '30d';
        const m1 = new Date(); const monthStart = new Date(m1.getFullYear(), m1.getMonth(), 1);
        if (from === toIsoDate(monthStart) && to === today) return 'month';
        return null;
    }, [from, to]);

    const activeCount = [search, userId, action, subjectType, from, to].filter(Boolean).length;

    return (
        <AuthenticatedLayout title="Journal d'activité">
            <Head title="Journal d'activité" />

            <div className="flex items-center gap-2 mb-4">
                <Activity size={22} className="text-[var(--color-primary)]" />
                <h1 className="text-xl font-semibold">Journal d'activité</h1>
            </div>

            {/* Filters */}
            <Card className="mb-4 !p-0 overflow-hidden">
                {/* Filter header */}
                <div className="flex flex-wrap items-center justify-between gap-3 px-4 py-3 border-b border-[var(--color-border)] bg-[var(--color-surface)]/40">
                    <div className="flex items-center gap-2">
                        <SlidersHorizontal size={16} className="text-[var(--color-text-muted)]" />
                        <h2 className="text-sm font-semibold uppercase tracking-wide text-[var(--color-text-secondary)]">Filtres</h2>
                        {activeCount > 0 && (
                            <span className="ml-1 inline-flex items-center px-2 py-0.5 text-[11px] font-semibold rounded-full bg-[var(--color-primary)]/10 text-[var(--color-primary)]">
                                {activeCount} actif{activeCount > 1 ? 's' : ''}
                            </span>
                        )}
                    </div>
                    <div className="flex items-center gap-2">
                        <Button type="button" variant="ghost" size="sm" onClick={reset} disabled={activeCount === 0}>
                            <X size={14} className="mr-1" />Réinitialiser
                        </Button>
                        <Button type="button" variant="secondary" size="sm" onClick={exportXlsx} icon={<Download size={14} />}>
                            Exporter Excel
                        </Button>
                    </div>
                </div>

                {/* Filter body */}
                <div className="px-4 py-4 space-y-4">
                    {/* Search row */}
                    <div>
                        <label className="block text-[11px] font-semibold uppercase tracking-wide text-[var(--color-text-muted)] mb-1.5">
                            Recherche
                        </label>
                        <div className="relative">
                            <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-[var(--color-text-muted)]" />
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Rechercher par utilisateur, sujet ou action..."
                                className="w-full pl-9 pr-9 py-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]/30 focus:border-[var(--color-primary)]"
                            />
                            {search && (
                                <button
                                    type="button"
                                    onClick={() => { setSearch(''); apply({ search: '' }); }}
                                    className="absolute right-2 top-1/2 -translate-y-1/2 p-1 rounded hover:bg-[var(--color-surface-hover)] text-[var(--color-text-muted)]"
                                    aria-label="Effacer la recherche"
                                >
                                    <X size={14} />
                                </button>
                            )}
                        </div>
                    </div>

                    {/* Select row */}
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <FilterSelect
                            label="Utilisateur"
                            value={userId}
                            placeholder="Tous les utilisateurs"
                            onChange={(v) => { setUserId(v); apply({ user_id: v }); }}
                            options={users.map((u) => ({ value: String(u.id), label: u.name }))}
                        />
                        <FilterSelect
                            label="Action"
                            value={action}
                            placeholder="Toutes les actions"
                            onChange={(v) => { setAction(v); apply({ action: v }); }}
                            options={actions.map((a) => ({ value: a, label: ACTION_LABEL[a] ?? a }))}
                        />
                        <FilterSelect
                            label="Sujet"
                            value={subjectType}
                            placeholder="Tous les sujets"
                            onChange={(v) => { setSubjectType(v); apply({ subject_type: v }); }}
                            options={subjectTypes.map((s) => ({ value: s.value, label: s.label }))}
                        />
                    </div>

                    {/* Period row */}
                    <div>
                        <label className="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide text-[var(--color-text-muted)] mb-2">
                            <Calendar size={12} />
                            Période
                        </label>
                        <div className="flex flex-wrap items-center gap-2 mb-2">
                            {([
                                { key: 'today', label: "Aujourd'hui" },
                                { key: '7d', label: '7 jours' },
                                { key: '30d', label: '30 jours' },
                                { key: 'month', label: 'Ce mois' },
                            ] as const).map((p) => (
                                <button
                                    key={p.key}
                                    type="button"
                                    onClick={() => applyPreset(p.key)}
                                    className={clsx(
                                        'px-3 py-1 text-xs rounded-full border transition-colors',
                                        activePreset === p.key
                                            ? 'bg-[var(--color-primary)] border-[var(--color-primary)] text-white'
                                            : 'bg-[var(--color-surface)] border-[var(--color-border)] hover:bg-[var(--color-surface-hover)] text-[var(--color-text-secondary)]'
                                    )}
                                >
                                    {p.label}
                                </button>
                            ))}
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="text-xs text-[var(--color-text-muted)]">Du</span>
                            <input
                                type="date"
                                value={from}
                                onChange={(e) => { const v = e.target.value; setFrom(v); apply({ from: v }); }}
                                className="px-2 py-1.5 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]/30 focus:border-[var(--color-primary)]"
                            />
                            <span className="text-xs text-[var(--color-text-muted)]">au</span>
                            <input
                                type="date"
                                value={to}
                                onChange={(e) => { const v = e.target.value; setTo(v); apply({ to: v }); }}
                                className="px-2 py-1.5 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]/30 focus:border-[var(--color-primary)]"
                            />
                            {(from || to) && (
                                <button
                                    type="button"
                                    onClick={() => { setFrom(''); setTo(''); apply({ from: '', to: '' }); }}
                                    className="ml-1 inline-flex items-center gap-1 px-2 py-1 text-xs rounded-md text-[var(--color-text-muted)] hover:bg-[var(--color-surface-hover)]"
                                >
                                    <X size={12} />Effacer
                                </button>
                            )}
                        </div>
                    </div>
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
                                const hasDetail = !!row.changes || !!row.user_agent;
                                return (
                                    <ExpandableRow
                                        key={row.id}
                                        row={row}
                                        isOpen={isOpen}
                                        hasDetail={hasDetail}
                                        onToggle={() => hasDetail && setExpanded(isOpen ? null : row.id)}
                                    />
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

interface FilterSelectProps {
    label: string;
    value: string;
    placeholder: string;
    options: { value: string; label: string }[];
    onChange: (value: string) => void;
}

function FilterSelect({ label, value, placeholder, options, onChange }: FilterSelectProps) {
    return (
        <div>
            <label className="block text-[11px] font-semibold uppercase tracking-wide text-[var(--color-text-muted)] mb-1.5">
                {label}
            </label>
            <div className="relative">
                <select
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    className={clsx(
                        'w-full appearance-none pl-3 pr-9 py-2 rounded-lg border bg-[var(--color-surface)] text-sm cursor-pointer',
                        'focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]/30 focus:border-[var(--color-primary)]',
                        value
                            ? 'border-[var(--color-primary)]/60 text-[var(--color-text)]'
                            : 'border-[var(--color-border)] text-[var(--color-text-secondary)]'
                    )}
                >
                    <option value="">— {placeholder} —</option>
                    {options.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
                </select>
                <ChevronDown size={14} className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-[var(--color-text-muted)]" />
            </div>
        </div>
    );
}

interface ExpandableRowProps {
    row: LogRow;
    isOpen: boolean;
    hasDetail: boolean;
    onToggle: () => void;
}

function ExpandableRow({ row, isOpen, hasDetail, onToggle }: ExpandableRowProps) {
    return (
        <>
            <tr
                className={clsx('border-b border-[var(--color-border)]', hasDetail && 'cursor-pointer hover:bg-[var(--color-surface-hover)]')}
                onClick={onToggle}
            >
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
                            <span className="font-medium" title={row.subject_type_full ?? undefined}>{row.subject_type}</span>
                            {row.subject_label && <span className="text-[var(--color-text-muted)]"> — {row.subject_label}</span>}
                        </>
                    ) : <span className="text-[var(--color-text-muted)]">—</span>}
                </td>
                <td className="py-2 px-3 font-mono text-xs text-[var(--color-text-muted)]">{row.ip_address ?? '—'}</td>
            </tr>
            {isOpen && (
                <tr className="border-b border-[var(--color-border)] bg-[var(--color-surface-hover)]/50">
                    <td colSpan={6} className="px-6 py-3">
                        {row.changes ? <DiffTable changes={row.changes} /> : (
                            <p className="text-xs text-[var(--color-text-muted)] italic">Aucune modification enregistrée.</p>
                        )}
                        {row.user_agent && (
                            <p className="text-xs text-[var(--color-text-muted)] mt-3 break-all">
                                <span className="font-semibold">User-Agent :</span> {row.user_agent}
                            </p>
                        )}
                    </td>
                </tr>
            )}
        </>
    );
}

function DiffTable({ changes }: { changes: { before?: Record<string, any>; after?: Record<string, any> } }) {
    const keys = useMemo(() => {
        const before = changes.before ?? {};
        const after = changes.after ?? {};
        return Array.from(new Set([...Object.keys(before), ...Object.keys(after)])).sort();
    }, [changes]);

    if (keys.length === 0) {
        return <p className="text-xs text-[var(--color-text-muted)] italic">Aucun détail.</p>;
    }

    const before = changes.before ?? {};
    const after = changes.after ?? {};

    return (
        <div className="overflow-x-auto">
            <table className="min-w-full text-xs">
                <thead>
                    <tr className="text-left text-[var(--color-text-muted)] uppercase">
                        <th className="py-1.5 pr-3 font-semibold w-1/4">Champ</th>
                        <th className="py-1.5 pr-3 font-semibold w-3/8">Avant</th>
                        <th className="py-1.5 font-semibold w-3/8">Après</th>
                    </tr>
                </thead>
                <tbody>
                    {keys.map((k) => {
                        const b = formatValue(before[k]);
                        const a = formatValue(after[k]);
                        const changed = b.text !== a.text;
                        const isLong = b.text.length > 120 || a.text.length > 120 || b.isObject || a.isObject;
                        return (
                            <tr
                                key={k}
                                className={clsx(
                                    'border-t border-[var(--color-border)]/60',
                                    changed ? 'border-l-2 border-l-[var(--color-primary)] bg-[var(--color-surface)]/60' : 'opacity-60'
                                )}
                            >
                                <td className="py-1.5 px-2 font-mono text-[11px] align-top">{k}</td>
                                <td className="py-1.5 pr-3 align-top">
                                    {isLong
                                        ? <pre className="text-[11px] whitespace-pre-wrap break-words font-mono">{b.text}</pre>
                                        : <span className="font-mono text-[11px]">{b.text}</span>}
                                </td>
                                <td className="py-1.5 align-top">
                                    {isLong
                                        ? <pre className="text-[11px] whitespace-pre-wrap break-words font-mono">{a.text}</pre>
                                        : <span className={clsx('font-mono text-[11px]', changed && 'font-semibold')}>{a.text}</span>}
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}
