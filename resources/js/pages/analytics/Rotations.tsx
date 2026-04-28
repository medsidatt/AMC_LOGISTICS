import { Head, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import AnalyticsTabs from '@/components/analytics/AnalyticsTabs';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import FormInput from '@/components/ui/FormInput';
import FormSelect from '@/components/ui/FormSelect';
import KpiCard from '@/components/dashboard/KpiCard';
import KpiGrid from '@/components/dashboard/KpiGrid';
import { Weight, Scale, TrendingDown, BarChart3, Truck, Users, Package, X } from 'lucide-react';
import { clsx } from 'clsx';

const fmt = (v: number) => (Number(v) || 0).toLocaleString('fr-FR', { maximumFractionDigits: 0 });
const fmtT = (v: number) => `${fmt(v)} T`;

interface Props {
    totalTrips: number;
    totalProviderWeight: number;
    totalClientWeight: number;
    totalGap: number;
    months: string[];
    monthlyProvider: number[];
    monthlyClient: number[];
    monthlyGap: number[];
    monthlyTrips: number[];
    topTrucks: Array<{ truck: string; trips: number; prov: number; client: number; gap: number }>;
    topDrivers: Array<{ driver: string; trips: number; prov: number; client: number; gap: number }>;
    byProduct: Array<{ product: string; trips: number; prov: number; client: number }>;
    drivers: Array<{ id: number; name: string }>;
    trucks: Array<{ id: number; matricule: string }>;
    providers: Array<{ id: number; name: string }>;
    filters: Record<string, string>;
}

export default function RotationsDashboard(props: Props) {
    const { totalTrips, totalProviderWeight, totalClientWeight, totalGap, months, monthlyProvider, monthlyClient, monthlyGap, monthlyTrips, topTrucks, topDrivers, byProduct, drivers, trucks, providers, filters } = props;

    const applyFilter = (key: string, value: string | number | null) => {
        const f = { ...filters, [key]: value ? String(value) : '' };
        Object.keys(f).forEach((k) => { if (!f[k]) delete f[k]; });
        router.get('/dashboard/rotations', f, { preserveState: true, preserveScroll: true });
    };

    const clearFilters = () => router.get('/dashboard/rotations', {}, { preserveState: true });
    const hasFilters = Object.keys(filters).filter(k => k !== 'from' && k !== 'to').length > 0;

    return (
        <AuthenticatedLayout title="Rotations & Poids">
            <Head title="Rotations & Poids" />
            <AnalyticsTabs />

            {/* Filters */}
            <Card className="mb-6">
                <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 items-end">
                    <FormInput label="Du (22/mm)" type="date" name="from" value={filters.from ?? ''} onChange={(e) => applyFilter('from', e.target.value)} />
                    <FormInput label="Au (21/mm)" type="date" name="to" value={filters.to ?? ''} onChange={(e) => applyFilter('to', e.target.value)} />
                    <FormSelect label="Conducteur" placeholder="Tous" options={drivers.map(d => ({ value: d.id, label: d.name }))} value={filters.driver_id ?? null} onChange={(v) => applyFilter('driver_id', v)} wrapperClass="mb-0" />
                    <FormSelect label="Camion" placeholder="Tous" options={trucks.map(t => ({ value: t.id, label: t.matricule }))} value={filters.truck_id ?? null} onChange={(v) => applyFilter('truck_id', v)} wrapperClass="mb-0" />
                    <FormSelect label="Fournisseur" placeholder="Tous" options={providers.map(p => ({ value: p.id, label: p.name }))} value={filters.provider_id ?? null} onChange={(v) => applyFilter('provider_id', v)} wrapperClass="mb-0" />
                    <FormSelect label="Produit" placeholder="Tous" options={[{ value: '0/3', label: '0/3' }, { value: '3/8', label: '3/8' }, { value: '8/16', label: '8/16' }]} value={filters.product ?? null} onChange={(v) => applyFilter('product', v)} wrapperClass="mb-0" />
                </div>
                {hasFilters && (
                    <div className="mt-3 flex justify-end">
                        <button onClick={clearFilters} className="text-xs text-[var(--color-danger)] hover:underline flex items-center gap-1"><X size={12} /> Réinitialiser</button>
                    </div>
                )}
                <p className="text-xs text-[var(--color-text-muted)] mt-2">Période comptable : du 22 au 21. Basé sur la date client.</p>
            </Card>

            {/* KPIs */}
            <KpiGrid>
                <KpiCard label="Rotations" value={totalTrips} icon={<BarChart3 size={22} />} color="var(--color-info)" />
                <KpiCard label="Poids transporté" value={totalProviderWeight} unit="T" icon={<Weight size={22} />} color="var(--color-primary)" />
                <KpiCard label="Poids reçu" value={totalClientWeight} unit="T" icon={<Scale size={22} />} color="var(--color-success)" />
                <KpiCard label={totalGap < 0 ? 'Perte nette' : 'Excédent net'} value={Math.abs(totalGap)} unit="T" icon={<TrendingDown size={22} />} color={totalGap < 0 ? 'var(--color-danger)' : 'var(--color-info)'} />
            </KpiGrid>

            {/* Monthly breakdown */}
            {months.length > 0 && (
                <Card className="mt-6">
                    <h3 className="text-lg font-semibold text-[var(--color-text)] mb-4">Tonnage par période (22→21)</h3>
                    <div className="overflow-x-auto rounded-lg border border-[var(--color-border)]">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="bg-[var(--color-surface-hover)]">
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Période</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Rotations</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Transporté</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Reçu</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Écart</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[var(--color-border)]">
                                {months.map((m, i) => (
                                    <tr key={m} className="hover:bg-[var(--color-surface-hover)]">
                                        <td className="px-4 py-3 font-medium text-[var(--color-text)]">{m}</td>
                                        <td className="px-4 py-3 text-right font-mono text-[var(--color-text)]">{monthlyTrips[i]}</td>
                                        <td className="px-4 py-3 text-right font-mono text-[var(--color-text)]">{fmtT(monthlyProvider[i])}</td>
                                        <td className="px-4 py-3 text-right font-mono text-[var(--color-text)]">{fmtT(monthlyClient[i])}</td>
                                        <td className="px-4 py-3 text-right"><span className={clsx('font-mono', monthlyGap[i] < -500 ? 'text-red-600 font-bold' : monthlyGap[i] > 0 ? 'text-blue-600' : 'text-[var(--color-text)]')}>{monthlyGap[i] > 0 ? '+' : ''}{fmtT(monthlyGap[i])}</span></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>
            )}

            {/* By product */}
            {byProduct.length > 0 && (
                <Card className="mt-6">
                    <div className="flex items-center gap-2 mb-4">
                        <Package size={18} className="text-[var(--color-primary)]" />
                        <h3 className="text-lg font-semibold text-[var(--color-text)]">Par produit</h3>
                    </div>
                    <div className="grid sm:grid-cols-3 gap-4">
                        {byProduct.map((p) => (
                            <div key={p.product} className="p-4 rounded-lg bg-[var(--color-surface-hover)]">
                                <p className="text-lg font-bold text-[var(--color-text)]">{p.product}</p>
                                <div className="mt-2 space-y-1 text-sm">
                                    <div className="flex justify-between"><span className="text-[var(--color-text-muted)]">Rotations</span><span className="font-mono">{p.trips}</span></div>
                                    <div className="flex justify-between"><span className="text-[var(--color-text-muted)]">Transporté</span><span className="font-mono">{fmtT(p.prov)}</span></div>
                                    <div className="flex justify-between"><span className="text-[var(--color-text-muted)]">Reçu</span><span className="font-mono">{fmtT(p.client)}</span></div>
                                    <div className="flex justify-between"><span className="text-[var(--color-text-muted)]">Écart</span><span className={clsx('font-mono', (p.client - p.prov) < 0 ? 'text-[var(--color-danger)]' : (p.client - p.prov) > 0 ? 'text-blue-600' : '')}>{(p.client - p.prov) > 0 ? '+' : ''}{fmtT(p.client - p.prov)}</span></div>
                                </div>
                            </div>
                        ))}
                    </div>
                </Card>
            )}

            <div className="grid lg:grid-cols-2 gap-6 mt-6">
                {/* Top trucks */}
                <Card>
                    <div className="flex items-center gap-2 mb-4">
                        <Truck size={18} className="text-[var(--color-primary)]" />
                        <h3 className="text-lg font-semibold text-[var(--color-text)]">Top camions</h3>
                    </div>
                    <div className="overflow-x-auto rounded-lg border border-[var(--color-border)]">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="bg-[var(--color-surface-hover)]">
                                    <th className="px-3 py-2 text-left text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Camion</th>
                                    <th className="px-3 py-2 text-right text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Rot.</th>
                                    <th className="px-3 py-2 text-right text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Transporté</th>
                                    <th className="px-3 py-2 text-right text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Écart</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[var(--color-border)]">
                                {topTrucks.map((t, i) => (
                                    <tr key={i} className="hover:bg-[var(--color-surface-hover)]">
                                        <td className="px-3 py-2 font-medium text-[var(--color-text)]">{t.truck}</td>
                                        <td className="px-3 py-2 text-right font-mono text-[var(--color-text)]">{t.trips}</td>
                                        <td className="px-3 py-2 text-right font-mono text-[var(--color-text)]">{fmtT(t.prov)}</td>
                                        <td className="px-3 py-2 text-right">
                                            <Badge variant={t.gap < 0 ? (t.gap < -500 ? 'danger' : 'warning') : t.gap > 0 ? 'info' : 'success'}>
                                                {t.gap < 0 ? '' : t.gap > 0 ? '+' : ''}{fmtT(t.gap)}
                                            </Badge>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>

                {/* Top drivers */}
                <Card>
                    <div className="flex items-center gap-2 mb-4">
                        <Users size={18} className="text-amber-500" />
                        <h3 className="text-lg font-semibold text-[var(--color-text)]">Top conducteurs</h3>
                    </div>
                    <div className="overflow-x-auto rounded-lg border border-[var(--color-border)]">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="bg-[var(--color-surface-hover)]">
                                    <th className="px-3 py-2 text-left text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Conducteur</th>
                                    <th className="px-3 py-2 text-right text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Rot.</th>
                                    <th className="px-3 py-2 text-right text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Transporté</th>
                                    <th className="px-3 py-2 text-right text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Écart</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[var(--color-border)]">
                                {topDrivers.map((d, i) => (
                                    <tr key={i} className="hover:bg-[var(--color-surface-hover)]">
                                        <td className="px-3 py-2 font-medium text-[var(--color-text)]">{d.driver}</td>
                                        <td className="px-3 py-2 text-right font-mono text-[var(--color-text)]">{d.trips}</td>
                                        <td className="px-3 py-2 text-right font-mono text-[var(--color-text)]">{fmtT(d.prov)}</td>
                                        <td className="px-3 py-2 text-right">
                                            <Badge variant={d.gap < 0 ? (d.gap < -500 ? 'danger' : 'warning') : d.gap > 0 ? 'info' : 'success'}>
                                                {d.gap < 0 ? '' : d.gap > 0 ? '+' : ''}{fmtT(d.gap)}
                                            </Badge>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
