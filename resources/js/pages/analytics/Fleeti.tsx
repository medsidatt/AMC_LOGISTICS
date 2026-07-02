import { Head } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import AnalyticsTabs from '@/components/analytics/AnalyticsTabs';
import Card from '@/components/ui/Card';
import { Truck, Activity, Fuel, Droplets } from 'lucide-react';
import { clsx } from 'clsx';

const fmt = (v: number) => (Number(v) || 0).toLocaleString('fr-FR', { maximumFractionDigits: 0 });

interface Props {
    stats: {
        total_trucks: number; connected: number; synced_recently: number;
        total_fleet_km: number; avg_km: number; last_sync: string | null;
        trucks_with_fuel: number; total_fuel_litres: number; avg_fuel_litres: number;
    };
    fuelDistribution: { critical: number; low: number; medium: number; good: number };
    fuelData: Array<{ id: number; matricule: string; litres: number; total_km: number; last_synced: string | null }>;
    fuelHistory: Array<{ day: string; avg_litres: number; total_litres: number; trucks: number }>;
    dailyKm: Array<{ day: string; km: number; trucks: number }>;
    fleetTable: Array<{ id: number; matricule: string; total_km: number; fleeti_connected: boolean; fleeti_km: number | null; fuel_litres: number | null; last_synced: string | null }>;
}

export default function FleetiDashboard({ stats, fuelDistribution, fuelData, fuelHistory, dailyKm, fleetTable }: Props) {
    const [search, setSearch] = useState('');

    const hasFuelData = stats.trucks_with_fuel > 0;
    const filteredFleet = fleetTable.filter((t) => !search || t.matricule.toLowerCase().includes(search.toLowerCase()));

    return (
        <AuthenticatedLayout title="Fleeti & Carburant">
            <Head title="Fleeti & Carburant" />
            <AnalyticsTabs />

            {/* KPIs */}
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-6">
                <Card>
                    <div className="flex items-center gap-2 mb-1"><Activity size={16} className="text-[var(--color-info)]" /><span className="text-xs text-[var(--color-text-muted)] uppercase">Kilométrage</span></div>
                    <p className="text-2xl font-bold text-[var(--color-text)]">{fmt(stats.total_fleet_km)} km</p>
                    <p className="text-xs text-[var(--color-text-muted)]">Moy: {fmt(stats.avg_km)} km/camion</p>
                </Card>
                <Card>
                    <div className="flex items-center gap-2 mb-1"><Fuel size={16} className="text-amber-500" /><span className="text-xs text-[var(--color-text-muted)] uppercase">Carburant total</span></div>
                    <p className="text-2xl font-bold text-[var(--color-text)]">{hasFuelData ? `${fmt(stats.total_fuel_litres)} L` : '—'}</p>
                    <p className="text-xs text-[var(--color-text-muted)]">{hasFuelData ? `${stats.trucks_with_fuel} camions avec données` : 'Aucune donnée'}</p>
                </Card>
                <Card>
                    <div className="flex items-center gap-2 mb-1"><Droplets size={16} className="text-blue-500" /><span className="text-xs text-[var(--color-text-muted)] uppercase">Moyenne carburant</span></div>
                    <p className="text-2xl font-bold text-[var(--color-text)]">{hasFuelData ? `${stats.avg_fuel_litres} L` : '—'}</p>
                    <p className="text-xs text-[var(--color-text-muted)]">{hasFuelData ? 'par camion' : 'Aucune donnée'}</p>
                </Card>
            </div>

            <div className="grid lg:grid-cols-2 gap-6">
                {/* Fuel distribution */}
                <Card>
                    <div className="flex items-center gap-2 mb-4">
                        <Fuel size={18} className="text-amber-500" />
                        <h3 className="text-lg font-semibold text-[var(--color-text)]">Niveaux de carburant</h3>
                    </div>
                    <div className="grid grid-cols-4 gap-3">
                        {[
                            { label: 'Critique', value: fuelDistribution.critical, color: 'bg-red-500' },
                            { label: 'Bas', value: fuelDistribution.low, color: 'bg-amber-500' },
                            { label: 'Moyen', value: fuelDistribution.medium, color: 'bg-blue-500' },
                            { label: 'Bon', value: fuelDistribution.good, color: 'bg-emerald-500' },
                        ].map((d) => (
                            <div key={d.label} className="text-center p-3 rounded-lg bg-[var(--color-surface-hover)]">
                                <div className={clsx('w-3 h-3 rounded-full mx-auto mb-2', d.color)} />
                                <p className="text-xl font-bold text-[var(--color-text)]">{d.value}</p>
                                <p className="text-xs text-[var(--color-text-muted)]">{d.label}</p>
                            </div>
                        ))}
                    </div>
                </Card>

                {/* Fuel per truck */}
                <Card>
                    <div className="flex items-center gap-2 mb-4">
                        <Droplets size={18} className="text-blue-500" />
                        <h3 className="text-lg font-semibold text-[var(--color-text)]">Carburant par camion</h3>
                    </div>
                    {fuelData.length === 0 ? (
                        <div className="text-center py-8 text-[var(--color-text-muted)]">
                            <Fuel size={32} className="mx-auto mb-2 opacity-30" />
                            <p>Aucune donnée disponible</p>
                        </div>
                    ) : (
                        <div className="space-y-2 max-h-80 overflow-y-auto">
                            {[...fuelData].sort((a, b) => a.litres - b.litres).map((t) => (
                                <div key={t.id} className="flex items-center justify-between p-2 rounded-lg hover:bg-[var(--color-surface-hover)]">
                                    <a href={`/trucks/${t.id}/show-page`} className="text-sm font-medium text-[var(--color-primary)] hover:underline">{t.matricule}</a>
                                    <span className="text-sm font-mono text-[var(--color-text)]">{fmt(t.litres)} L</span>
                                </div>
                            ))}
                        </div>
                    )}
                </Card>
            </div>

            {/* Fuel history chart */}
            {fuelHistory.length > 0 && (
                <Card className="mt-6">
                    <h3 className="text-lg font-semibold text-[var(--color-text)] mb-4">Évolution carburant (30 jours)</h3>
                    <div className="flex items-end gap-1 h-32">
                        {fuelHistory.map((d, i) => {
                            const max = Math.max(...fuelHistory.map(x => x.avg_litres), 1);
                            const height = Math.max(4, (d.avg_litres / max) * 100);
                            return (
                                <div key={i} className="flex-1 flex flex-col items-center gap-1" title={`${d.day}: moy ${d.avg_litres}L · total ${fmt(d.total_litres)}L · ${d.trucks} camions`}>
                                    <div className="w-full rounded-t bg-amber-500/80 hover:bg-amber-500 transition-all cursor-default" style={{ height: `${height}%` }} />
                                    {i % 3 === 0 && <span className="text-[7px] text-[var(--color-text-muted)]">{d.day}</span>}
                                </div>
                            );
                        })}
                    </div>
                </Card>
            )}

            {/* Daily km evolution */}
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

            {/* Fleet counters */}
            <Card className="mt-6" padding={false}>
                <div className="p-5">
                    <div className="flex flex-wrap items-center gap-3 mb-4">
                        <div className="flex items-center gap-2">
                            <Truck size={18} className="text-[var(--color-primary)]" />
                            <h3 className="text-lg font-semibold text-[var(--color-text)]">Compteurs par camion</h3>
                        </div>
                        <input type="text" value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Rechercher..."
                            className="ml-auto px-3 py-1.5 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm w-40" />
                    </div>
                    <div className="overflow-x-auto rounded-lg border border-[var(--color-border)]">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="bg-[var(--color-surface-hover)]">
                                    <th className="px-3 py-2 text-left text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Camion</th>
                                    <th className="px-3 py-2 text-right text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Compteur</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[var(--color-border)]">
                                {filteredFleet.length === 0 ? (
                                    <tr><td colSpan={2} className="px-3 py-8 text-center text-[var(--color-text-muted)]">Aucun camion</td></tr>
                                ) : filteredFleet.map((t) => (
                                    <tr key={t.id} className="hover:bg-[var(--color-surface-hover)]">
                                        <td className="px-3 py-2"><a href={`/trucks/${t.id}/show-page`} className="text-[var(--color-primary)] hover:underline font-medium">{t.matricule}</a></td>
                                        <td className="px-3 py-2 text-right font-mono text-[var(--color-text)]">{fmt(t.total_km)} km</td>
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
