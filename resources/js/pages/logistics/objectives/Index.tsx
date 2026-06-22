import { Head, useForm, router, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import Modal from '@/components/ui/Modal';
import FormInput from '@/components/ui/FormInput';
import FormSelect from '@/components/ui/FormSelect';
import FormTextarea from '@/components/ui/FormTextarea';
import DataTable from '@/components/ui/DataTable';
import ConfirmDialog from '@/components/ui/ConfirmDialog';
import { Plus, Pencil, Archive, ArchiveRestore, Target, CheckCircle2, Gauge, CalendarRange } from 'lucide-react';
import { clsx } from 'clsx';
import { usePermission } from '@/hooks/usePermission';
import type { PlanningMode } from '@/types/achievement';

interface Objective {
    id: number;
    period_type: PlanningMode;
    start_date: string;
    end_date: string;
    target_tons: number;
    target_rotations: number;
    achieved_tons: number;
    achieved_rotations: number;
    remaining_tons: number;
    remaining_rotations: number;
    pct: number | null;
    working_trucks: number;
    notes: string | null;
    archived: boolean;
    created_by: string | null;
}

interface Props {
    objectives: Objective[];
    showArchived: boolean;
    periodTypes: PlanningMode[];
}

const fmt = (n: number) => Math.round(n).toLocaleString('fr-FR');
const today = () => new Date().toISOString().slice(0, 10);

const TYPE_LABEL: Record<PlanningMode, string> = { WEEK: 'Semaine', MONTH: 'Mois', YEAR: 'Année', CUSTOM: 'Personnalisé' };
const TYPE_VARIANT: Record<PlanningMode, 'primary' | 'info' | 'success' | 'muted'> = { WEEK: 'primary', MONTH: 'info', YEAR: 'success', CUSTOM: 'muted' };

const pctColor = (pct: number | null) =>
    pct == null ? 'bg-[var(--color-surface-hover)]'
        : pct >= 100 ? 'bg-emerald-500'
            : pct >= 75 ? 'bg-[var(--color-primary)]'
                : pct >= 50 ? 'bg-amber-500'
                    : 'bg-red-500';

export default function ObjectivesIndex({ objectives, showArchived, periodTypes }: Props) {
    const [modal, setModal] = useState<'create' | 'edit' | null>(null);
    const [selected, setSelected] = useState<Objective | null>(null);
    const [archiveTarget, setArchiveTarget] = useState<Objective | null>(null);
    const { can } = usePermission();
    const canManage = can('fleet-roster-plan');

    const typeOpts = periodTypes.map((t) => ({ value: t, label: TYPE_LABEL[t] }));

    const createForm = useForm({ period_type: 'MONTH' as PlanningMode, start_date: today(), end_date: '', target_tons: '', notes: '' });
    const editForm = useForm({ target_tons: '', notes: '' });

    const openEdit = (o: Objective) => {
        setSelected(o);
        editForm.setData({ target_tons: String(o.target_tons), notes: o.notes ?? '' });
        setModal('edit');
    };

    const submitCreate = (e: React.FormEvent) => {
        e.preventDefault();
        createForm.post('/logistics/objectives', { onSuccess: () => { setModal(null); createForm.reset(); } });
    };
    const submitEdit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selected) return;
        editForm.put(`/logistics/objectives/${selected.id}`, { onSuccess: () => setModal(null) });
    };
    const confirmArchive = () => {
        if (!archiveTarget) return;
        router.post(`/logistics/objectives/${archiveTarget.id}/archive`, {}, { onFinish: () => setArchiveTarget(null) });
    };

    // Overview (over the objectives currently shown).
    const overview = useMemo(() => {
        const planned = objectives.reduce((s, o) => s + o.target_tons, 0);
        const done = objectives.reduce((s, o) => s + o.achieved_tons, 0);
        const avgPct = objectives.length
            ? Math.round(objectives.reduce((s, o) => s + (o.pct ?? 0), 0) / objectives.length)
            : 0;
        return { count: objectives.length, planned, done, avgPct };
    }, [objectives]);

    // Timeline scaling across all shown objectives.
    const timeline = useMemo(() => {
        if (!objectives.length) return null;
        const starts = objectives.map((o) => new Date(o.start_date + 'T00:00:00').getTime());
        const ends = objectives.map((o) => new Date(o.end_date + 'T00:00:00').getTime());
        const min = Math.min(...starts);
        const max = Math.max(...ends);
        const span = Math.max(1, max - min);
        return objectives.map((o) => {
            const s = new Date(o.start_date + 'T00:00:00').getTime();
            const e = new Date(o.end_date + 'T00:00:00').getTime();
            return { o, left: ((s - min) / span) * 100, width: Math.max(2, ((e - s) / span) * 100) };
        });
    }, [objectives]);

    return (
        <AuthenticatedLayout title="Objectifs">
            <Head title="Objectifs de planification" />
            <div className="space-y-5">
                <div className="flex items-start justify-between gap-3 flex-wrap">
                    <div>
                        <div className="flex items-center gap-2">
                            <Target size={22} className="text-[var(--color-primary)]" />
                            <h1 className="text-xl font-semibold">Objectifs de planification</h1>
                        </div>
                        <p className="text-sm text-[var(--color-text-muted)] mt-1">Définissez des objectifs par semaine, mois, année ou plage personnalisée.</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button
                            variant="secondary"
                            onClick={() => router.get('/logistics/objectives', { archived: showArchived ? 0 : 1 }, { preserveScroll: true })}
                        >
                            {showArchived ? 'Masquer archivés' : 'Voir archivés'}
                        </Button>
                        {canManage && (
                            <Button icon={<Plus size={16} />} onClick={() => { createForm.reset(); createForm.setData('start_date', today()); setModal('create'); }}>
                                Nouvel objectif
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <OverviewCard icon={<CalendarRange size={14} />} label="Objectifs" value={String(overview.count)} />
                    <OverviewCard icon={<Target size={14} />} label="Planifié" value={`${fmt(overview.planned)} t`} />
                    <OverviewCard icon={<CheckCircle2 size={14} className="text-emerald-500" />} label="Réalisé" value={`${fmt(overview.done)} t`} />
                    <OverviewCard icon={<Gauge size={14} />} label="Réalisation moy." value={`${overview.avgPct}%`} />
                </div>

                {timeline && (
                    <Card header="Chronologie des objectifs">
                        <div className="space-y-2">
                            {timeline.map(({ o, left, width }) => (
                                <div key={o.id} className="flex items-center gap-3">
                                    <div className="w-28 shrink-0 text-xs text-[var(--color-text-muted)] truncate">
                                        <Badge variant={TYPE_VARIANT[o.period_type]}>{TYPE_LABEL[o.period_type]}</Badge>
                                    </div>
                                    <div className="relative flex-1 h-6 rounded-md bg-[var(--color-surface-hover)] overflow-hidden">
                                        <div
                                            className={clsx('absolute top-0 h-full rounded-md opacity-80', pctColor(o.pct))}
                                            style={{ left: `${left}%`, width: `${width}%` }}
                                            title={`${o.start_date} → ${o.end_date} · ${o.pct ?? 0}%`}
                                        />
                                    </div>
                                    <div className="w-20 shrink-0 text-right text-xs font-semibold tabular-nums">{o.pct ?? 0}%</div>
                                </div>
                            ))}
                        </div>
                    </Card>
                )}

                <Card padding={false}>
                    <div className="p-5">
                        <DataTable
                            data={objectives}
                            columns={[
                                { key: 'period_type', label: 'Type', render: (o) => <Badge variant={TYPE_VARIANT[o.period_type]}>{TYPE_LABEL[o.period_type]}</Badge> },
                                { key: 'start_date', label: 'Période', render: (o) => <span className="whitespace-nowrap">{o.start_date} → {o.end_date}</span> },
                                { key: 'target_tons', label: 'Planifié', render: (o) => <span className="font-mono">{fmt(o.target_tons)} t</span> },
                                { key: 'achieved_tons', label: 'Réalisé', render: (o) => <span className="font-mono">{fmt(o.achieved_tons)} t</span> },
                                {
                                    key: 'pct', label: 'Avancement', render: (o) => (
                                        <div className="flex items-center gap-2 min-w-[7rem]">
                                            <div className="flex-1 h-2 rounded-full bg-[var(--color-surface-hover)] overflow-hidden">
                                                <div className={clsx('h-full rounded-full', pctColor(o.pct))} style={{ width: `${Math.min(100, o.pct ?? 0)}%` }} />
                                            </div>
                                            <span className="text-xs font-semibold w-9 text-right tabular-nums">{o.pct ?? 0}%</span>
                                        </div>
                                    ),
                                },
                                { key: 'working_trucks', label: 'Camions', hideOnMobile: true, render: (o) => o.working_trucks },
                                { key: 'created_by', label: 'Créé par', hideOnMobile: true, render: (o) => o.created_by ?? '—' },
                                {
                                    key: 'actions', label: 'Actions', sortable: false, render: (o) => canManage ? (
                                        <div className="flex items-center gap-1">
                                            <button onClick={() => openEdit(o)} title="Modifier" className="p-1.5 rounded-lg text-[var(--color-primary)] hover:bg-[var(--color-primary)]/10 cursor-pointer">
                                                <Pencil size={15} />
                                            </button>
                                            <button onClick={() => setArchiveTarget(o)} title={o.archived ? 'Réactiver' : 'Archiver'} className="p-1.5 rounded-lg text-[var(--color-text-secondary)] hover:bg-[var(--color-surface-hover)] cursor-pointer">
                                                {o.archived ? <ArchiveRestore size={15} /> : <Archive size={15} />}
                                            </button>
                                        </div>
                                    ) : null,
                                },
                            ]}
                            searchable
                            emptyMessage="Aucun objectif défini."
                        />
                    </div>
                </Card>

                <p className="text-xs text-[var(--color-text-muted)]">
                    Astuce : le <Link href="/logistics/planning/weekly" className="text-[var(--color-primary)] hover:underline">tableau de suivi</Link> applique automatiquement l’objectif le plus précis (semaine → mois → année).
                </p>
            </div>

            {/* Create */}
            <Modal open={modal === 'create'} onClose={() => setModal(null)} title="Nouvel objectif">
                <form onSubmit={submitCreate}>
                    <FormSelect
                        label="Type de période"
                        options={typeOpts}
                        value={createForm.data.period_type}
                        onChange={(v) => createForm.setData('period_type', (v ?? 'MONTH') as PlanningMode)}
                        error={createForm.errors.period_type}
                        required
                    />
                    <FormInput
                        label={createForm.data.period_type === 'CUSTOM' ? 'Date de début' : 'Date de référence'}
                        type="date"
                        name="start_date"
                        value={createForm.data.start_date}
                        onChange={(e) => createForm.setData('start_date', e.target.value)}
                        error={createForm.errors.start_date}
                        required
                    />
                    {createForm.data.period_type === 'CUSTOM' && (
                        <FormInput
                            label="Date de fin"
                            type="date"
                            name="end_date"
                            value={createForm.data.end_date}
                            onChange={(e) => createForm.setData('end_date', e.target.value)}
                            error={createForm.errors.end_date}
                            required
                        />
                    )}
                    <FormInput
                        label="Objectif tonnage (t)"
                        type="number"
                        step="0.01"
                        min="0"
                        name="target_tons"
                        value={createForm.data.target_tons}
                        onChange={(e) => createForm.setData('target_tons', e.target.value)}
                        error={createForm.errors.target_tons}
                        required
                    />
                    <FormTextarea label="Notes" name="notes" value={createForm.data.notes} onChange={(e) => createForm.setData('notes', e.target.value)} error={createForm.errors.notes} />
                    <p className="text-xs text-[var(--color-text-muted)] -mt-2 mb-3">
                        Le tonnage est réparti automatiquement sur les camions en service pour la période.
                    </p>
                    <div className="flex justify-end gap-2 mt-2">
                        <Button variant="secondary" onClick={() => setModal(null)} type="button">Annuler</Button>
                        <Button type="submit" loading={createForm.processing}>Enregistrer</Button>
                    </div>
                </form>
            </Modal>

            {/* Edit */}
            <Modal open={modal === 'edit'} onClose={() => setModal(null)} title="Modifier l’objectif">
                <form onSubmit={submitEdit}>
                    {selected && (
                        <div className="mb-4 text-sm text-[var(--color-text-muted)]">
                            <Badge variant={TYPE_VARIANT[selected.period_type]}>{TYPE_LABEL[selected.period_type]}</Badge>
                            <span className="ml-2">{selected.start_date} → {selected.end_date}</span>
                        </div>
                    )}
                    <FormInput
                        label="Objectif tonnage (t)"
                        type="number"
                        step="0.01"
                        min="0"
                        name="target_tons"
                        value={editForm.data.target_tons}
                        onChange={(e) => editForm.setData('target_tons', e.target.value)}
                        error={editForm.errors.target_tons}
                        required
                        autoFocus
                    />
                    <FormTextarea label="Notes" name="notes" value={editForm.data.notes} onChange={(e) => editForm.setData('notes', e.target.value)} error={editForm.errors.notes} />
                    <div className="flex justify-end gap-2 mt-2">
                        <Button variant="secondary" onClick={() => setModal(null)} type="button">Annuler</Button>
                        <Button type="submit" loading={editForm.processing}>Enregistrer</Button>
                    </div>
                </form>
            </Modal>

            <ConfirmDialog
                open={!!archiveTarget}
                onClose={() => setArchiveTarget(null)}
                title={archiveTarget?.archived ? 'Réactiver l’objectif' : 'Archiver l’objectif'}
                message={archiveTarget?.archived ? 'L’objectif redeviendra actif et sera pris en compte dans le suivi.' : 'L’objectif sera conservé pour l’historique mais exclu du suivi.'}
                confirmLabel={archiveTarget?.archived ? 'Réactiver' : 'Archiver'}
                onConfirm={confirmArchive}
            />
        </AuthenticatedLayout>
    );
}

function OverviewCard({ icon, label, value }: { icon: React.ReactNode; label: string; value: string }) {
    return (
        <div className="rounded-xl border border-[var(--color-border)] p-4 bg-[var(--color-surface)]">
            <div className="flex items-center gap-1.5 text-xs uppercase tracking-wide text-[var(--color-text-muted)]">{icon}{label}</div>
            <div className="text-2xl font-bold text-[var(--color-text)] mt-1.5 tabular-nums">{value}</div>
        </div>
    );
}
