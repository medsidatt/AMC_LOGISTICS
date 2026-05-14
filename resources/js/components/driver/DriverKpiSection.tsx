import Card from '@/components/ui/Card';
import PeriodFilter from '@/components/dashboard/PeriodFilter';
import { formatNumber } from '@/utils/formatters';
import { Route, Clock, Fuel, Scale, ShieldCheck, Gauge, Trophy } from 'lucide-react';

export interface DriverKpi {
    period: { from: string; to: string };
    rotations: { done: number; planned: number; score: number; weight: number };
    cycle: { avg_days: number | null; score: number; weight: number };
    fuel_gap: { anomalies: number; litres: number; score: number; weight: number };
    weight_gap: { sum: number; violations: number; threshold: number; score: number; weight: number };
    discipline: { score: number; weight: number };
    global_score: number;
}

interface Props {
    driverId: number;
    kpi: DriverKpi;
    filter: { from: string; to: string; preset: 'day' | 'week' | 'month' | 'year' | 'custom' };
}

function scoreColor(score: number): string {
    if (score >= 75) return 'var(--color-success)';
    if (score >= 50) return 'var(--color-warning)';
    return 'var(--color-danger)';
}

function ScoreCard({
    label,
    score,
    weight,
    icon,
    primary,
    detail,
}: {
    label: string;
    score: number;
    weight: number;
    icon: React.ReactNode;
    primary: React.ReactNode;
    detail?: React.ReactNode;
}) {
    const color = scoreColor(score);
    return (
        <div className="bg-[var(--color-surface)] rounded-xl border border-[var(--color-border)] p-5 shadow-[var(--shadow-sm)]">
            <div className="flex items-start justify-between mb-3">
                <div>
                    <p className="text-xs font-medium uppercase tracking-wider text-[var(--color-text-muted)]">{label}</p>
                    <p className="text-[10px] text-[var(--color-text-muted)] mt-0.5">Poids {formatNumber(weight * 100, 0)} %</p>
                </div>
                <div
                    className="flex-shrink-0 w-9 h-9 rounded-lg flex items-center justify-center"
                    style={{ background: `${color}15`, color }}
                >
                    {icon}
                </div>
            </div>
            <div className="text-2xl font-bold text-[var(--color-text)] mb-1">{primary}</div>
            {detail && <p className="text-xs text-[var(--color-text-muted)] mb-2">{detail}</p>}
            <div className="flex items-center justify-between text-xs">
                <span className="text-[var(--color-text-muted)]">Score</span>
                <span className="font-bold" style={{ color }}>{formatNumber(score, 1)}/100</span>
            </div>
            <div className="mt-1 h-1.5 rounded-full bg-[var(--color-border)] overflow-hidden">
                <div
                    className="h-full rounded-full transition-all duration-500"
                    style={{ width: `${Math.max(0, Math.min(100, score))}%`, background: color }}
                />
            </div>
        </div>
    );
}

export default function DriverKpiSection({ driverId, kpi, filter }: Props) {
    const globalColor = scoreColor(kpi.global_score);

    return (
        <Card
            className="mb-6"
            header={
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <Gauge size={16} />
                        <span className="text-sm font-semibold">Indicateurs de performance</span>
                    </div>
                    <div className="flex items-center gap-2">
                        <Trophy size={14} style={{ color: globalColor }} />
                        <span className="text-sm font-bold" style={{ color: globalColor }}>
                            Score global : {formatNumber(kpi.global_score, 1)} / 100
                        </span>
                    </div>
                </div>
            }
        >
            <PeriodFilter
                from={filter.from}
                to={filter.to}
                preset={filter.preset}
                routeName={`/drivers/${driverId}/show-page`}
            />

            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mt-1">
                <ScoreCard
                    label="Nb rotations"
                    score={kpi.rotations.score}
                    weight={kpi.rotations.weight}
                    icon={<Route size={18} />}
                    primary={<>{formatNumber(kpi.rotations.done)} <span className="text-sm font-normal text-[var(--color-text-secondary)]">/ {formatNumber(kpi.rotations.planned, 1)} planif.</span></>}
                    detail={`Effectuées sur planifiées`}
                />
                <ScoreCard
                    label="Temps de cycle"
                    score={kpi.cycle.score}
                    weight={kpi.cycle.weight}
                    icon={<Clock size={18} />}
                    primary={kpi.cycle.avg_days === null ? '-' : <>{formatNumber(kpi.cycle.avg_days, 2)} <span className="text-sm font-normal text-[var(--color-text-secondary)]">jours</span></>}
                    detail="Moyenne entre rotations"
                />
                <ScoreCard
                    label="Écart carburant"
                    score={kpi.fuel_gap.score}
                    weight={kpi.fuel_gap.weight}
                    icon={<Fuel size={18} />}
                    primary={<>{formatNumber(kpi.fuel_gap.anomalies)} <span className="text-sm font-normal text-[var(--color-text-secondary)]">anomalie(s)</span></>}
                    detail={`${formatNumber(kpi.fuel_gap.litres, 1)} L perdus`}
                />
                <ScoreCard
                    label="Écart tonnage"
                    score={kpi.weight_gap.score}
                    weight={kpi.weight_gap.weight}
                    icon={<Scale size={18} />}
                    primary={<>{formatNumber(kpi.weight_gap.violations)} <span className="text-sm font-normal text-[var(--color-text-secondary)]">dépassement(s)</span></>}
                    detail={`${kpi.weight_gap.sum >= 0 ? '+' : ''}${formatNumber(kpi.weight_gap.sum, 2)} T cumulé · seuil ${formatNumber(kpi.weight_gap.threshold, 2)} T`}
                />
                <ScoreCard
                    label="Discipline"
                    score={kpi.discipline.score}
                    weight={kpi.discipline.weight}
                    icon={<ShieldCheck size={18} />}
                    primary={<>{formatNumber(kpi.discipline.score, 0)} <span className="text-sm font-normal text-[var(--color-text-secondary)]">/ 100</span></>}
                    detail="Notes + checklists + issues + écarts"
                />
            </div>
        </Card>
    );
}
