import { Head, router } from '@inertiajs/react';
import { useMemo, useState, type ReactNode } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import ConfirmDialog from '@/components/ui/ConfirmDialog';
import FormInput from '@/components/ui/FormInput';
import { usePermission } from '@/hooks/usePermission';
import type { PlanningMode } from '@/types/achievement';
import { Target, ArrowLeft } from 'lucide-react';
import { clsx } from 'clsx';

interface PreviousPeriod {
    start: string;
    end: string;
    target_tons: number;
    done_tons: number;
    pct: number | null;
}

interface Props {
    editing: { id: number } | null;
    periodTypes: PlanningMode[];
    period: { type: PlanningMode; start: string; end: string };
    targetTons: number;
    notes: string;
    planCapacityT: number;
    fleetWeeklyCapacityT: number;
    availableTruckCount: number;
    previousPeriod: PreviousPeriod | null;
}

const fmt = (n: number) => Math.round(n).toLocaleString('fr-FR');
const TYPE_LABEL: Record<PlanningMode, string> = { WEEK: 'Semaine', MONTH: 'Mois', YEAR: 'Année', CUSTOM: 'Personnalisé' };

// Uniform label style (matches FormInput) and one touch-compliant control height.
const LABEL = 'block text-xs font-medium text-[var(--color-text-secondary)] mb-1.5';
const CONTROL_H = 'h-11'; // 44px — minimum touch target

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
 * Objective authoring — the COMMITMENT only: period + target tonnage (+ notes).
 *
 * Responsive, mobile-first. Desktop: two columns (form left, context rail right).
 * Mobile (single column) order is Target → Period → Capacity → Previous → Notes →
 * Save, so the primary action (target entry) leads and capacity context is seen
 * immediately. Achieved with a 4-card grid using `order` (mobile) + `row/col-start`
 * (desktop) and one internal order-swap for Period/Target.
 *
 * Capacity is reported as business metrics (Volume réalisable / Couverture / Volume
 * non couvert) — never emotional labels — and never blocks saving. Truck allocation
 * lives in the Planning workspace.
 */
export default function ObjectiveAuthoring({ editing, periodTypes, period, targetTons, notes: initialNotes, planCapacityT, fleetWeeklyCapacityT, availableTruckCount, previousPeriod }: Props) {
    const { can } = usePermission();
    const canEdit = can('fleet-roster-plan');

    const [type, setType] = useState<PlanningMode>(period.type);
    const [anchor, setAnchor] = useState(period.start);
    const [customEnd, setCustomEnd] = useState(period.end);
    const [target, setTarget] = useState(String(targetTons || ''));
    const [notes, setNotes] = useState(initialNotes ?? '');
    const [saving, setSaving] = useState(false);
    const [conflict, setConflict] = useState<{ existing_tons: number; new_tons: number } | null>(null);

    const [start, end] = useMemo(() => rangeFor(type, anchor, customEnd), [type, anchor, customEnd]);
    const weeks = useMemo(() => Math.max(1, daysBetween(start, end) / 7), [start, end]);

    const targetNum = Number(target) || 0;

    // Advisory capacity metrics for the period — informational only, never a gate.
    const fleetCapacity = useMemo(() => fleetWeeklyCapacityT * weeks, [fleetWeeklyCapacityT, weeks]);
    const coveragePct = targetNum > 0 ? Math.round((fleetCapacity / targetNum) * 100) : null;
    const uncovered = Math.max(0, targetNum - fleetCapacity);
    const trucksNeeded = useMemo(() => {
        const perTruck = availableTruckCount > 0 ? fleetCapacity / availableTruckCount : 0;
        return perTruck > 0 && targetNum > 0 ? Math.ceil(targetNum / perTruck) : 0;
    }, [fleetCapacity, availableTruckCount, targetNum]);

    const save = (override = false) => {
        setSaving(true);
        router.post('/logistics/objectives', {
            period_type: type,
            start_date: type === 'CUSTOM' ? start : anchor,
            end_date: type === 'CUSTOM' ? end : null,
            target_tons: targetNum,
            notes,
            override: override || !!editing, // editing a locked period is an explicit replace
        }, {
            preserveState: true, // keep the form if we bounce back to confirm a replace
            preserveScroll: true,
            onSuccess: (page) => {
                const c = (page.props as { flash?: { objectiveConflict?: { existing_tons: number; new_tons: number } } }).flash?.objectiveConflict;
                if (c) setConflict(c); // existing objective — ask before overwriting
            },
            onFinish: () => setSaving(false),
        });
    };

    return (
        <AuthenticatedLayout title={editing ? 'Modifier l’objectif' : 'Nouvel objectif'}>
            <Head title={editing ? 'Modifier l’objectif' : 'Nouvel objectif'} />

            <div className="flex items-center justify-between gap-3 flex-wrap mb-4">
                <div className="flex items-center gap-2">
                    <Target size={22} className="text-[var(--color-primary)]" />
                    <h1 className="text-xl font-semibold">{editing ? 'Modifier l’objectif' : 'Nouvel objectif'}</h1>
                </div>
                <Button variant="secondary" onClick={() => router.visit('/logistics/objectives')}>
                    <ArrowLeft size={14} className="mr-1" /> Objectifs
                </Button>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 items-start">
                {/* Card A — Target + Period (order-swapped: Target first on mobile, Period first on desktop) */}
                <Card className="order-1 lg:col-span-2 lg:col-start-1 lg:row-start-1">
                    <div className="flex flex-col gap-5">
                        {/* Target — the primary action */}
                        <div className="order-1 lg:order-2">
                            <label htmlFor="target_tons" className={LABEL}>Tonnage à transporter</label>
                            <div className="relative">
                                <input
                                    id="target_tons"
                                    type="number" step="0.1" min="0" inputMode="decimal" autoFocus
                                    value={target} onChange={(e) => setTarget(e.target.value)}
                                    placeholder="0"
                                    className="w-full h-14 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] pl-4 pr-10 text-3xl font-bold tabular-nums text-[var(--color-text)] transition focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]/20 focus:border-[var(--color-primary)]"
                                />
                                <span className="absolute right-4 top-1/2 -translate-y-1/2 text-lg font-semibold text-[var(--color-text-muted)]">t</span>
                            </div>
                        </div>

                        {/* Period */}
                        <div className="order-2 lg:order-1">
                            <Field label="Type de période">
                                <div className="flex flex-wrap gap-2">
                                    {periodTypes.map((m) => (
                                        <button
                                            key={m}
                                            type="button"
                                            disabled={!!editing}
                                            aria-pressed={type === m}
                                            onClick={() => setType(m)}
                                            className={clsx(
                                                'px-4 text-sm font-medium rounded-lg border transition-colors', CONTROL_H,
                                                editing && 'opacity-50 cursor-not-allowed',
                                                type === m
                                                    ? 'bg-[var(--color-primary)] text-white border-[var(--color-primary)]'
                                                    : 'border-[var(--color-border)] text-[var(--color-text-secondary)] hover:bg-[var(--color-surface-hover)] cursor-pointer',
                                            )}
                                        >
                                            {TYPE_LABEL[m]}
                                        </button>
                                    ))}
                                </div>
                            </Field>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                                <FormInput
                                    label={type === 'CUSTOM' ? 'Date de début' : 'Date de référence'}
                                    type="date" value={anchor} onChange={(e) => setAnchor(e.target.value)}
                                    disabled={!!editing} className={CONTROL_H} wrapperClass="mb-0"
                                />
                                {type === 'CUSTOM' ? (
                                    <FormInput
                                        label="Date de fin" type="date" value={customEnd}
                                        onChange={(e) => setCustomEnd(e.target.value)}
                                        disabled={!!editing} className={CONTROL_H} wrapperClass="mb-0"
                                    />
                                ) : (
                                    <Field label="Période">
                                        <div className={clsx('flex items-center px-3 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-hover)] text-sm font-medium', CONTROL_H)}>
                                            {start} → {end}
                                        </div>
                                    </Field>
                                )}
                            </div>
                        </div>
                    </div>
                </Card>

                {/* Card C — Capacity (business metrics only) */}
                <Card className="order-2 lg:col-start-3 lg:row-start-1">
                    <h2 className="text-xs font-semibold uppercase tracking-wide text-[var(--color-text-muted)] mb-3">Potentiel de transport</h2>
                    <div className="space-y-2">
                        <RailLine label="Volume réalisable" value={`~${fmt(fleetCapacity)} t`} strong />
                        <RailLine label="Flotte disponible" value={`${availableTruckCount} camions`} />
                        <RailLine label="Volume par voyage" value={`${fmt(planCapacityT)} t`} />
                        {targetNum > 0 && (
                            <>
                                <div className="border-t border-[var(--color-border)] !mt-3 pt-3 space-y-2">
                                    <RailLine label="Objectif" value={`${fmt(targetNum)} t`} />
                                    <RailLine
                                        label="Couverture"
                                        value={`${coveragePct}%`}
                                        valueClass={coveragePct != null && coveragePct >= 100 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'}
                                    />
                                    <RailLine
                                        label="Volume non couvert"
                                        value={`${fmt(uncovered)} t`}
                                        valueClass={uncovered > 0 ? 'text-amber-600 dark:text-amber-400' : undefined}
                                    />
                                    {trucksNeeded > 0 && <RailLine label="Camions nécessaires" value={`~${trucksNeeded}`} />}
                                </div>
                            </>
                        )}
                    </div>
                </Card>

                {/* Card D — Previous period */}
                <Card className="order-3 lg:col-start-3 lg:row-start-2">
                    <h2 className="text-xs font-semibold uppercase tracking-wide text-[var(--color-text-muted)] mb-3">Période précédente</h2>
                    {previousPeriod ? (
                        <div className="space-y-2">
                            <div className="text-xs text-[var(--color-text-muted)]">{previousPeriod.start} → {previousPeriod.end}</div>
                            <RailLine label="Objectif" value={`${fmt(previousPeriod.target_tons)} t`} />
                            <RailLine label="Transporté" value={`${fmt(previousPeriod.done_tons)} t`} />
                            <RailLine
                                label="Couverture"
                                value={previousPeriod.pct != null ? `${previousPeriod.pct}%` : '—'}
                                valueClass={previousPeriod.pct == null ? undefined
                                    : previousPeriod.pct >= 100 ? 'text-emerald-600 dark:text-emerald-400'
                                        : previousPeriod.pct >= 75 ? 'text-[var(--color-primary)]'
                                            : 'text-amber-600 dark:text-amber-400'}
                            />
                        </div>
                    ) : (
                        <p className="text-sm text-[var(--color-text-muted)]">Aucune période précédente.</p>
                    )}
                </Card>

                {/* Card B — Notes + Save (Actions) */}
                <Card className="order-4 lg:col-span-2 lg:col-start-1 lg:row-start-2">
                    <FormInput
                        label="Note (optionnel)" type="text" value={notes}
                        onChange={(e) => setNotes(e.target.value)} maxLength={500}
                        className={CONTROL_H} wrapperClass="mb-0"
                    />
                    {canEdit && (
                        <div className="mt-4 flex">
                            <Button onClick={() => save()} loading={saving} disabled={targetNum <= 0} className="h-11 w-full sm:w-auto sm:ml-auto px-5">
                                <Target size={15} className="mr-1.5" /> {editing ? 'Enregistrer' : 'Créer l’objectif'}
                            </Button>
                        </div>
                    )}
                </Card>
            </div>

            <ConfirmDialog
                open={!!conflict}
                onClose={() => setConflict(null)}
                title="Objectif déjà défini"
                message={conflict ? `Un objectif de ${fmt(conflict.existing_tons)} t existe déjà pour cette période. Le remplacer par ${fmt(conflict.new_tons)} t ?` : ''}
                confirmLabel="Remplacer"
                onConfirm={() => save(true)}
            />
        </AuthenticatedLayout>
    );
}

/** Uniform labelled cell — same label style as FormInput, for non-input controls. */
function Field({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div>
            <span className={LABEL}>{label}</span>
            {children}
        </div>
    );
}

function RailLine({ label, value, strong, valueClass }: { label: string; value: string; strong?: boolean; valueClass?: string }) {
    return (
        <div className="flex items-center justify-between gap-2">
            <span className="text-sm text-[var(--color-text-secondary)]">{label}</span>
            <span className={clsx('tabular-nums', strong ? 'text-base font-bold' : 'text-sm font-semibold', valueClass)}>{value}</span>
        </div>
    );
}
