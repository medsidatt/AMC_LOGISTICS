import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import PeriodFilter from '@/components/dashboard/PeriodFilter';
import RatioCard from '@/components/dashboard/RatioCard';
import { formatNumber } from '@/utils/formatters';
import { Route, Clock, Scale, Fuel, Wrench, Gauge, AlertTriangle, Droplet } from 'lucide-react';

export interface TruckKpi {
    period: { from: string; to: string };
    rotations: { count: number; tonnage_delivered: number; tonnage_provider: number };
    cycle: { avg_days: number | null };
    weight_gap: { sum: number; violations: number; threshold: number };
    fuel_anomalies: { count: number; litres: number };
    fuel_per_rotation: number | null;
    load_rate: { rate: number; delivered: number; theoretical: number; capacity: number };
    fuel_yield: { litres_per_tonne: number | null; litres: number; tonnage: number };
    maintenance: { interval_km: number; km_since: number; remaining_km: number; progress: number; level: 'green' | 'orange' | 'red' };
}

interface Props {
    truckId: number;
    kpi: TruckKpi;
    filter: { from: string; to: string; preset: 'day' | 'week' | 'month' | 'year' | 'custom' };
}

function NumberCard({ label, value, unit, icon, color }: { label: string; value: string | number; unit?: string; icon: React.ReactNode; color: string }) {
    return (
        <div className="bg-[var(--color-surface)] rounded-xl border border-[var(--color-border)] p-5 shadow-[var(--shadow-sm)]">
            <div className="flex items-start justify-between mb-2">
                <p className="text-xs font-medium uppercase tracking-wider text-[var(--color-text-muted)]">{label}</p>
                <div
                    className="flex-shrink-0 w-9 h-9 rounded-lg flex items-center justify-center"
                    style={{ background: `${color}15`, color }}
                >
                    {icon}
                </div>
            </div>
            <div className="flex items-baseline gap-1.5">
                <span className="text-2xl font-bold text-[var(--color-text)]">{value}</span>
                {unit && <span className="text-sm font-medium text-[var(--color-text-secondary)]">{unit}</span>}
            </div>
        </div>
    );
}

function MaintenanceCard({ m }: { m: TruckKpi['maintenance'] }) {
    const colorMap = {
        red: 'var(--color-danger)',
        orange: 'var(--color-warning)',
        green: 'var(--color-success)',
    } as const;
    const color = colorMap[m.level];
    const labelMap = {
        red: 'Urgent',
        orange: 'À surveiller',
        green: 'OK',
    } as const;

    return (
        <Card
            className="lg:col-span-2"
            header={
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <Wrench size={16} style={{ color }} />
                        <span className="text-sm font-semibold">Maintenance</span>
                    </div>
                    <Badge variant={m.level === 'red' ? 'danger' : m.level === 'orange' ? 'warning' : 'success'}>
                        {labelMap[m.level]}
                    </Badge>
                </div>
            }
        >
            <div className="flex items-baseline justify-between mb-2">
                <div>
                    <p className="text-3xl font-bold" style={{ color }}>
                        {formatNumber(m.remaining_km, 0)} <span className="text-sm font-normal text-[var(--color-text-secondary)]">km restants</span>
                    </p>
                    <p className="text-xs text-[var(--color-text-muted)] mt-1">
                        {formatNumber(m.km_since, 0)} km depuis dernière maintenance / intervalle {formatNumber(m.interval_km, 0)} km
                    </p>
                </div>
                {m.level === 'red' && <AlertTriangle size={28} style={{ color }} />}
            </div>
            <div className="h-2 rounded-full bg-[var(--color-border)] overflow-hidden">
                <div
                    className="h-full rounded-full transition-all duration-500"
                    style={{ width: `${Math.min(100, m.progress * 100)}%`, background: color }}
                />
            </div>
        </Card>
    );
}

export default function TruckKpiSection({ truckId, kpi, filter }: Props) {
    const cycle = kpi.cycle.avg_days;
    return (
        <Card
            className="mb-6"
            header={
                <div className="flex items-center gap-2">
                    <Gauge size={16} />
                    <span className="text-sm font-semibold">Indicateurs de performance</span>
                </div>
            }
        >
            <PeriodFilter
                from={filter.from}
                to={filter.to}
                preset={filter.preset}
                routeName={`/trucks/${truckId}/show`}
            />

            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-1">
                <NumberCard
                    label="Nb rotations"
                    value={formatNumber(kpi.rotations.count)}
                    unit="rotations"
                    icon={<Route size={18} />}
                    color="var(--color-primary)"
                />
                <NumberCard
                    label="Temps de cycle moyen"
                    value={cycle === null ? '-' : formatNumber(cycle, 1)}
                    unit={cycle === null ? '' : 'jours / rotation'}
                    icon={<Clock size={18} />}
                    color="var(--color-info)"
                />
                <NumberCard
                    label="Écart tonnage cumulé"
                    value={`${kpi.weight_gap.sum >= 0 ? '+' : ''}${formatNumber(kpi.weight_gap.sum, 2)}`}
                    unit={`T · ${kpi.weight_gap.violations} dépass.`}
                    icon={<Scale size={18} />}
                    color={kpi.weight_gap.violations > 0 ? 'var(--color-warning)' : 'var(--color-success)'}
                />
                <NumberCard
                    label="Écart carburant"
                    value={formatNumber(kpi.fuel_anomalies.count)}
                    unit={`anomalie(s) · ${formatNumber(kpi.fuel_anomalies.litres, 1)} L`}
                    icon={<AlertTriangle size={18} />}
                    color={kpi.fuel_anomalies.count > 0 ? 'var(--color-danger)' : 'var(--color-success)'}
                />

                <NumberCard
                    label="Conso. par rotation"
                    value={kpi.fuel_per_rotation === null ? '-' : formatNumber(kpi.fuel_per_rotation, 1)}
                    unit={kpi.fuel_per_rotation === null ? '' : 'L / rotation'}
                    icon={<Fuel size={18} />}
                    color="var(--color-warning)"
                />
                <RatioCard
                    label="Taux de chargement"
                    ratio={kpi.load_rate.rate}
                    numerator={kpi.load_rate.delivered}
                    denominator={kpi.load_rate.theoretical}
                    numeratorLabel=" T"
                    denominatorLabel=" T théorique"
                    icon={<Gauge size={18} />}
                />
                <NumberCard
                    label="Rendement camion"
                    value={kpi.fuel_yield.litres_per_tonne === null ? '-' : formatNumber(kpi.fuel_yield.litres_per_tonne, 2)}
                    unit={kpi.fuel_yield.litres_per_tonne === null ? '' : 'L / T livrée'}
                    icon={<Droplet size={18} />}
                    color="var(--color-primary)"
                />
                <MaintenanceCard m={kpi.maintenance} />
            </div>
        </Card>
    );
}
