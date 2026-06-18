import { Head, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import ObjectiveTrendChart from '@/components/charts/ObjectiveTrendChart';
import { ArrowLeft, Target, History as HistoryIcon, TrendingUp } from 'lucide-react';
import { clsx } from 'clsx';

interface Objective {
    id: number;
    start_date: string;
    end_date: string;
    target_tons: number;
    target_rotations: number;
    achieved_tons: number;
    achieved_rotations: number;
    ticketed_rotations: number;
    gps_only_rotations: number;
    missing_tickets: number;
    remaining_tons: number;
    remaining_rotations: number;
    pct: number | null;
    working_trucks: number;
    rested_trucks: number;
    notes: string | null;
    created_by: string | null;
}

interface TrendPoint { label: string; target_tons: number; achieved_tons: number }

interface Props {
    objectives: Objective[];
    trend: TrendPoint[];
}

const fmt = (n: number) => Math.round(n).toLocaleString('fr-FR');

export default function FleetObjectiveHistory({ objectives, trend }: Props) {
    return (
        <AuthenticatedLayout>
            <Head title="Historique des objectifs" />
            <div className="space-y-5">
                <div className="flex items-start justify-between gap-3 flex-wrap">
                    <div>
                        <div className="flex items-center gap-2">
                            <HistoryIcon size={22} className="text-[var(--color-primary)]" />
                            <h1 className="text-xl font-semibold">Historique des objectifs</h1>
                        </div>
                        <p className="text-sm text-[var(--color-text-muted)] mt-1">
                            Objectif réalisé et restant par période — en tonnage et en rotations.
                        </p>
                    </div>
                    <Button variant="secondary" onClick={() => router.visit('/logistics/fleet-roster')}>
                        <ArrowLeft size={14} className="mr-1" /> Retour au planning
                    </Button>
                </div>

                {trend.length > 1 && (
                    <Card>
                        <h2 className="text-base font-semibold flex items-center gap-2 mb-3"><TrendingUp size={18} className="text-[var(--color-primary)]" /> Tendance hebdomadaire</h2>
                        <ObjectiveTrendChart
                            labels={trend.map((t) => t.label)}
                            target={trend.map((t) => t.target_tons)}
                            achieved={trend.map((t) => t.achieved_tons)}
                        />
                    </Card>
                )}

                {objectives.length === 0 ? (
                    <Card>
                        <div className="text-center py-12 text-[var(--color-text-muted)]">
                            <Target size={32} className="mx-auto mb-2 opacity-30" />
                            Aucun objectif enregistré. Programmez une période depuis le planning.
                        </div>
                    </Card>
                ) : (
                    <Card padding={false}>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="bg-[var(--color-surface-hover)] text-[11px] uppercase tracking-wide text-[var(--color-text-secondary)]">
                                        <th className="px-4 py-3 text-left font-semibold">Période</th>
                                        <th className="px-4 py-3 text-right font-semibold">Objectif</th>
                                        <th className="px-4 py-3 text-right font-semibold">Réalisé</th>
                                        <th className="px-4 py-3 text-right font-semibold">Restant</th>
                                        <th className="px-4 py-3 text-left font-semibold w-40">Avancement</th>
                                        <th className="px-4 py-3 text-center font-semibold">Flotte</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-[var(--color-border)]">
                                    {objectives.map((o) => {
                                        const pct = o.pct ?? 0;
                                        const done = o.pct !== null && o.pct >= 100;
                                        return (
                                            <tr key={o.id} className="hover:bg-[var(--color-surface-hover)]/40">
                                                <td className="px-4 py-3 whitespace-nowrap">
                                                    <div className="font-medium text-[var(--color-text)]">{o.start_date} → {o.end_date}</div>
                                                    {o.created_by && <div className="text-xs text-[var(--color-text-muted)]">par {o.created_by}</div>}
                                                </td>
                                                <td className="px-4 py-3 text-right whitespace-nowrap">
                                                    <div className="font-mono font-semibold">{fmt(o.target_tons)} t</div>
                                                    <div className="text-xs text-[var(--color-text-muted)]">{fmt(o.target_rotations)} rot.</div>
                                                </td>
                                                <td className="px-4 py-3 text-right whitespace-nowrap">
                                                    <div className="font-mono font-semibold text-emerald-600 dark:text-emerald-400">{fmt(o.achieved_tons)} t</div>
                                                    <div className="text-xs text-[var(--color-text-muted)]">{fmt(o.achieved_rotations)} rot. ({fmt(o.ticketed_rotations)} ticket + {fmt(o.gps_only_rotations)} GPS)</div>
                                                    {o.missing_tickets > 0 && <Badge variant="warning" className="mt-0.5">{o.missing_tickets} ticket manquant</Badge>}
                                                </td>
                                                <td className="px-4 py-3 text-right whitespace-nowrap">
                                                    <div className={clsx('font-mono font-semibold', o.remaining_tons > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-[var(--color-text-muted)]')}>
                                                        {fmt(o.remaining_tons)} t
                                                    </div>
                                                    <div className="text-xs text-[var(--color-text-muted)]">{fmt(o.remaining_rotations)} rot.</div>
                                                </td>
                                                <td className="px-4 py-3">
                                                    {o.pct === null ? (
                                                        <span className="text-xs text-[var(--color-text-muted)]">—</span>
                                                    ) : (
                                                        <div className="flex items-center gap-2">
                                                            <div className="flex-1 h-2 rounded-full bg-[var(--color-surface-hover)] overflow-hidden">
                                                                <div
                                                                    className={clsx('h-full rounded-full', done ? 'bg-emerald-500' : 'bg-[var(--color-primary)]')}
                                                                    style={{ width: `${pct}%` }}
                                                                />
                                                            </div>
                                                            <span className="text-xs font-semibold w-9 text-right">{pct}%</span>
                                                        </div>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 text-center whitespace-nowrap text-xs text-[var(--color-text-muted)]">
                                                    {o.working_trucks} actifs · {o.rested_trucks} repos
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </Card>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
