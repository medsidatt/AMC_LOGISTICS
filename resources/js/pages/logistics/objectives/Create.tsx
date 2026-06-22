import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import FormInput from '@/components/ui/FormInput';
import FormTextarea from '@/components/ui/FormTextarea';
import { usePermission } from '@/hooks/usePermission';
import type { PlanningMode } from '@/types/achievement';
import { BedDouble, Check, AlertTriangle, Sparkles, Target, ArrowLeft } from 'lucide-react';
import { clsx } from 'clsx';

interface TruckRow {
    id: number;
    matricule: string;
    is_available: boolean;
    capacity_tonnage: number;
    target_weekly_capacity_t: number;
    empirical_weekly_capacity_t: number;
}

interface Props {
    editing: { id: number } | null;
    periodTypes: PlanningMode[];
    period: { type: PlanningMode; start: string; end: string };
    targetTons: number;
    notes: string;
    planCapacityT: number;
    trucks: TruckRow[];
    restedTruckIds: number[];
}

const fmt = (n: number) => Math.round(n).toLocaleString('fr-FR');
const TYPE_LABEL: Record<PlanningMode, string> = { WEEK: 'Semaine', MONTH: 'Mois', YEAR: 'Année', CUSTOM: 'Personnalisé' };

const ymd = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
const mondayOf = (base: Date) => { const d = new Date(base); d.setDate(d.getDate() - ((d.getDay() + 6) % 7)); return d; };

/** Canonical range for a (type, anchor) — mirrors PlanningPeriodResolver server-side. */
function rangeFor(type: PlanningMode, anchorIso: string, endIso: string): [string, string] {
    const a = new Date(anchorIso + 'T00:00:00');
    if (type === 'WEEK') { const mon = mondayOf(a); const sat = new Date(mon); sat.setDate(mon.getDate() + 5); return [ymd(mon), ymd(sat)]; }
    if (type === 'MONTH') return [ymd(new Date(a.getFullYear(), a.getMonth(), 1)), ymd(new Date(a.getFullYear(), a.getMonth() + 1, 0))];
    if (type === 'YEAR') return [ymd(new Date(a.getFullYear(), 0, 1)), ymd(new Date(a.getFullYear(), 11, 31))];
    return [anchorIso, endIso || anchorIso];
}

const daysBetween = (s: string, e: string) =>
    Math.round((new Date(e + 'T00:00:00').getTime() - new Date(s + 'T00:00:00').getTime()) / 86400000) + 1;

/**
 * Mirror of FleetCapacityService::distributeTargetRotations (live preview = server).
 * Rotations proportional to per-truck capacity: rot_i = round(target × cap_i / Σcap²).
 */
function distribute(targetTons: number, working: TruckRow[], defaultCap: number): Map<number, number> {
    const map = new Map<number, number>();
    if (working.length === 0 || targetTons <= 0) { working.forEach((t) => map.set(t.id, 0)); return map; }
    const capOf = (t: TruckRow) => (t.capacity_tonnage > 0 ? t.capacity_tonnage : Math.max(0.01, defaultCap));
    const sumCapSq = working.reduce((s, t) => { const c = capOf(t); return s + c * c; }, 0);
    if (sumCapSq <= 0) { working.forEach((t) => map.set(t.id, 0)); return map; }
    const k = targetTons / sumCapSq;
    working.forEach((t) => map.set(t.id, Math.max(0, Math.round(k * capOf(t)))));
    return map;
}

export default function ObjectiveAuthoring({ editing, periodTypes, period, targetTons, notes: initialNotes, planCapacityT, trucks, restedTruckIds }: Props) {
    const { can } = usePermission();
    const canEdit = can('fleet-roster-plan');

    const [type, setType] = useState<PlanningMode>(period.type);
    const [anchor, setAnchor] = useState(period.start);
    const [customEnd, setCustomEnd] = useState(period.end);
    const [target, setTarget] = useState(String(targetTons || ''));
    const [notes, setNotes] = useState(initialNotes ?? '');
    const [rested, setRested] = useState<Set<number>>(new Set(restedTruckIds));
    const [saving, setSaving] = useState(false);

    const [start, end] = useMemo(() => rangeFor(type, anchor, customEnd), [type, anchor, customEnd]);
    const weeks = useMemo(() => Math.max(1, daysBetween(start, end) / 7), [start, end]);

    const toggle = (id: number) => {
        if (!canEdit) return;
        setRested((prev) => { const next = new Set(prev); next.has(id) ? next.delete(id) : next.add(id); return next; });
    };

    const workingTrucks = trucks.filter((t) => !rested.has(t.id) && t.is_available);
    const targetNum = Number(target) || 0;
    const workingCapacity = workingTrucks.reduce((s, t) => s + t.target_weekly_capacity_t * weeks, 0);
    const isCovered = targetNum <= 0 || workingCapacity >= targetNum;

    const dist = useMemo(() => distribute(targetNum, workingTrucks, planCapacityT), [targetNum, workingTrucks, planCapacityT]);
    const plannedRotations = useMemo(() => [...dist.values()].reduce((s, r) => s + r, 0), [dist]);
    const plannedTons = useMemo(
        () => workingTrucks.reduce((s, t) => s + (dist.get(t.id) ?? 0) * (t.capacity_tonnage > 0 ? t.capacity_tonnage : planCapacityT), 0),
        [dist, workingTrucks, planCapacityT],
    );

    const autoSuggest = () => {
        const totalCap = trucks.reduce((s, t) => s + t.target_weekly_capacity_t * weeks, 0);
        const avg = trucks.length ? totalCap / trucks.length : 0;
        const minTrucks = avg > 0 && targetNum > 0 ? Math.max(1, Math.min(Math.ceil(targetNum / avg), trucks.length)) : trucks.length;
        const sorted = [...trucks].sort((a, b) => {
            const ea = a.empirical_weekly_capacity_t > 0 ? a.empirical_weekly_capacity_t : a.target_weekly_capacity_t;
            const eb = b.empirical_weekly_capacity_t > 0 ? b.empirical_weekly_capacity_t : b.target_weekly_capacity_t;
            return eb - ea;
        });
        const work = new Set(sorted.slice(0, minTrucks).map((t) => t.id));
        setRested(new Set(trucks.filter((t) => !work.has(t.id)).map((t) => t.id)));
    };

    const save = () => {
        setSaving(true);
        router.post('/logistics/objectives', {
            period_type: type,
            start_date: type === 'CUSTOM' ? start : anchor,
            end_date: type === 'CUSTOM' ? end : null,
            target_tons: targetNum,
            notes,
            rested_truck_ids: [...rested],
        }, { onFinish: () => setSaving(false) });
    };

    return (
        <AuthenticatedLayout title={editing ? 'Modifier l’objectif' : 'Nouvel objectif'}>
            <Head title={editing ? 'Modifier l’objectif' : 'Nouvel objectif'} />
            <div className="space-y-5 max-w-5xl">
                <div className="flex items-center justify-between gap-3 flex-wrap">
                    <div className="flex items-center gap-2">
                        <Target size={22} className="text-[var(--color-primary)]" />
                        <h1 className="text-xl font-semibold">{editing ? 'Modifier l’objectif' : 'Nouvel objectif'}</h1>
                    </div>
                    <Button variant="secondary" onClick={() => router.visit('/logistics/objectives')}>
                        <ArrowLeft size={14} className="mr-1" /> Objectifs
                    </Button>
                </div>

                {/* Période + cible */}
                <Card>
                    <div className="inline-flex rounded-lg border border-[var(--color-border)] p-0.5 bg-[var(--color-surface)] mb-4">
                        {periodTypes.map((m) => (
                            <button
                                key={m}
                                type="button"
                                disabled={!!editing}
                                aria-pressed={type === m}
                                onClick={() => setType(m)}
                                className={clsx(
                                    'px-3 py-1.5 text-sm font-medium rounded-md transition-colors',
                                    editing && 'opacity-50 cursor-not-allowed',
                                    type === m ? 'bg-[var(--color-primary)] text-white' : 'text-[var(--color-text-secondary)] hover:bg-[var(--color-surface-hover)] cursor-pointer',
                                )}
                            >
                                {TYPE_LABEL[m]}
                            </button>
                        ))}
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
                        <FormInput
                            label={type === 'CUSTOM' ? 'Date de début' : 'Date de référence'}
                            type="date" value={anchor} onChange={(e) => setAnchor(e.target.value)}
                            disabled={!!editing} wrapperClass="mb-0"
                        />
                        {type === 'CUSTOM' ? (
                            <FormInput label="Date de fin" type="date" value={customEnd} onChange={(e) => setCustomEnd(e.target.value)} disabled={!!editing} wrapperClass="mb-0" />
                        ) : (
                            <div className="text-sm text-[var(--color-text-muted)] pb-2">Période : <strong>{start} → {end}</strong></div>
                        )}
                        <FormInput label="Objectif tonnage (t)" type="number" step="0.1" min="0" value={target} onChange={(e) => setTarget(e.target.value)} wrapperClass="mb-0" />
                    </div>
                </Card>

                {/* Statut de couverture */}
                {targetNum > 0 && (
                    <div className={clsx(
                        'rounded-xl border p-4 flex items-center justify-between flex-wrap gap-3',
                        isCovered ? 'border-emerald-300 dark:border-emerald-900/40 bg-emerald-50 dark:bg-emerald-900/10'
                            : 'border-red-300 dark:border-red-900/40 bg-red-50 dark:bg-red-900/10',
                    )}>
                        <div className="flex items-center gap-2">
                            {isCovered ? <Check size={18} className="text-emerald-600 dark:text-emerald-400" /> : <AlertTriangle size={18} className="text-red-600 dark:text-red-400" />}
                            <span className={clsx('font-semibold', isCovered ? 'text-emerald-700 dark:text-emerald-300' : 'text-red-700 dark:text-red-300')}>
                                {isCovered ? 'Objectif couvert par la sélection' : 'Capacité insuffisante — remettez des camions en service'}
                            </span>
                        </div>
                        <div className="text-sm text-[var(--color-text-secondary)]">
                            <strong>{fmt(plannedRotations)}</strong> rotations ≈ <strong>{fmt(plannedTons)} t</strong> · {workingTrucks.length} au travail · {rested.size} au repos
                            <span className="text-[var(--color-text-muted)]"> · {fmt(planCapacityT)} t/rotation</span>
                        </div>
                    </div>
                )}

                {/* Sélection des camions */}
                <Card>
                    {canEdit && (
                        <div className="flex items-center gap-3 flex-wrap mb-4">
                            <Button size="sm" variant="secondary" onClick={autoSuggest}><Sparkles size={14} className="mr-1" /> Suggestion automatique</Button>
                            <button type="button" onClick={() => setRested(new Set())} className="text-sm text-[var(--color-primary)] hover:underline">Tous au travail</button>
                            <span className="text-[var(--color-text-muted)]">·</span>
                            <button type="button" onClick={() => setRested(new Set(trucks.map((t) => t.id)))} className="text-sm text-[var(--color-primary)] hover:underline">Tous au repos</button>
                        </div>
                    )}
                    <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
                        {trucks.map((t) => {
                            const unavailable = !t.is_available;
                            const resting = rested.has(t.id);
                            return (
                                <button
                                    key={t.id} type="button" onClick={() => toggle(t.id)} disabled={!canEdit || unavailable}
                                    title={unavailable ? 'Camion indisponible — réactivez-le depuis sa fiche.' : undefined}
                                    className={clsx(
                                        'flex items-center justify-between gap-2 px-3 py-2.5 rounded-xl border text-left transition',
                                        unavailable ? 'border-red-300 dark:border-red-900/40 bg-red-50 dark:bg-red-900/10 opacity-70 cursor-not-allowed'
                                            : resting ? 'border-amber-300 dark:border-amber-900/40 bg-amber-50 dark:bg-amber-900/10'
                                                : 'border-emerald-300 dark:border-emerald-900/40 bg-emerald-50/50 dark:bg-emerald-900/10',
                                        !unavailable && canEdit && 'hover:shadow-sm cursor-pointer',
                                    )}
                                >
                                    <span className="font-semibold text-sm truncate">{t.matricule}</span>
                                    {unavailable ? <Badge variant="danger"><AlertTriangle size={10} className="mr-1" /> Indispo.</Badge>
                                        : resting ? <Badge variant="warning"><BedDouble size={10} className="mr-1" /> Repos</Badge>
                                            : targetNum > 0 ? <Badge variant="success">{fmt(dist.get(t.id) ?? 0)} rot</Badge>
                                                : <Badge variant="success"><Check size={10} className="mr-1" /> Travail</Badge>}
                                </button>
                            );
                        })}
                    </div>
                </Card>

                {/* Note + enregistrement */}
                {canEdit && (
                    <Card>
                        <FormTextarea label="Note (optionnel)" value={notes} onChange={(e) => setNotes(e.target.value)} rows={2} maxLength={500} wrapperClass="mb-0" />
                        <div className="flex justify-end mt-3">
                            <Button onClick={save} loading={saving} disabled={!isCovered || targetNum <= 0}>
                                <Target size={14} className="mr-1" /> {editing ? 'Enregistrer les modifications' : 'Créer l’objectif'}
                            </Button>
                        </div>
                    </Card>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
