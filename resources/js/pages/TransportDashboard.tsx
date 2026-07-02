import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import KpiCard from '@/components/dashboard/KpiCard';
import KpiGrid from '@/components/dashboard/KpiGrid';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import Select from '@/components/ui/Select';
import DateRangePicker from '@/components/ui/DateRangePicker';
import { useFilters } from '@/hooks/useFilters';
import { usePolling } from '@/hooks/usePolling';
import { formatNumber } from '@/utils/formatters';
import { Weight, Scale, AlertTriangle, TrendingDown, Filter, RotateCcw } from 'lucide-react';

interface FilterOption {
    value: number;
    label: string;
}

interface Props {
    filters: Record<string, string | null>;
    filterOptions: {
        transporters: FilterOption[];
        trucks: FilterOption[];
        drivers: FilterOption[];
        providers: FilterOption[];
    };
    kpis: {
        totalTransported: number;
        totalReceived: number;
        totalDifference: number;
        totalPoidsAnomalies: number;
        totalCount: number;
        rotationsPerdues: number;
        rotationsNormal: number;
        pctReceived: number;
        pctDifference: number;
        pctAnomalies: number;
        pctPerdues: number;
        pctNormal: number;
        suspiciousDrivers: number;
    };
    months: string[];
    monthlyWeights: number[];
    timelineEvents: Array<{
        truck: string;
        driver: string;
        start: string;
        end: string;
        reference: string;
        hasConflict: boolean;
    }>;
}

export default function TransportDashboard({ filters: initialFilters, filterOptions, kpis }: Props) {
    const { filters, updateFilter, applyFilters, resetFilters, loading } = useFilters(
        initialFilters as any,
        '/transport_tracking/dashboard',
    );
    usePolling({ interval: 60, only: ['kpis'] });

    const hasActivity = kpis.totalCount > 0;

    return (
        <AuthenticatedLayout title="Dashboard Transport">
            <Head title="Dashboard Transport" />

            {/* Filters */}
            <Card className="mb-6">
                <div className="flex flex-wrap items-end gap-3">
                    <DateRangePicker
                        startDate={filters.start_date ?? ''}
                        endDate={filters.end_date ?? ''}
                        onStartChange={(v) => updateFilter('start_date', v)}
                        onEndChange={(v) => updateFilter('end_date', v)}
                    />
                    <Select
                        label="Transporteur"
                        options={filterOptions.transporters as any}
                        value={filters.transporter_id}
                        onChange={(v) => updateFilter('transporter_id', v)}
                        className="w-44"
                    />
                    <Select
                        label="Camion"
                        options={filterOptions.trucks as any}
                        value={filters.truck_id}
                        onChange={(v) => updateFilter('truck_id', v)}
                        className="w-44"
                    />
                    <Select
                        label="Conducteur"
                        options={filterOptions.drivers as any}
                        value={filters.driver_id}
                        onChange={(v) => updateFilter('driver_id', v)}
                        className="w-44"
                    />
                    <Select
                        label="Fournisseur"
                        options={filterOptions.providers as any}
                        value={filters.provider_id}
                        onChange={(v) => updateFilter('provider_id', v)}
                        className="w-44"
                    />
                    <div className="flex items-end gap-2">
                        <Button onClick={() => applyFilters()} loading={loading} icon={<Filter size={14} />}>
                            Filtrer
                        </Button>
                        <Button variant="ghost" onClick={resetFilters} icon={<RotateCcw size={14} />}>
                            Reset
                        </Button>
                    </div>
                </div>
            </Card>

            {!hasActivity ? (
                <Card>
                    <div className="py-12 text-center text-sm text-[var(--color-text-muted)]">
                        Aucune activité de transport sur cette période.
                    </div>
                </Card>
            ) : (
                <>
                    {/* Transport operations */}
                    <KpiGrid>
                        <KpiCard
                            label="Poids transporté"
                            value={kpis.totalTransported}
                            unit="t"
                            icon={<Weight size={22} />}
                            color="var(--color-primary)"
                        />
                        <KpiCard
                            label="Poids reçu"
                            value={kpis.totalReceived}
                            unit="t"
                            icon={<Scale size={22} />}
                            color="var(--color-success)"
                        />
                        <KpiCard
                            label="Poids perdu"
                            value={kpis.totalDifference}
                            unit="t"
                            icon={<TrendingDown size={22} />}
                            color="var(--color-danger)"
                        />
                        <KpiCard
                            label="Anomalies poids"
                            value={kpis.totalPoidsAnomalies}
                            icon={<AlertTriangle size={22} />}
                            color="var(--color-warning)"
                        />
                    </KpiGrid>

                    {/* Rotations — current + attention */}
                    <div className="grid grid-cols-2 gap-4 mt-4">
                        <div className="bg-[var(--color-surface)] rounded-xl border border-[var(--color-border)] p-4 text-center">
                            <p className="text-2xl font-bold text-[var(--color-text)]">{formatNumber(kpis.totalCount)}</p>
                            <p className="text-xs text-[var(--color-text-muted)] mt-1">Rotations totales</p>
                        </div>
                        <div className="bg-[var(--color-surface)] rounded-xl border border-[var(--color-border)] p-4 text-center">
                            <p className="text-2xl font-bold text-[var(--color-danger)]">{formatNumber(kpis.rotationsPerdues)}</p>
                            <p className="text-xs text-[var(--color-text-muted)] mt-1">Rotations perdues</p>
                        </div>
                    </div>
                </>
            )}
        </AuthenticatedLayout>
    );
}
