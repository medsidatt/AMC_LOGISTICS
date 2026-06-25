import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import ObjectiveDrawer, { type ObjectiveDrawerInitial } from '@/components/operations/ObjectiveDrawer';
import type { PlanningMode } from '@/types/achievement';
import { clsx } from 'clsx';
import { Plus, Pencil } from 'lucide-react';

interface AllocationRow { matricule: string; rotations: number; tonnage: number; capacity_rotations: number; utilisation: number | null; available: boolean }

interface Props {
    period: { type: PlanningMode; start: string; end: string };
    periodTypes: PlanningMode[];
    situation: string;
    hierarchy: { period_type: PlanningMode; label: string; planned: boolean; target_tons: number | null }[];
    objective: { target_tons: number; target_rotations: number; required_trucks: number; period_type: PlanningMode; start: string; end: string; notes: string | null } | null;
    capacity: { available: number; availability_rate: number | null; coverage: number | null; gap: number | null };
    allocation: AllocationRow[];
    constraints: { key: string; message: string }[];
}

const fmt = (n: number) => Math.round(n).toLocaleString('fr-FR');
const TYPE_LABEL: Record<PlanningMode, string> = { WEEK: 'Semaine', MONTH: 'Mois', YEAR: 'Année', CUSTOM: 'Personnalisé' };
const dayMonth = (iso: string) => new Date(iso + 'T00:00:00').toLocaleDateString('fr-FR', { day: 'numeric', month: 'long' });

function L({ children }: { children: React.ReactNode }) {
    return <div className="text-[11px] font-semibold uppercase tracking-wider text-[var(--color-text-muted)] mb-1">{children}</div>;
}

/**
 * Planification — a working planning workspace (objective + capacity + truck
 * allocation + constraints + actions). Objectives are created/modified in a side
 * drawer so the manager stays inside Planning. Planning data only — no execution
 * metrics (those live in Réalisation).
 */
export default function Overview({ period, periodTypes, situation, hierarchy, objective, capacity, allocation, constraints }: Props) {
    const [drawer, setDrawer] = useState<{ initial: ObjectiveDrawerInitial; editing: boolean } | null>(null);

    const covered = capacity.coverage == null ? null : capacity.coverage >= 100;
    const margin = capacity.gap == null ? null : `${capacity.gap >= 0 ? '+' : '−'}${fmt(Math.abs(capacity.gap))} t`;
    const planned = allocation.filter((r) => r.rotations > 0);
    const totalPlannedRot = planned.reduce((s, r) => s + r.rotations, 0);
    const rotPerTruck = planned.length > 0 ? Math.round(totalPlannedRot / planned.length) : 0;

    const openCreate = () => setDrawer({
        editing: false,
        initial: { type: period.type, start: period.start, end: period.end, target: 0, notes: '' },
    });
    const openModify = () => objective && setDrawer({
        editing: true,
        initial: { type: objective.period_type, start: objective.start, end: objective.end, target: objective.target_tons, notes: objective.notes ?? '' },
    });

    return (
        <AuthenticatedLayout>
            <Head title="Planification" />
            <div className="max-w-4xl space-y-5">
                {/* Header + create/modify in the workspace */}
                <div className="flex items-start justify-between gap-3 flex-wrap">
                    <div>
                        <h1 className="text-xl font-semibold">Planification</h1>
                        <p className="text-sm text-[var(--color-text-muted)] mt-0.5">
                            {TYPE_LABEL[period.type]} — {dayMonth(period.start)} → {dayMonth(period.end)} · <span className={covered === false ? 'text-amber-600 dark:text-amber-400' : ''}>{situation}</span>
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button variant="secondary" size="sm" onClick={() => router.visit('/planning/objectives')}>Liste des objectifs</Button>
                        {objective && (
                            <Button variant="secondary" size="sm" icon={<Pencil size={15} />} onClick={openModify}>Modifier objectif</Button>
                        )}
                        <Button size="sm" icon={<Plus size={15} />} onClick={openCreate}>Nouvel objectif</Button>
                    </div>
                </div>

                {/* Objective + Capacity */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">
                    <Card>
                        <L>Objectifs actifs</L>
                        <div className="space-y-2.5">
                            {hierarchy.map((h) => (
                                <div key={h.period_type} className="flex items-baseline justify-between gap-3">
                                    <span className="text-sm text-[var(--color-text-secondary)]">{h.label}</span>
                                    {h.planned
                                        ? <span className="text-lg font-bold tabular-nums">{fmt(h.target_tons as number)} t</span>
                                        : <span className="text-sm text-[var(--color-text-muted)]">Non planifiée</span>}
                                </div>
                            ))}
                        </div>
                        {hierarchy.every((h) => !h.planned) && (
                            <p className="text-sm text-[var(--color-text-muted)] mt-3">
                                <button onClick={openCreate} className="text-[var(--color-primary)] hover:underline">Définir un objectif</button>
                            </p>
                        )}
                    </Card>

                    <Card>
                        <L>Capacité</L>
                        <p className="text-sm leading-relaxed">
                            <strong>{fmt(capacity.available)} t</strong> disponibles
                            {capacity.availability_rate != null && <> · disponibilité flotte {capacity.availability_rate} %</>}
                            {capacity.coverage != null && (
                                <>
                                    <br />
                                    <strong className={covered ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'}>
                                        {covered ? 'Objectif couvert' : 'Objectif non couvert'}
                                    </strong>
                                    {capacity.gap != null && (covered
                                        ? <> — {fmt(capacity.gap)} t de marge disponible</>
                                        : <> — il manque {fmt(Math.abs(capacity.gap))} t</>)}
                                </>
                            )}
                        </p>
                    </Card>
                </div>

                {/* Truck planning allocation — the plan */}
                <div>
                    <L>Allocation des camions</L>
                    {planned.length > 0 && (
                        <p className="text-sm text-[var(--color-text-muted)] mb-2">
                            {planned.length} camions affectés pour {fmt(totalPlannedRot)} rotations — soit {rotPerTruck} par camion.
                        </p>
                    )}
                    <Card padding={false}>
                        {planned.length === 0 ? (
                            <p className="p-5 text-sm text-[var(--color-text-muted)]">Aucune allocation — définissez un objectif pour répartir la flotte.</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="bg-[var(--color-surface-hover)] text-[11px] uppercase tracking-wide text-[var(--color-text-secondary)]">
                                            <th className="px-4 py-3 text-left font-semibold">Camion</th>
                                            <th className="px-4 py-3 text-right font-semibold">Rotations prévues</th>
                                            <th className="px-4 py-3 text-right font-semibold">Capacité max</th>
                                            <th className="px-4 py-3 text-right font-semibold">Charge planifiée</th>
                                            <th className="px-4 py-3 text-right font-semibold">Utilisation</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-[var(--color-border)]">
                                        {planned.map((r) => (
                                            <tr key={r.matricule} className="hover:bg-[var(--color-surface-hover)]/40">
                                                <td className="px-4 py-2.5 font-medium">{r.matricule}</td>
                                                <td className="px-4 py-2.5 text-right font-mono">{r.rotations} rot</td>
                                                <td className="px-4 py-2.5 text-right font-mono">{r.capacity_rotations} rot</td>
                                                <td className="px-4 py-2.5 text-right font-mono">{fmt(r.tonnage)} t</td>
                                                <td className={clsx('px-4 py-2.5 text-right font-mono', r.utilisation != null && r.utilisation > 100 ? 'text-amber-600 dark:text-amber-400' : '')}>
                                                    {r.utilisation != null ? `${r.utilisation} %` : '—'}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </Card>
                </div>

                {/* Constraints */}
                {constraints.length > 0 && (
                    <div>
                        <L>Contraintes</L>
                        <Card>
                            <ul className="space-y-1.5">
                                {constraints.map((c) => (
                                    <li key={c.key} className="text-sm text-amber-700 dark:text-amber-300">• {c.message}</li>
                                ))}
                            </ul>
                        </Card>
                    </div>
                )}
            </div>

            {drawer && (
                <ObjectiveDrawer
                    onClose={() => setDrawer(null)}
                    periodTypes={periodTypes}
                    initial={drawer.initial}
                    editing={drawer.editing}
                />
            )}
        </AuthenticatedLayout>
    );
}
