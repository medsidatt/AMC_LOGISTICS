import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import AnalyticsTabs from '@/components/analytics/AnalyticsTabs';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import { Satellite, Truck, Wifi, WifiOff, Activity, Fuel, AlertTriangle, Droplets } from 'lucide-react';
import { clsx } from 'clsx';

const fmt = (v: number) => (Number(v) || 0).toLocaleString('fr-FR', { maximumFractionDigits: 0 });

interface Props {
    stats: {
        total_trucks: number; connected: number; synced_recently: number;
        total_fleet_km: number; avg_km: number; last_sync: string | null;
        trucks_with_fuel: number; avg_fuel: number;
    };
    fuelDistribution: { critical: number; low: number; medium: number; good: number };
    fuelData: Array<{ id: number; matricule: string; fuel_level: number; total_km: number; last_synced: string | null }>;
    dailyKm: Array<{ day: string; km: number; trucks: number }>;
    fleetTable: Array<{ id: number; matricule: string; total_km: number; fleeti_connected: boolean; fleeti_km: number | null; fuel_level: number | null; last_synced: string | null }>;
}

function FuelGauge({ level }: { level: number }) {
    const color = level < 15 ? 'bg-red-500' : level < 30 ? 'bg-amber-500' : level < 60 ? 'bg-blue-500' : 'bg-emerald-500';
    return (
        <div className="flex items-center gap-2">
            <div className="w-16 h-2 rounded-full bg-[var(--color-surface-hover)] overflow-hidden">
                <div className={clsx('h-full rounded-full transition-all', color)} style={{ width: `${Math.min(100, level)}%` }} />
            </div>
            <span className="text-xs font-mono text-[var(--color-text)]">{level}%</span>
        </div>
    );
}

export default function FleetiDashboard({ stats, fuelDistribution, fuelData, dailyKm, fleetTable }: Props) {
    return (
        <AuthenticatedLayout title="Fleeti & Carburant">
            <Head title="Fleeti & Carburant" />
            <AnalyticsTabs />

            {/* Fleet KPIs */}
            <div className="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3 mb-6">
                {[
                    { label: 'Camions', value: stats.total_trucks, icon: <Truck size={16} />, color: 'text-[var(--color-primary)]' },
                    { label: 'GPS connectés', value: stats.connected, icon: <Wifi size={16} />, color: 'text-emerald-500' },
                    { label: 'Sync < 2h', value: stats.synced_recently, icon: <Activity size={16} />, color: 'text-[var(--color-info)]' },
                    { label: 'Km flotte', value: fmt(stats.total_fleet_km), icon: null, color: '' },
                    { label: 'Km moy.', value: fmt(stats.avg_km), icon: null, color: '' },
                    { label: 'Avec carburant', value: stats.trucks_with_fuel, icon: <Fuel size={16} />, color: 'text-amber-500' },
                    { label: 'Carburant moy.', value: `${stats.avg_fuel}%`, icon: <Droplets size={16} />, color: 'text-blue-500' },
                    { label: 'Dernière sync', value: stats.last_sync ?? '-', icon: null, color: '', small: true },
                ].map((kpi, i) => (
                    <div key={i} className="p-3 rounded-lg bg-[var(--color-surface)] border border-[var(--color-border)] text-center">
                        {kpi.icon && <div className={clsx('mx-auto mb-1', kpi.color)}>{kpi.icon}</div>}
                        <p className={clsx('font-bold text-[var(--color-text)]', (kpi as any).small ? 'text-xs' : 'text-lg')}>{kpi.value}</p>
                        <p className="text-[10px] text-[var(--color-text-muted)] uppercase">{kpi.label}</p>
                    </div>
                ))}
            </div>

            <div className="grid lg:grid-cols-2 gap-6">
                {/* Fuel distribution */}
                <Card>
                    <div className="flex items-center gap-2 mb-4">
                        <Fuel size={18} className="text-amber-500" />
                        <h3 className="text-lg font-semibold text-[var(--color-text)]">Distribution carburant</h3>
                    </div>
                    <div className="grid grid-cols-4 gap-3 mb-4">
                        {[
                            { label: 'Critique', value: fuelDistribution.critical, color: 'bg-red-500', desc: '< 15%' },
                            { label: 'Bas', value: fuelDistribution.low, color: 'bg-amber-500', desc: '15-30%' },
                            { label: 'Moyen', value: fuelDistribution.medium, color: 'bg-blue-500', desc: '30-60%' },
                            { label: 'Bon', value: fuelDistribution.good, color: 'bg-emerald-500', desc: '> 60%' },
                        ].map((d) => (
                            <div key={d.label} className="text-center p-3 rounded-lg bg-[var(--color-surface-hover)]">
                                <div className={clsx('w-3 h-3 rounded-full mx-auto mb-2', d.color)} />
                                <p className="text-xl font-bold text-[var(--color-text)]">{d.value}</p>
                                <p className="text-xs text-[var(--color-text-muted)]">{d.label}</p>
                                <p className="text-[10px] text-[var(--color-text-muted)]">{d.desc}</p>
                            </div>
                        ))}
                    </div>
                    {fuelDistribution.critical > 0 && (
                        <div className="flex items-center gap-2 p-3 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
                            <AlertTriangle size={16} className="text-red-500" />
                            <span className="text-sm text-red-700 dark:text-red-300">{fuelDistribution.critical} camion(s) en niveau critique !</span>
                        </div>
                    )}
                </Card>

                {/* Fuel per truck */}
                <Card>
                    <div className="flex items-center gap-2 mb-4">
                        <Droplets size={18} className="text-blue-500" />
                        <h3 className="text-lg font-semibold text-[var(--color-text)]">Niveau carburant par camion</h3>
                    </div>
                    {fuelData.length === 0 ? (
                        <p className="text-center py-8 text-[var(--color-text-muted)]">Aucune donnée carburant disponible</p>
                    ) : (
                        <div className="space-y-2 max-h-80 overflow-y-auto">
                            {fuelData.sort((a, b) => a.fuel_level - b.fuel_level).map((t) => (
                                <div key={t.id} className="flex items-center justify-between p-2 rounded-lg hover:bg-[var(--color-surface-hover)]">
                                    <a href={`/trucks/${t.id}/show-page`} className="text-sm font-medium text-[var(--color-primary)] hover:underline w-24">{t.matricule}</a>
                                    <FuelGauge level={t.fuel_level} />
                                    <span className="text-xs text-[var(--color-text-muted)] w-20 text-right">{fmt(t.total_km)} km</span>
                                </div>
                            ))}
                        </div>
                    )}
                </Card>
            </div>

            {/* Daily km bar chart */}
            {dailyKm.length > 0 && (
                <Card className="mt-6">
                    <h3 className="text-lg font-semibold text-[var(--color-text)] mb-4">Évolution kilométrique (30 jours)</h3>
                    <div className="flex items-end gap-1 h-32">
                        {dailyKm.map((d, i) => {
                            const max = Math.max(...dailyKm.map(x => x.km), 1);
                            const height = Math.max(4, (d.km / max) * 100);
                            return (
                                <div key={i} className="flex-1 flex flex-col items-center gap-1" title={`${d.day}: ${fmt(d.km)} km — ${d.trucks} camions`}>
                                    <div className="w-full rounded-t bg-[var(--color-primary)]/80 hover:bg-[var(--color-primary)] transition-all cursor-default" style={{ height: `${height}%` }} />
                                    {i % 3 === 0 && <span className="text-[7px] text-[var(--color-text-muted)]">{d.day}</span>}
                                </div>
                            );
                        })}
                    </div>
                </Card>
            )}

            {/* Full fleet table */}
            <Card className="mt-6" padding={false}>
                <div className="p-5">
                    <div className="flex items-center gap-2 mb-4">
                        <Satellite size={18} className="text-[var(--color-primary)]" />
                        <h3 className="text-lg font-semibold text-[var(--color-text)]">Flotte complète</h3>
                    </div>
                    <div className="overflow-x-auto rounded-lg border border-[var(--color-border)]">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="bg-[var(--color-surface-hover)]">
                                    <th className="px-3 py-2 text-left text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Camion</th>
                                    <th className="px-3 py-2 text-right text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Compteur</th>
                                    <th className="px-3 py-2 text-right text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Fleeti km</th>
                                    <th className="px-3 py-2 text-center text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Carburant</th>
                                    <th className="px-3 py-2 text-center text-xs font-semibold uppercase text-[var(--color-text-secondary)]">GPS</th>
                                    <th className="px-3 py-2 text-left text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Dernière sync</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[var(--color-border)]">
                                {fleetTable.map((t) => (
                                    <tr key={t.id} className="hover:bg-[var(--color-surface-hover)]">
                                        <td className="px-3 py-2"><a href={`/trucks/${t.id}/show-page`} className="text-[var(--color-primary)] hover:underline font-medium">{t.matricule}</a></td>
                                        <td className="px-3 py-2 text-right font-mono text-[var(--color-text)]">{fmt(t.total_km)} km</td>
                                        <td className="px-3 py-2 text-right font-mono text-[var(--color-text)]">{t.fleeti_km != null ? `${fmt(t.fleeti_km)} km` : '-'}</td>
                                        <td className="px-3 py-2">{t.fuel_level != null ? <FuelGauge level={t.fuel_level} /> : <span className="text-[var(--color-text-muted)] text-center block">-</span>}</td>
                                        <td className="px-3 py-2 text-center">{t.fleeti_connected ? <Wifi size={14} className="inline text-emerald-500" /> : <WifiOff size={14} className="inline text-[var(--color-text-muted)]" />}</td>
                                        <td className="px-3 py-2 text-[var(--color-text-secondary)]">{t.last_synced ?? '-'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </Card>
        </AuthenticatedLayout>
    );
}
