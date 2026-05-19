import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import { ChevronLeft, ChevronRight, Play, BarChart3 } from 'lucide-react';

interface NameRef { id: number; name?: string; matricule?: string; code?: string }

interface Demand {
    id: number;
    project: NameRef | null;
    provider: NameRef | null;
    client_name: string | null;
    required_tons: number;
    required_trucks: number | null;
    product: string | null;
    priority: number;
    priority_label: string;
    allocated_tons: number;
    coverage_rate: number;
}

interface Assignment {
    id: number;
    truck: NameRef | null;
    driver: NameRef | null;
    project: NameRef | null;
    provider: NameRef | null;
    planned_date: string;
    planned_rotations: number;
    planned_tonnage: number;
    status: string;
    client_demand_plan_id: number | null;
}

interface RestWindow {
    id: number;
    truck: NameRef | null;
    start_date: string;
    end_date: string;
    reason: string;
    reason_label: string;
    notes: string | null;
}

interface Capacity {
    active_trucks_count: number;
    total_weekly_capacity_t: number;
    total_allocated_t: number;
    capacity_margin_t: number;
}

interface Props {
    weekStart: string;
    weekEnd: string;
    capacity: Capacity;
    demands: Demand[];
    assignments: Assignment[];
    restWindows: RestWindow[];
}

function shiftWeek(weekStart: string, days: number): string {
    const d = new Date(weekStart + 'T00:00:00');
    d.setDate(d.getDate() + days);
    return d.toISOString().slice(0, 10);
}

function listDays(weekStart: string): string[] {
    const out: string[] = [];
    const base = new Date(weekStart + 'T00:00:00');
    for (let i = 0; i < 7; i++) {
        const d = new Date(base);
        d.setDate(d.getDate() + i);
        out.push(d.toISOString().slice(0, 10));
    }
    return out;
}

const dayLabel = (iso: string) => {
    const d = new Date(iso + 'T00:00:00');
    return d.toLocaleDateString('fr-FR', { weekday: 'short', day: '2-digit', month: '2-digit' });
};

export default function OptimizationIndex({ weekStart, weekEnd, capacity, demands, assignments, restWindows }: Props) {
    const [running, setRunning] = useState(false);
    const days = useMemo(() => listDays(weekStart), [weekStart]);

    const goto = (next: string) => router.get('/logistics/optimization', { week: next }, { preserveState: false });

    const runOptimizer = () => {
        if (!confirm('Recalculer le plan de la semaine ? Les affectations planifiées non démarrées seront remplacées.')) return;
        setRunning(true);
        router.post('/logistics/optimization/run', { week_start: weekStart }, {
            onFinish: () => setRunning(false),
        });
    };

    const trucks = useMemo(() => {
        const map = new Map<number, string>();
        assignments.forEach((a) => { if (a.truck) map.set(a.truck.id, a.truck.matricule ?? '—'); });
        restWindows.forEach((r) => { if (r.truck) map.set(r.truck.id, r.truck.matricule ?? '—'); });
        return Array.from(map.entries()).sort((a, b) => a[1].localeCompare(b[1]));
    }, [assignments, restWindows]);

    const cellFor = (truckId: number, dayIso: string) => {
        const assign = assignments.find((a) => a.truck?.id === truckId && a.planned_date === dayIso);
        if (assign) {
            return (
                <div className="rounded bg-emerald-100 dark:bg-emerald-900/30 px-2 py-1 text-xs">
                    <div className="font-medium">{assign.planned_tonnage.toFixed(0)} t · {assign.planned_rotations} rot.</div>
                    <div className="text-[var(--color-text-muted)] truncate">{assign.project?.code ?? assign.project?.name ?? '—'}</div>
                </div>
            );
        }
        const rest = restWindows.find((r) => r.truck?.id === truckId && dayIso >= r.start_date && dayIso <= r.end_date);
        if (rest) {
            return (
                <div className="rounded bg-amber-100 dark:bg-amber-900/30 px-2 py-1 text-xs">
                    <div className="font-medium">Repos</div>
                    <div className="text-[var(--color-text-muted)] truncate">{rest.reason_label}</div>
                </div>
            );
        }
        return <div className="rounded bg-[var(--color-surface-2)] px-2 py-1 text-xs text-[var(--color-text-muted)]">—</div>;
    };

    return (
        <AuthenticatedLayout title="Optimisation de la flotte">
            <Head title="Optimisation de la flotte" />

            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                <div className="flex items-center gap-2">
                    <Button variant="secondary" icon={<ChevronLeft size={16} />} onClick={() => goto(shiftWeek(weekStart, -7))}>Précédente</Button>
                    <Button variant="secondary" icon={<ChevronRight size={16} />} onClick={() => goto(shiftWeek(weekStart, 7))}>Suivante</Button>
                    <span className="text-sm text-[var(--color-text-muted)] ml-2">{weekStart} → {weekEnd}</span>
                </div>
                <div className="flex items-center gap-2">
                    <Button variant="secondary" icon={<BarChart3 size={16} />} onClick={() => router.get('/logistics/optimization/capacity', { week: weekStart })}>
                        Capacité détaillée
                    </Button>
                    <Button icon={<Play size={16} />} onClick={runOptimizer} loading={running}>
                        Lancer l'optimiseur
                    </Button>
                </div>
            </div>

            <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                <Card>
                    <div className="text-xs text-[var(--color-text-muted)]">Camions actifs</div>
                    <div className="text-2xl font-semibold">{capacity.active_trucks_count}</div>
                </Card>
                <Card>
                    <div className="text-xs text-[var(--color-text-muted)]">Capacité semaine</div>
                    <div className="text-2xl font-semibold">{capacity.total_weekly_capacity_t.toLocaleString('fr-FR')} t</div>
                </Card>
                <Card>
                    <div className="text-xs text-[var(--color-text-muted)]">Alloué</div>
                    <div className="text-2xl font-semibold">{capacity.total_allocated_t.toLocaleString('fr-FR')} t</div>
                </Card>
                <Card>
                    <div className="text-xs text-[var(--color-text-muted)]">Marge</div>
                    <div className={`text-2xl font-semibold ${capacity.capacity_margin_t < 0 ? 'text-[var(--color-danger)]' : ''}`}>
                        {capacity.capacity_margin_t.toLocaleString('fr-FR')} t
                    </div>
                </Card>
            </div>

            <Card>
                <h2 className="text-lg font-semibold mb-3">Demandes client</h2>
                {demands.length === 0 ? (
                    <div className="text-sm text-[var(--color-text-muted)]">
                        Aucune demande saisie pour cette semaine.{' '}
                        <a href="/logistics/demands/create" className="text-[var(--color-primary)] underline">Ajouter</a>
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-left text-xs text-[var(--color-text-muted)]">
                                    <th className="py-2">Priorité</th>
                                    <th>Client / Projet</th>
                                    <th>Carrière</th>
                                    <th>Produit</th>
                                    <th className="text-right">Demande (t)</th>
                                    <th className="text-right">Camions</th>
                                    <th className="text-right">Alloué (t)</th>
                                    <th className="text-right">Couverture</th>
                                </tr>
                            </thead>
                            <tbody>
                                {demands.map((d) => {
                                    const pct = Math.round(d.coverage_rate * 100);
                                    const variant = pct >= 100 ? 'success' : pct >= 70 ? 'warning' : 'danger';
                                    return (
                                        <tr key={d.id} className="border-t border-[var(--color-border)]">
                                            <td className="py-2"><Badge variant="muted">{d.priority_label}</Badge></td>
                                            <td>{d.project?.code ?? d.project?.name ?? d.client_name ?? '—'}</td>
                                            <td>{d.provider?.name ?? 'Libre'}</td>
                                            <td>{d.product ?? '—'}</td>
                                            <td className="text-right">{d.required_tons.toLocaleString('fr-FR')}</td>
                                            <td className="text-right">{d.required_trucks ?? '—'}</td>
                                            <td className="text-right">{d.allocated_tons.toLocaleString('fr-FR')}</td>
                                            <td className="text-right"><Badge variant={variant}>{pct} %</Badge></td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
            </Card>

            <Card>
                <h2 className="text-lg font-semibold mb-3">Plan hebdomadaire</h2>
                {trucks.length === 0 ? (
                    <div className="text-sm text-[var(--color-text-muted)]">
                        Aucune affectation ni repos planifié pour cette semaine. Lancez l'optimiseur pour générer le plan.
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-left text-xs text-[var(--color-text-muted)]">
                                    <th className="py-2 pr-2 sticky left-0 bg-[var(--color-surface)]">Camion</th>
                                    {days.map((d) => <th key={d} className="px-2">{dayLabel(d)}</th>)}
                                </tr>
                            </thead>
                            <tbody>
                                {trucks.map(([truckId, matricule]) => (
                                    <tr key={truckId} className="border-t border-[var(--color-border)]">
                                        <td className="py-2 pr-2 font-medium sticky left-0 bg-[var(--color-surface)]">{matricule}</td>
                                        {days.map((d) => (
                                            <td key={d} className="px-1 py-1 align-top w-32">{cellFor(truckId, d)}</td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Card>
        </AuthenticatedLayout>
    );
}
