import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import FormInput from '@/components/ui/FormInput';
import FormTextarea from '@/components/ui/FormTextarea';
import { usePermission } from '@/hooks/usePermission';
import AchievementSummary from '@/components/logistics/AchievementSummary';
import type { Achievement } from '@/types/achievement';
import { BedDouble, Check, AlertTriangle, Calendar, Sparkles, History, Trophy } from 'lucide-react';
import { clsx } from 'clsx';

interface TruckRow {
    id: number;
    matricule: string;
    capacity_tonnage: number;
    target_rotations_per_week: number;
    target_weekly_capacity_t: number;
    period_capacity_t: number;
    avg_rotations_per_week: number;
    empirical_weekly_capacity_t: number;
}

interface Props {
    period: { start: string; end: string; days: number; weeks: number };
    objective: {
        target_tons: number;
        default_target_tons: number;
        weekly_target_tons: number;
        source: 'default' | 'mixed' | 'client_demand';
    };
    trucks: TruckRow[];
    total_capacity_t: number;
    min_trucks_needed: number;
    avg_capacity_per_truck_t: number;
    currently_rested_truck_ids: number[];
    achievement: Achievement;
}

const fmt = (n: number) => Math.round(n).toLocaleString('fr-FR');

type Dist = Map<number, { rotations: number; tons: number }>;

/**
 * Mirror of FleetCapacityService::distributeTargetRotations — keeps the live
 * preview identical to what the server will store. Total rotations =
 * round(target / average capacity), spread evenly, leftover to the
 * highest-capacity trucks; per-truck tonnage = rotations × own capacity.
 */
function distribute(targetTons: number, working: TruckRow[]): Dist {
    const map: Dist = new Map();
    const n = working.length;
    if (n === 0 || targetTons <= 0) {
        working.forEach((t) => map.set(t.id, { rotations: 0, tons: 0 }));
        return map;
    }
    const cap = (t: TruckRow) => Math.max(0.01, t.capacity_tonnage || 0);
    const avg = Math.max(0.01, working.reduce((s, t) => s + cap(t), 0) / n);
    const total = Math.max(0, Math.round(targetTons / avg));
    const base = Math.floor(total / n);
    const remainder = total % n;
    const order = [...working].sort((a, b) => cap(b) - cap(a));
    const extra = new Map<number, number>();
    order.forEach((t, i) => extra.set(t.id, i < remainder ? 1 : 0));
    working.forEach((t) => {
        const rot = base + (extra.get(t.id) ?? 0);
        map.set(t.id, { rotations: rot, tons: Math.round(rot * cap(t)) });
    });
    return map;
}

export default function FleetRosterIndex({
    period, objective, trucks, min_trucks_needed, currently_rested_truck_ids, achievement,
}: Props) {
    const { can } = usePermission();
    const canEdit = can('fleet-roster-plan');

    // resting truck IDs (mutable). Default = whatever is already saved.
    const [rested, setRested] = useState<Set<number>>(new Set(currently_rested_truck_ids));
    const [start, setStart] = useState(period.start);
    const [end, setEnd] = useState(period.end);
    const [targetTons, setTargetTons] = useState(String(objective.target_tons));
    const [notes, setNotes] = useState('');
    const [saving, setSaving] = useState(false);

    const goto = (s: string, e: string, t?: string) => {
        router.get('/logistics/fleet-roster', {
            start: s, end: e, ...(t ? { target_tons: t } : {}),
        }, { preserveState: false });
    };

    // "Appliquer" persists the objective for the period+target, then reloads.
    const applyObjective = () => {
        router.post('/logistics/fleet-roster/apply', {
            start_date: start,
            end_date: end,
            target_tons: Number(targetTons) || 0,
        }, { preserveScroll: true });
    };

    // Quick period presets (local-date safe).
    const ymd = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    const mondayOf = (base: Date) => {
        const d = new Date(base);
        d.setDate(d.getDate() - ((d.getDay() + 6) % 7));
        return d;
    };
    const weekRange = (offsetWeeks: number): [string, string] => {
        const mon = mondayOf(new Date());
        mon.setDate(mon.getDate() + offsetWeeks * 7);
        const sat = new Date(mon);
        sat.setDate(mon.getDate() + 5);
        return [ymd(mon), ymd(sat)];
    };
    const monthRange = (): [string, string] => {
        const n = new Date();
        return [ymd(new Date(n.getFullYear(), n.getMonth(), 1)), ymd(new Date(n.getFullYear(), n.getMonth() + 1, 0))];
    };
    const presets: { label: string; range: () => [string, string] }[] = [
        { label: 'Cette semaine', range: () => weekRange(0) },
        { label: 'Semaine prochaine', range: () => weekRange(1) },
        { label: 'Ce mois-ci', range: monthRange },
    ];
    const isActivePreset = (range: () => [string, string]) => {
        const [s, e] = range();
        return start === s && end === e;
    };

    const toggle = (id: number) => {
        if (!canEdit) return;
        setRested((prev) => {
            const next = new Set(prev);
            if (next.has(id)) next.delete(id);
            else next.add(id);
            return next;
        });
    };

    const autoSuggest = () => {
        // Keep the most productive trucks in service, rest the rest.
        const sorted = [...trucks].sort((a, b) => {
            const ea = a.empirical_weekly_capacity_t > 0 ? a.empirical_weekly_capacity_t : a.target_weekly_capacity_t;
            const eb = b.empirical_weekly_capacity_t > 0 ? b.empirical_weekly_capacity_t : b.target_weekly_capacity_t;
            return eb - ea;
        });
        const workingSet = new Set(sorted.slice(0, min_trucks_needed).map((t) => t.id));
        setRested(new Set(trucks.filter((t) => !workingSet.has(t.id)).map((t) => t.id)));
    };

    const restAll = () => setRested(new Set(trucks.map((t) => t.id)));
    const restNone = () => setRested(new Set());

    // Internal capacity check — drives the covered/insufficient status only.
    // No raw tonnage figures are shown to the user.
    const workingTrucks = trucks.filter((t) => !rested.has(t.id));
    const workingCapacity = workingTrucks.reduce((s, t) => s + t.period_capacity_t, 0);
    const target = Number(targetTons) || 0;
    const isCovered = target <= 0 || workingCapacity >= target;

    // Live top-down distribution: spreads the tonnage target across the trucks
    // in service, recomputing instantly whenever one is put to rest.
    const dist = useMemo(() => distribute(target, workingTrucks), [target, workingTrucks]);
    const plannedRotations = useMemo(() => [...dist.values()].reduce((s, d) => s + d.rotations, 0), [dist]);
    const plannedTons = useMemo(() => [...dist.values()].reduce((s, d) => s + d.tons, 0), [dist]);

    // The saved plan was built for this set of working trucks; if the selection
    // changed, the objective will be redistributed on save.
    const savedRested = useMemo(() => new Set(currently_rested_truck_ids), [currently_rested_truck_ids]);
    const selectionChanged = useMemo(
        () => rested.size !== savedRested.size || [...rested].some((id) => !savedRested.has(id)),
        [rested, savedRested],
    );

    const save = () => {
        setSaving(true);
        router.post('/logistics/fleet-roster', {
            start_date: start,
            end_date: end,
            rested_truck_ids: [...rested],
            notes,
            target_tons: target,
        }, { onFinish: () => setSaving(false) });
    };

    const periodChanged = useMemo(
        () => start !== period.start || end !== period.end,
        [start, end, period.start, period.end],
    );

    return (
        <AuthenticatedLayout>
            <Head title="Planning de la flotte" />
            <div className="space-y-5">
                <div className="flex items-start justify-between gap-3 flex-wrap">
                    <div>
                        <div className="flex items-center gap-2">
                            <BedDouble size={22} className="text-emerald-500" />
                            <h1 className="text-xl font-semibold">Planning de la flotte</h1>
                        </div>
                        <p className="text-sm text-[var(--color-text-muted)] mt-1">
                            Sélectionnez les camions en service pour la période. Les autres seront mis au repos.
                        </p>
                    </div>
                    <Button variant="secondary" onClick={() => router.visit('/logistics/fleet-roster/history')}>
                        <History size={14} className="mr-1" /> Historique des objectifs
                    </Button>
                </div>

                {/* Période et objectif */}
                <Card>
                    <div className="flex flex-wrap gap-2 mb-4">
                        {presets.map((p) => {
                            const active = isActivePreset(p.range);
                            return (
                                <button
                                    key={p.label}
                                    type="button"
                                    onClick={() => { const [s, e] = p.range(); goto(s, e); }}
                                    className={clsx(
                                        'px-3 py-1.5 rounded-full text-xs font-medium border transition',
                                        active
                                            ? 'bg-[var(--color-primary)] text-white border-[var(--color-primary)]'
                                            : 'border-[var(--color-border)] text-[var(--color-text-secondary)] hover:bg-[var(--color-surface-hover)]',
                                    )}
                                >
                                    {p.label}
                                </button>
                            );
                        })}
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                        <FormInput label="Date début" type="date" value={start} onChange={(e) => setStart(e.target.value)} wrapperClass="mb-0" />
                        <FormInput label="Date fin" type="date" value={end} onChange={(e) => setEnd(e.target.value)} wrapperClass="mb-0" />
                        <FormInput label="Objectif tonnage (t)" type="number" step="0.1" value={targetTons} onChange={(e) => setTargetTons(e.target.value)} wrapperClass="mb-0" />
                        <Button variant="secondary" onClick={applyObjective}>
                            <Calendar size={14} className="mr-1" /> Appliquer
                        </Button>
                    </div>
                </Card>

                {periodChanged && (
                    <div className="rounded-xl border border-amber-300 dark:border-amber-900/40 bg-amber-50 dark:bg-amber-900/10 p-3 text-sm text-amber-800 dark:text-amber-300 flex items-center gap-2">
                        <AlertTriangle size={14} /> Période modifiée — cliquez « Appliquer » pour rafraîchir.
                    </div>
                )}

                {/* Status — result only, no figures */}
                {target > 0 && (
                    <div className={clsx(
                        'rounded-xl border p-4 flex items-center justify-between flex-wrap gap-3',
                        isCovered
                            ? 'border-emerald-300 dark:border-emerald-900/40 bg-emerald-50 dark:bg-emerald-900/10'
                            : 'border-red-300 dark:border-red-900/40 bg-red-50 dark:bg-red-900/10',
                    )}>
                        <div className="flex items-center gap-2">
                            {isCovered
                                ? <Check size={18} className="text-emerald-600 dark:text-emerald-400" />
                                : <AlertTriangle size={18} className="text-red-600 dark:text-red-400" />}
                            <span className={clsx('font-semibold', isCovered ? 'text-emerald-700 dark:text-emerald-300' : 'text-red-700 dark:text-red-300')}>
                                {isCovered ? 'Objectif couvert par la sélection' : 'Capacité insuffisante — ajoutez des camions en service'}
                            </span>
                        </div>
                        <div className="text-sm text-[var(--color-text-secondary)]">
                            <strong>{workingTrucks.length}</strong> au travail · <strong>{rested.size}</strong> au repos · {trucks.length} camions
                        </div>
                    </div>
                )}

                {/* Répartition prévue (live) */}
                {target > 0 && workingTrucks.length > 0 && (
                    <div className={clsx(
                        'rounded-xl border p-4',
                        selectionChanged
                            ? 'border-amber-300 dark:border-amber-900/40 bg-amber-50 dark:bg-amber-900/10'
                            : 'border-[var(--color-border)] bg-[var(--color-surface)]',
                    )}>
                        <div className="flex items-center justify-between gap-3 flex-wrap">
                            <div className="flex items-center gap-2">
                                {selectionChanged && <AlertTriangle size={16} className="text-amber-600 dark:text-amber-400" />}
                                <span className="font-semibold">Répartition prévue</span>
                            </div>
                            <div className="text-sm text-[var(--color-text-secondary)]">
                                <strong>{fmt(plannedRotations)}</strong> rotations ≈ <strong>{fmt(plannedTons)} t</strong> sur <strong>{workingTrucks.length}</strong> camion{workingTrucks.length > 1 ? 's' : ''}
                            </div>
                        </div>
                        {selectionChanged && (
                            <p className="text-sm text-amber-800 dark:text-amber-300 mt-2">
                                La sélection des camions a changé — l'objectif sera redistribué sur les camions en service à l'enregistrement.
                            </p>
                        )}
                    </div>
                )}

                {/* Réalisation de l'objectif */}
                {achievement.has_objective && (
                    <Card>
                        <div className="flex items-center justify-between gap-2 flex-wrap mb-4">
                            <h2 className="text-base font-semibold flex items-center gap-2"><Trophy size={18} className="text-[var(--color-primary)]" /> Réalisation de l'objectif</h2>
                            <a href="/logistics/fleet-roster/history" className="text-sm text-[var(--color-primary)] hover:underline inline-flex items-center gap-1"><History size={14} /> Historique</a>
                        </div>
                        <AchievementSummary fleet={achievement.fleet} projection={achievement.projection} gpsAvailable={achievement.gps_available} />

                        {achievement.per_truck.length > 0 && (
                            <div className="mt-5 overflow-x-auto rounded-lg border border-[var(--color-border)]">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="bg-[var(--color-surface-hover)] text-[11px] uppercase tracking-wide text-[var(--color-text-secondary)]">
                                            <th className="px-3 py-2 text-left font-semibold">Camion</th>
                                            <th className="px-3 py-2 text-right font-semibold">Objectif</th>
                                            <th className="px-3 py-2 text-right font-semibold">Réalisé</th>
                                            <th className="px-3 py-2 text-right font-semibold">Restant</th>
                                            <th className="px-3 py-2 text-right font-semibold">%</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-[var(--color-border)]">
                                        {achievement.per_truck.map((t) => (
                                            <tr key={t.truck_id}>
                                                <td className="px-3 py-2 font-medium">{t.matricule}</td>
                                                <td className="px-3 py-2 text-right font-mono">{fmt(t.target_rotations)} rot</td>
                                                <td className="px-3 py-2 text-right font-mono">
                                                    {fmt(t.done_rotations)} rot
                                                    {t.missing_tickets > 0 && <Badge variant="warning" className="ml-1">{t.missing_tickets} ticket manquant</Badge>}
                                                </td>
                                                <td className="px-3 py-2 text-right font-mono">{fmt(t.remaining_rotations)} rot</td>
                                                <td className="px-3 py-2 text-right font-mono font-semibold">{t.pct ?? '—'}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </Card>
                )}

                {/* Sélection des camions */}
                <Card>
                    {canEdit && (
                        <div className="flex items-center gap-2 flex-wrap mb-4">
                            <Button size="sm" variant="secondary" onClick={autoSuggest}>
                                <Sparkles size={14} className="mr-1" /> Suggestion automatique
                            </Button>
                            <button type="button" onClick={restNone} className="text-sm text-[var(--color-primary)] hover:underline">Tous au travail</button>
                            <span className="text-[var(--color-text-muted)]">·</span>
                            <button type="button" onClick={restAll} className="text-sm text-[var(--color-primary)] hover:underline">Tous au repos</button>
                        </div>
                    )}

                    <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
                        {trucks.map((t) => {
                            const isResting = rested.has(t.id);
                            return (
                                <button
                                    key={t.id}
                                    type="button"
                                    onClick={() => toggle(t.id)}
                                    disabled={!canEdit}
                                    className={clsx(
                                        'flex items-center justify-between gap-2 px-3 py-2.5 rounded-xl border text-left transition',
                                        isResting
                                            ? 'border-amber-300 dark:border-amber-900/40 bg-amber-50 dark:bg-amber-900/10'
                                            : 'border-emerald-300 dark:border-emerald-900/40 bg-emerald-50/50 dark:bg-emerald-900/10',
                                        canEdit ? 'hover:shadow-sm' : 'opacity-80 cursor-not-allowed',
                                    )}
                                >
                                    <span className="font-semibold text-sm truncate">{t.matricule}</span>
                                    {isResting ? (
                                        <Badge variant="warning"><BedDouble size={10} className="mr-1" /> Repos</Badge>
                                    ) : target > 0 ? (
                                        <Badge variant="success">{fmt(dist.get(t.id)?.rotations ?? 0)} rot</Badge>
                                    ) : (
                                        <Badge variant="success"><Check size={10} className="mr-1" /> Travail</Badge>
                                    )}
                                </button>
                            );
                        })}
                    </div>
                </Card>

                {/* Note + enregistrement */}
                {canEdit && (
                    <Card>
                        <FormTextarea
                            label="Note (optionnel)"
                            value={notes}
                            onChange={(e) => setNotes(e.target.value)}
                            rows={2}
                            maxLength={500}
                            wrapperClass="mb-0"
                        />
                        <div className="flex justify-end mt-3">
                            <Button onClick={save} loading={saving} disabled={!isCovered}>
                                <BedDouble size={14} className="mr-1" />
                                Programmer le repos de {rested.size} camion{rested.size > 1 ? 's' : ''}
                            </Button>
                        </div>
                    </Card>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
