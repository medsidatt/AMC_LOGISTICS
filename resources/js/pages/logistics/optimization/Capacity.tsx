import { Head, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import DataTable from '@/components/ui/DataTable';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import { ChevronLeft, ChevronRight } from 'lucide-react';

interface TruckRow {
    truck_id: number;
    matricule: string;
    is_available: boolean;
    effective_daily_capacity_t: number;
    days_available: number;
    days_rest: number;
    days_assigned: number;
    weekly_capacity_t: number;
    allocated_t: number;
    utilization_rate: number;
}

interface CapacityPayload {
    week_start: string;
    week_end: string;
    active_trucks_count: number;
    total_weekly_capacity_t: number;
    total_allocated_t: number;
    capacity_margin_t: number;
    trucks: TruckRow[];
}

interface Props {
    weekStart: string;
    weekEnd: string;
    capacity: CapacityPayload;
}

function shiftWeek(weekStart: string, days: number): string {
    const d = new Date(weekStart + 'T00:00:00');
    d.setDate(d.getDate() + days);
    return d.toISOString().slice(0, 10);
}

export default function OptimizationCapacity({ weekStart, weekEnd, capacity }: Props) {
    const goto = (next: string) => router.get('/logistics/optimization/capacity', { week: next }, { preserveState: false });

    return (
        <AuthenticatedLayout title="Capacité de la flotte">
            <Head title="Capacité de la flotte" />

            <div className="mb-4 flex items-center justify-between">
                <div className="flex items-center gap-2">
                    <Button variant="secondary" icon={<ChevronLeft size={16} />} onClick={() => goto(shiftWeek(weekStart, -7))}>Semaine précédente</Button>
                    <Button variant="secondary" icon={<ChevronRight size={16} />} onClick={() => goto(shiftWeek(weekStart, 7))}>Semaine suivante</Button>
                </div>
                <div className="text-sm text-[var(--color-text-muted)]">
                    {weekStart} → {weekEnd}
                </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                <Card>
                    <div className="text-xs text-[var(--color-text-muted)]">Camions actifs</div>
                    <div className="text-2xl font-semibold">{capacity.active_trucks_count}</div>
                </Card>
                <Card>
                    <div className="text-xs text-[var(--color-text-muted)]">Capacité hebdomadaire</div>
                    <div className="text-2xl font-semibold">{capacity.total_weekly_capacity_t.toLocaleString('fr-FR')} t</div>
                </Card>
                <Card>
                    <div className="text-xs text-[var(--color-text-muted)]">Tonnage alloué</div>
                    <div className="text-2xl font-semibold">{capacity.total_allocated_t.toLocaleString('fr-FR')} t</div>
                </Card>
                <Card>
                    <div className="text-xs text-[var(--color-text-muted)]">Marge</div>
                    <div className={`text-2xl font-semibold ${capacity.capacity_margin_t < 0 ? 'text-[var(--color-danger)]' : ''}`}>
                        {capacity.capacity_margin_t.toLocaleString('fr-FR')} t
                    </div>
                </Card>
            </div>

            <Card padding={false}>
                <div className="p-5">
                    <DataTable
                        data={capacity.trucks}
                        columns={[
                            { key: 'matricule', label: 'Camion' },
                            { key: 'effective_daily_capacity_t', label: 'Capacité/jour (t)', render: (r) => r.effective_daily_capacity_t.toFixed(1) },
                            { key: 'days_available', label: 'Jours dispo' },
                            { key: 'days_assigned', label: 'Jours alloués' },
                            { key: 'days_rest', label: 'Jours repos' },
                            { key: 'weekly_capacity_t', label: 'Capacité semaine (t)', render: (r) => r.weekly_capacity_t.toFixed(1) },
                            { key: 'allocated_t', label: 'Alloué (t)', render: (r) => r.allocated_t.toFixed(1) },
                            {
                                key: 'utilization_rate', label: 'Utilisation',
                                render: (r) => {
                                    const pct = Math.round(r.utilization_rate * 100);
                                    const variant = pct >= 85 ? 'success' : pct >= 50 ? 'warning' : 'muted';
                                    return <Badge variant={variant}>{pct} %</Badge>;
                                },
                            },
                        ]}
                        searchable
                        searchKeys={['matricule']}
                    />
                </div>
            </Card>
        </AuthenticatedLayout>
    );
}
