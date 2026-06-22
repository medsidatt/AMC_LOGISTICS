import { Head, router, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import PeriodSwitcher from '@/components/logistics/PeriodSwitcher';
import type { Achievement, PlanningMode } from '@/types/achievement';
import { Target, CheckCircle2, TrendingUp, Gauge, Trophy, AlertTriangle, ListChecks } from 'lucide-react';
import { clsx } from 'clsx';

interface Props {
    mode: PlanningMode;
    period: { start: string; end: string };
    achievement: Achievement;
}

const fmt = (n: number) => Math.round(n).toLocaleString('fr-FR');

const MODE_LABEL: Record<PlanningMode, string> = { WEEK: 'hebdomadaire', MONTH: 'mensuel', YEAR: 'annuel', CUSTOM: 'personnalisé' };

// Fill rate = how full the truck was loaded vs its capacity. Below 70% is a flag.
const fillColor = (pct: number | null) =>
    pct == null ? 'text-[var(--color-text-muted)]'
        : pct >= 90 ? 'text-emerald-600 dark:text-emerald-400'
            : pct >= 70 ? 'text-amber-600 dark:text-amber-400'
                : 'text-red-600 dark:text-red-400';
const fillBar = (pct: number | null) =>
    pct == null ? 'bg-[var(--color-surface-hover)]'
        : pct >= 90 ? 'bg-emerald-500'
            : pct >= 70 ? 'bg-amber-500'
                : 'bg-red-500';

const pctColor = (pct: number | null) =>
    pct == null ? 'text-[var(--color-text-muted)]'
        : pct >= 100 ? 'text-emerald-600 dark:text-emerald-400'
            : pct >= 75 ? 'text-[var(--color-primary)]'
                : pct >= 50 ? 'text-amber-600 dark:text-amber-400'
                    : 'text-red-600 dark:text-red-400';

function SourceBadge({ source, coverage }: { source?: string; coverage?: number }) {
    if (!source || source === 'none') return <Badge variant="warning">Aucun objectif défini</Badge>;
    if (source === 'exact') return <Badge variant="success">Objectif exact</Badge>;
    if (source === 'derived') return <Badge variant="info">Objectif proraté</Badge>;
    // aggregated
    const partial = coverage != null && coverage < 1;
    return (
        <span className="inline-flex items-center gap-1.5">
            <Badge variant="info">Objectifs agrégés</Badge>
            {partial && <Badge variant="warning">Couverture {Math.round((coverage ?? 0) * 100)}%</Badge>}
        </span>
    );
}

function Kpi({ icon, label, value, sub, subClass }: { icon: React.ReactNode; label: string; value: string; sub?: string; subClass?: string }) {
    return (
        <div className="rounded-xl border border-[var(--color-border)] p-4 bg-[var(--color-surface)]">
            <div className="flex items-center gap-1.5 text-xs uppercase tracking-wide text-[var(--color-text-muted)]">{icon}{label}</div>
            <div className="text-2xl font-bold text-[var(--color-text)] mt-1.5 leading-tight tabular-nums">{value}</div>
            {sub && <div className={clsx('text-xs mt-0.5', subClass ?? 'text-[var(--color-text-muted)]')}>{sub}</div>}
        </div>
    );
}

export default function PlanningScoreboard({ mode, period, achievement }: Props) {
    const f = achievement.fleet;
    const p = achievement.projection;
    const variance = Math.round(f.done_tons - f.target_tons);
    const varianceLabel = variance >= 0 ? `+${fmt(variance)} t vs objectif` : `${fmt(variance)} t vs objectif`;

    return (
        <AuthenticatedLayout>
            <Head title={`Suivi ${MODE_LABEL[mode]}`} />
            <div className="space-y-5">
                <div className="flex items-start justify-between gap-3 flex-wrap">
                    <div>
                        <div className="flex items-center gap-2">
                            <Trophy size={22} className="text-[var(--color-primary)]" />
                            <h1 className="text-xl font-semibold">Suivi de la planification</h1>
                        </div>
                        <p className="text-sm text-[var(--color-text-muted)] mt-1">
                            Objectif vs réalisé — du {period.start} au {period.end}.
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Link href="/logistics/objectives">
                            <Button variant="secondary"><Target size={14} className="mr-1" /> Objectifs</Button>
                        </Link>
                        <Button variant="secondary" onClick={() => router.visit('/logistics/planning')}>Programmation</Button>
                    </div>
                </div>

                <Card>
                    <div className="space-y-4">
                        <div className="flex items-center justify-between gap-3 flex-wrap">
                            <PeriodSwitcher mode={mode} period={period} />
                            <SourceBadge source={achievement.target_source} coverage={achievement.target_coverage} />
                        </div>

                        <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
                            <Kpi icon={<Target size={14} />} label="Planifié" value={`${fmt(f.target_tons)} t`} sub={`${fmt(f.target_rotations)} rotations`} />
                            <Kpi
                                icon={<CheckCircle2 size={14} className="text-emerald-500" />}
                                label="Réalisé"
                                value={`${fmt(f.done_tons)} t`}
                                sub={f.target_tons > 0 ? varianceLabel : `${fmt(f.done_rotations)} rotations`}
                                subClass={f.target_tons > 0 ? (variance >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400') : undefined}
                            />
                            <Kpi icon={<TrendingUp size={14} />} label="Restant" value={`${fmt(f.remaining_tons)} t`} sub={`${fmt(f.remaining_rotations)} rotations`} />
                            <Kpi
                                icon={<Gauge size={14} className={pctColor(f.pct)} />}
                                label="Réalisation"
                                value={`${f.pct ?? 0}%`}
                                sub={p.on_track ? 'En bonne voie' : 'En retard'}
                                subClass={p.on_track ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'}
                            />
                        </div>

                        <div>
                            <div className="flex items-center justify-between text-sm mb-1">
                                <span className="text-[var(--color-text-secondary)]">Avancement</span>
                                <span className={clsx('font-semibold', pctColor(f.pct))}>{f.pct ?? 0}%</span>
                            </div>
                            <div className="h-2.5 rounded-full bg-[var(--color-surface-hover)] overflow-hidden">
                                <div
                                    className={clsx('h-full rounded-full', (f.pct ?? 0) >= 100 ? 'bg-emerald-500' : 'bg-[var(--color-primary)]')}
                                    style={{ width: `${Math.min(100, f.pct ?? 0)}%` }}
                                />
                            </div>
                            <div className="flex items-center gap-3 mt-2 text-xs text-[var(--color-text-muted)] flex-wrap">
                                <span>Jour {p.days_elapsed}/{p.days_total}</span>
                                <span>· Rythme {p.pace_rotations_per_day}/j</span>
                                <span>· Projection {fmt(p.projected_tons)} t</span>
                                {f.missing_tickets > 0 && (
                                    <span className="inline-flex items-center gap-1 text-amber-600 dark:text-amber-400">
                                        <AlertTriangle size={12} /> {f.missing_tickets} bon{f.missing_tickets > 1 ? 's' : ''} manquant{f.missing_tickets > 1 ? 's' : ''}
                                    </span>
                                )}
                            </div>
                        </div>

                        <div className="flex items-center gap-3 rounded-xl border border-[var(--color-border)] p-3 flex-wrap">
                            <div className="flex items-center gap-2">
                                <Gauge size={18} className={fillColor(f.fill_pct)} />
                                <span className="font-semibold">Remplissage moyen</span>
                            </div>
                            <span className={clsx('text-2xl font-bold tabular-nums', fillColor(f.fill_pct))}>
                                {f.fill_pct ?? '—'}{f.fill_pct != null && '%'}
                            </span>
                            <span className="text-sm text-[var(--color-text-muted)]">
                                charge moyenne <strong>{fmt(f.avg_load_t)} t</strong> par rotation
                            </span>
                        </div>
                    </div>
                </Card>

                <Card padding={false}>
                    <div className="px-4 pt-4 pb-2 font-semibold">Réalisation par camion</div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="bg-[var(--color-surface-hover)] text-[11px] uppercase tracking-wide text-[var(--color-text-secondary)]">
                                    <th className="px-4 py-3 text-left font-semibold">Camion</th>
                                    <th className="px-4 py-3 text-right font-semibold">Planifié</th>
                                    <th className="px-4 py-3 text-right font-semibold">Réalisé</th>
                                    <th className="px-4 py-3 text-right font-semibold">Restant</th>
                                    <th className="px-4 py-3 text-left font-semibold w-32">Avancement</th>
                                    <th className="px-4 py-3 text-right font-semibold">Charge moy.</th>
                                    <th className="px-4 py-3 text-left font-semibold w-32">Remplissage</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[var(--color-border)]">
                                {achievement.per_truck.length === 0 ? (
                                    <tr><td colSpan={7} className="px-4 py-10 text-center text-[var(--color-text-muted)]">Aucun objectif pour cette période.</td></tr>
                                ) : achievement.per_truck.map((t) => (
                                    <tr key={t.truck_id} className="hover:bg-[var(--color-surface-hover)]/40">
                                        <td className="px-4 py-3 font-medium">{t.matricule}</td>
                                        <td className="px-4 py-3 text-right font-mono">{fmt(t.target_rotations)} rot</td>
                                        <td className="px-4 py-3 text-right font-mono">
                                            {fmt(t.done_rotations)} rot
                                            {t.missing_tickets > 0 && <Badge variant="warning"><AlertTriangle size={10} className="mr-0.5" />{t.missing_tickets}</Badge>}
                                        </td>
                                        <td className="px-4 py-3 text-right font-mono text-amber-600 dark:text-amber-400">{fmt(t.remaining_rotations)} rot</td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-2">
                                                <div className="flex-1 h-2 rounded-full bg-[var(--color-surface-hover)] overflow-hidden">
                                                    <div className="h-full rounded-full bg-[var(--color-primary)]" style={{ width: `${Math.min(100, t.pct ?? 0)}%` }} />
                                                </div>
                                                <span className="text-xs font-semibold w-8 text-right">{t.pct ?? '—'}</span>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-right font-mono">
                                            {t.ticketed_rotations > 0 ? (
                                                <>
                                                    <span className={fillColor(t.fill_pct)}>{fmt(t.avg_load_t)} t</span>
                                                    <span className="text-[var(--color-text-muted)] text-xs"> / {fmt(t.capacity_tonnage)}</span>
                                                </>
                                            ) : <span className="text-[var(--color-text-muted)]">—</span>}
                                        </td>
                                        <td className="px-4 py-3">
                                            {t.fill_pct == null ? (
                                                <span className="text-[var(--color-text-muted)] text-xs">—</span>
                                            ) : (
                                                <div className="flex items-center gap-2">
                                                    <div className="flex-1 h-2 rounded-full bg-[var(--color-surface-hover)] overflow-hidden">
                                                        <div className={clsx('h-full rounded-full', fillBar(t.fill_pct))} style={{ width: `${t.fill_pct}%` }} />
                                                    </div>
                                                    <span className={clsx('text-xs font-semibold w-8 text-right', fillColor(t.fill_pct))}>{t.fill_pct}%</span>
                                                </div>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>

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
