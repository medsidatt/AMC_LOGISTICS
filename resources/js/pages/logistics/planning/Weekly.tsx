import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import PeriodSwitcher from '@/components/logistics/PeriodSwitcher';
import type { Achievement, PlanningMode } from '@/types/achievement';
import { Trophy, ListChecks } from 'lucide-react';

interface Props {
    mode: PlanningMode;
    period: { start: string; end: string };
    achievement: Achievement;
}

const fmt = (n: number) => Math.round(n).toLocaleString('fr-FR');
const MODE_LABEL: Record<PlanningMode, string> = { WEEK: 'hebdomadaire', MONTH: 'mensuel', YEAR: 'annuel', CUSTOM: 'personnalisé' };

/**
 * Réalisation — operations briefing of what has actually been done. Plain
 * operational sentences + the realization-by-truck table as the core. No KPI
 * cards, progress bars, gauges, badges or dashboard widgets.
 */
export default function Realisation({ mode, period, achievement }: Props) {
    const f = achievement.fleet;
    // Three target states: manual objective (exact), reference target (estimated from a
    // parent objective — reporting only), or none.
    const isManual = achievement.target_source === 'exact';
    const isEstimated = achievement.target_source === 'estimated';
    // Manual objective: progress vs target (never clamped to 100 %).
    const rawPct = f.target_tons > 0 ? Math.round((f.done_tons / f.target_tons) * 100) : null;
    const surplus = Math.max(0, Math.round(f.done_tons - f.target_tons));
    const remaining = Math.max(0, Math.round(f.target_tons - f.done_tons));
    // Reference target: report the raw difference (realized − reference), no % / "remaining".
    const diff = Math.round(f.done_tons - f.target_tons);
    // Reference (estimated) and "no target" states show realized only — never fabricate
    // truck planning, so the planned/remaining columns are dropped.
    const rows = isManual ? achievement.per_truck : achievement.per_truck.filter((t) => t.done_rotations > 0);

    return (
        <AuthenticatedLayout>
            <Head title={`Réalisation — ${MODE_LABEL[mode]}`} />
            <div className="space-y-5">
                <div className="flex items-start justify-between gap-3 flex-wrap">
                    <div className="flex items-center gap-2">
                        <Trophy size={22} className="text-[var(--color-primary)]" />
                        <h1 className="text-xl font-semibold">Réalisation</h1>
                    </div>
                    <PeriodSwitcher mode={mode} period={period} />
                </div>

                {/* Operational briefing — sentences, not metrics. Three target states. */}
                <Card>
                    <div className="text-sm leading-relaxed space-y-1">
                        <p className="text-[var(--color-text-muted)]">Période du {period.start} au {period.end}.</p>

                        {/* State 1 — manual objective */}
                        {isManual && (
                            <>
                                <p><strong>{fmt(f.done_tons)} t</strong> réalisées sur <strong>{fmt(f.target_tons)} t</strong> prévues.</p>
                                <p>Progression du plan : <strong className={rawPct != null && rawPct >= 100 ? 'text-emerald-600 dark:text-emerald-400' : ''}>{rawPct} %</strong>.</p>
                                {surplus > 0
                                    ? <p>Excédent : <strong className="text-emerald-600 dark:text-emerald-400">+{fmt(surplus)} t</strong>.</p>
                                    : <p><strong>{fmt(remaining)} t</strong> restent à transporter.</p>}
                            </>
                        )}

                        {/* State 2 — hierarchical reference (read-only, estimated from planning) */}
                        {isEstimated && (
                            <>
                                <p className="font-semibold pt-1">Référence de pilotage</p>
                                <p><strong>{fmt(f.target_tons)} t</strong></p>
                                <p className="pt-1"><strong>{fmt(f.done_tons)} t</strong> réalisées · écart <strong>{diff >= 0 ? '+' : ''}{fmt(diff)} t</strong> vs référence.</p>
                            </>
                        )}

                        {/* State 3 — no reference available: realized only */}
                        {!isManual && !isEstimated && (
                            <>
                                <p className="text-[var(--color-text-muted)]">Aucune référence disponible.</p>
                                <p><strong>{fmt(f.done_tons)} t</strong> réalisées · <strong>{fmt(f.done_rotations)} rotations</strong>.</p>
                            </>
                        )}
                    </div>
                </Card>

                {/* Realization by truck — the core of the page */}
                <Card padding={false}>
                    <div className="px-4 pt-4 pb-2 font-semibold">Réalisation par camion</div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="bg-[var(--color-surface-hover)] text-[11px] uppercase tracking-wide text-[var(--color-text-secondary)]">
                                    <th className="px-4 py-3 text-left font-semibold">Camion</th>
                                    {isManual && <th className="px-4 py-3 text-right font-semibold">Planifié</th>}
                                    <th className="px-4 py-3 text-right font-semibold">Réalisé</th>
                                    {isManual && <th className="px-4 py-3 text-right font-semibold">Restant</th>}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[var(--color-border)]">
                                {rows.length === 0 ? (
                                    <tr><td colSpan={isManual ? 4 : 2} className="px-4 py-10 text-center text-[var(--color-text-muted)]">Aucune réalisation sur cette période.</td></tr>
                                ) : rows.map((t) => (
                                    <tr key={t.truck_id} className="hover:bg-[var(--color-surface-hover)]/40">
                                        <td className="px-4 py-3 font-medium">{t.matricule}</td>
                                        {isManual && <td className="px-4 py-3 text-right font-mono">{fmt(t.target_rotations)} rot</td>}
                                        <td className="px-4 py-3 text-right font-mono">{fmt(t.done_rotations)} rot</td>
                                        {isManual && <td className="px-4 py-3 text-right font-mono text-amber-600 dark:text-amber-400">{fmt(t.remaining_rotations)} rot</td>}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>

                {/* Missing tickets — operational list (GPS rotations without a ticket) */}
                {achievement.missing_ticket_list.length > 0 && (
                    <Card>
                        <h2 className="font-semibold flex items-center gap-2 mb-3 text-amber-600 dark:text-amber-400">
                            <ListChecks size={18} /> Tickets manquants ({achievement.fleet.missing_tickets})
                        </h2>
                        <p className="text-xs text-[var(--color-text-muted)] mb-3">Rotations détectées par GPS (carrière → retour) sans bon de transport saisi.</p>
                        <div className="space-y-1.5">
                            {achievement.missing_ticket_list.map((m, i) => (
                                <div key={i} className="flex items-center justify-between text-sm rounded-lg border border-[var(--color-border)] px-3 py-2">
                                    <span className="font-medium">{m.matricule}</span>
                                    <span className="text-[var(--color-text-muted)]">{m.date} · {fmt(m.distance_km)} km</span>
                                </div>
                            ))}
                        </div>
                    </Card>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
