import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import FormInput from '@/components/ui/FormInput';
import FormTextarea from '@/components/ui/FormTextarea';
import { usePermission } from '@/hooks/usePermission';
import {
    Truck as TruckIcon, BedDouble, Target, Activity,
    Sparkles, Check, AlertTriangle, Calendar,
} from 'lucide-react';
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
}

function SectionLabel({ children }: { children: React.ReactNode }) {
    return (
        <div className="text-xs uppercase tracking-wider font-semibold text-[var(--color-text-muted)] mt-2 mb-1">
            {children}
        </div>
    );
}

export default function FleetRosterIndex({
    period, objective, trucks, total_capacity_t, min_trucks_needed, avg_capacity_per_truck_t, currently_rested_truck_ids,
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

    const toggle = (id: number) => {
        setRested((prev) => {
            const next = new Set(prev);
            if (next.has(id)) next.delete(id);
            else next.add(id);
            return next;
        });
    };

    const autoSuggest = () => {
        // Keep the top N performers (highest period_capacity), rest the others.
        // Sort by empirical_weekly_capacity (real productivity), fallback to target.
        const sorted = [...trucks].sort((a, b) => {
            const ea = a.empirical_weekly_capacity_t > 0 ? a.empirical_weekly_capacity_t : a.target_weekly_capacity_t;
            const eb = b.empirical_weekly_capacity_t > 0 ? b.empirical_weekly_capacity_t : b.target_weekly_capacity_t;
            return eb - ea;
        });
        const workingSet = new Set(sorted.slice(0, min_trucks_needed).map((t) => t.id));
        const restedSet = new Set(trucks.filter((t) => !workingSet.has(t.id)).map((t) => t.id));
        setRested(restedSet);
    };

    const restAll = () => setRested(new Set(trucks.map((t) => t.id)));
    const restNone = () => setRested(new Set());

    const workingTrucks = trucks.filter((t) => !rested.has(t.id));
    const workingCapacity = workingTrucks.reduce((s, t) => s + t.period_capacity_t, 0);
    const target = Number(targetTons) || 0;
    const coverage = target > 0 ? Math.min(100, (workingCapacity / target) * 100) : 0;
    const isCovered = workingCapacity >= target;

    const save = () => {
        setSaving(true);
        router.post('/logistics/fleet-roster', {
            start_date: start,
            end_date: end,
            rested_truck_ids: [...rested],
            notes,
        }, { onFinish: () => setSaving(false) });
    };

    const periodChanged = useMemo(
        () => start !== period.start || end !== period.end,
        [start, end, period.start, period.end],
    );

    return (
        <AuthenticatedLayout>
            <Head title="Planning de la flotte" />
            <div className="space-y-4">
                <div className="flex items-center gap-2 flex-wrap">
                    <BedDouble size={22} className="text-emerald-500" />
                    <h1 className="text-xl font-semibold">Planning de la flotte</h1>
                </div>

                <Card>
                    <div className="text-sm text-[var(--color-text-muted)]">
                        Choisis une période et l'objectif tonnage. L'application calcule combien de camions sont nécessaires
                        au minimum, propose ceux à garder en service et programme automatiquement un repos pour les autres.
                    </div>
                </Card>

                {/* Period + objective */}
                <SectionLabel>Période et objectif</SectionLabel>
                <Card>
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                        <FormInput
                            label="Date début"
                            type="date"
                            value={start}
                            onChange={(e) => setStart(e.target.value)}
                            wrapperClass="mb-0"
                        />
                        <FormInput
                            label="Date fin"
                            type="date"
                            value={end}
                            onChange={(e) => setEnd(e.target.value)}
                            wrapperClass="mb-0"
                        />
                        <FormInput
                            label="Objectif tonnage (t)"
                            type="number"
                            step="0.1"
                            value={targetTons}
                            onChange={(e) => setTargetTons(e.target.value)}
                            wrapperClass="mb-0"
                        />
                        <Button variant="secondary" onClick={() => goto(start, end, targetTons)}>
                            <Calendar size={14} className="mr-1" /> Appliquer la période
                        </Button>
                    </div>
                    <p className="text-xs text-[var(--color-text-muted)] mt-2">
                        Cible par défaut sur la période : <strong>{objective.default_target_tons.toLocaleString('fr-FR', { maximumFractionDigits: 0 })} t</strong> ({objective.weekly_target_tons.toLocaleString('fr-FR', { maximumFractionDigits: 0 })} t/semaine × {period.weeks} semaines).
                        Tu peux la modifier ci-dessus.
                    </p>
                </Card>

                {periodChanged && (
                    <div className="rounded-xl border border-amber-300 dark:border-amber-900/40 bg-amber-50 dark:bg-amber-900/10 p-3 text-sm text-amber-800 dark:text-amber-300 flex items-center gap-2">
                        <AlertTriangle size={14} /> Période modifiée — clique "Appliquer la période" pour recalculer les capacités.
                    </div>
                )}

                {/* Math summary */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <Card>
                        <div className="flex items-center gap-3">
                            <div className="p-2.5 rounded-lg bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400"><Target size={18} /></div>
                            <div className="min-w-0">
                                <div className="text-xs uppercase text-[var(--color-text-muted)]">Objectif période</div>
                                <div className="text-2xl font-bold leading-tight">{target.toLocaleString('fr-FR', { maximumFractionDigits: 0 })}<span className="text-sm font-normal ml-1">t</span></div>
                                <div className="text-xs text-[var(--color-text-muted)]">{period.days} jours</div>
                            </div>
                        </div>
                    </Card>
                    <Card>
                        <div className="flex items-center gap-3">
                            <div className="p-2.5 rounded-lg bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300"><TruckIcon size={18} /></div>
                            <div className="min-w-0">
                                <div className="text-xs uppercase text-[var(--color-text-muted)]">Capacité totale flotte</div>
                                <div className="text-2xl font-bold leading-tight">{total_capacity_t.toLocaleString('fr-FR', { maximumFractionDigits: 0 })}<span className="text-sm font-normal ml-1">t</span></div>
                                <div className="text-xs text-[var(--color-text-muted)]">{trucks.length} camions × {avg_capacity_per_truck_t.toFixed(0)} t en moyenne</div>
                            </div>
                        </div>
                    </Card>
                    <Card>
                        <div className="flex items-center gap-3">
                            <div className="p-2.5 rounded-lg bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400"><Sparkles size={18} /></div>
                            <div className="min-w-0">
                                <div className="text-xs uppercase text-[var(--color-text-muted)]">Camions nécessaires (min)</div>
                                <div className="text-2xl font-bold leading-tight">{min_trucks_needed}</div>
                                <div className="text-xs text-[var(--color-text-muted)]">{trucks.length - min_trucks_needed} au repos possible</div>
                            </div>
                        </div>
                    </Card>
                    <Card>
                        <div className="flex items-center gap-3">
                            <div className={`p-2.5 rounded-lg ${isCovered ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400'}`}>
                                <Activity size={18} />
                            </div>
                            <div className="min-w-0">
                                <div className="text-xs uppercase text-[var(--color-text-muted)]">Couverture sélection</div>
                                <div className="text-2xl font-bold leading-tight">{coverage.toFixed(0)}<span className="text-sm font-normal">%</span></div>
                                <div className="text-xs text-[var(--color-text-muted)]">
                                    {workingCapacity.toLocaleString('fr-FR', { maximumFractionDigits: 0 })} / {target.toLocaleString('fr-FR', { maximumFractionDigits: 0 })} t
                                </div>
                            </div>
                        </div>
                    </Card>
                </div>

                {!isCovered && target > 0 && (
                    <div className="rounded-xl border border-red-300 dark:border-red-900/40 bg-red-50 dark:bg-red-900/10 p-3 text-sm text-red-800 dark:text-red-300 flex items-center gap-2">
                        <AlertTriangle size={14} />
                        Capacité insuffisante : il manque {(target - workingCapacity).toLocaleString('fr-FR', { maximumFractionDigits: 0 })} t. Ajoute des camions en service.
                    </div>
                )}

                {/* Truck checklist */}
                <SectionLabel>Sélection des camions</SectionLabel>
                <Card>
                    <div className="flex items-center justify-between flex-wrap gap-3 mb-3">
                        <div className="flex items-center gap-2 text-sm">
                            {canEdit && (
                                <>
                                    <Button size="sm" variant="secondary" onClick={autoSuggest}>
                                        <Sparkles size={14} className="mr-1" /> Suggérer ({min_trucks_needed} camions)
                                    </Button>
                                    <button type="button" onClick={restNone} className="text-[var(--color-primary)] hover:underline">Tous au travail</button>
                                    <span className="text-[var(--color-text-muted)]">·</span>
                                    <button type="button" onClick={restAll} className="text-[var(--color-primary)] hover:underline">Tous au repos</button>
                                </>
                            )}
                        </div>
                        <div className="text-sm">
                            <span className="text-[var(--color-text-muted)]">Au travail : </span>
                            <strong>{workingTrucks.length}</strong>
                            <span className="text-[var(--color-text-muted)]"> · Au repos : </span>
                            <strong>{rested.size}</strong>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                        {trucks.map((t) => {
                            const isResting = rested.has(t.id);
                            return (
                                <button
                                    key={t.id}
                                    type="button"
                                    onClick={() => canEdit && toggle(t.id)}
                                    disabled={!canEdit}
                                    className={clsx(
                                        'p-3 rounded-xl border-2 text-left transition',
                                        isResting
                                            ? 'border-amber-400 bg-amber-50 dark:bg-amber-900/10'
                                            : 'border-emerald-400 bg-emerald-50/40 dark:bg-emerald-900/10',
                                        !canEdit && 'opacity-70 cursor-not-allowed',
                                    )}
                                >
                                    <div className="flex items-center justify-between gap-2 mb-1">
                                        <div className="font-semibold text-sm">{t.matricule}</div>
                                        {isResting ? (
                                            <Badge variant="warning"><BedDouble size={10} className="mr-1" /> Repos</Badge>
                                        ) : (
                                            <Badge variant="success"><Check size={10} className="mr-1" /> Travail</Badge>
                                        )}
                                    </div>
                                    <div className="text-xs text-[var(--color-text-muted)]">
                                        {t.target_rotations_per_week} rot/sem × {t.capacity_tonnage.toFixed(0)} t = <strong className="text-[var(--color-text)]">{t.target_weekly_capacity_t.toFixed(0)} t/sem</strong>
                                    </div>
                                    <div className="text-xs text-[var(--color-text-muted)]">
                                        Sur la période : <strong className="text-[var(--color-text)]">{t.period_capacity_t.toLocaleString('fr-FR', { maximumFractionDigits: 0 })} t</strong>
                                        {t.empirical_weekly_capacity_t > 0 && (
                                            <span> · Réel ~ {(t.empirical_weekly_capacity_t * period.weeks).toLocaleString('fr-FR', { maximumFractionDigits: 0 })} t</span>
                                        )}
                                    </div>
                                </button>
                            );
                        })}
                    </div>
                </Card>

                {/* Notes + save */}
                {canEdit && (
                    <Card>
                        <FormTextarea
                            label="Note (optionnel) — ajoutée à chaque fenêtre de repos créée"
                            value={notes}
                            onChange={(e) => setNotes(e.target.value)}
                            rows={2}
                            maxLength={500}
                        />
                        <div className="flex justify-end gap-2 mt-3">
                            <Button onClick={save} loading={saving} disabled={target > 0 && !isCovered}>
                                <BedDouble size={14} className="mr-1" />
                                Programmer le repos de {rested.size} camion{rested.size > 1 ? 's' : ''}
                            </Button>
                        </div>
                        <p className="text-xs text-[var(--color-text-muted)] mt-2">
                            Les fenêtres de repos précédentes pour la même période (source : capacité excédentaire) seront remplacées par cette sélection.
                        </p>
                    </Card>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
