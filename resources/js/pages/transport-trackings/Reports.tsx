import { Head, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import KpiCard from '@/components/dashboard/KpiCard';
import KpiGrid from '@/components/dashboard/KpiGrid';
import FormSelect from '@/components/ui/FormSelect';
import FormInput from '@/components/ui/FormInput';
import Pagination from '@/components/ui/Pagination';
import { Weight, Scale, TrendingDown, BarChart3, Users, FileWarning, X } from 'lucide-react';
import { clsx } from 'clsx';

interface DriverRisk { driver_id: number; driver_name: string; sum_gap: number; trip_count: number; large_count: number; avg_gap: number; }
interface GapByProduct { product: string; gap_sum: number; trips: number; }
interface GapByBase { base: string; prov: number; client: number; gap_sum: number; trips: number; }
interface Anomaly { id: number; reference: string; provider_date: string; provider_net_weight: number; client_net_weight: number; gap: number; driver: { id: number; name: string } | null; truck: { id: number; matricule: string } | null; provider: { id: number; name: string } | null; }
interface Trip { id: number; reference: string; provider_date: string; client_date: string; provider_net_weight: number; client_net_weight: number; gap: number; product: string; driver: { id: number; name: string } | null; truck: { id: number; matricule: string } | null; provider: { id: number; name: string } | null; }

interface Props {
    totalTrips: number;
    totalProviderWeight: number;
    totalClientWeight: number;
    totalGap: number;
    totalDiscrepanciesCount: number;
    totalDiscrepancyKg: number;
    suspiciousDrivers: number;
    thisMonthTonnage: number;
    thisYearTonnage: number;
    months: string[];
    monthlyProvider: number[];
    monthlyClient: number[];
    monthlyGap: number[];
    monthlyTrips: number[];
    gapByProduct: GapByProduct[];
    gapByBase: GapByBase[];
    driverRisk: DriverRisk[];
    anomalies: Anomaly[];
    trips: { data: Trip[]; current_page: number; last_page: number; per_page: number; total: number; from: number | null; to: number | null };
    drivers: { id: number; name: string }[];
    trucks: { id: number; matricule: string }[];
    providers: { id: number; name: string }[];
    products: { id: string; name: string }[];
    bases: { id: string; name: string }[];
    filters: Record<string, string>;
}

const fmt = (v: number) => (Number(v) || 0).toLocaleString('fr-FR', { maximumFractionDigits: 0 });
const fmtT = (v: number) => `${fmt(v)} T`;

export default function Reports(props: Props) {
    const { totalTrips, totalProviderWeight, totalClientWeight, totalGap, totalDiscrepanciesCount, totalDiscrepancyKg, suspiciousDrivers, thisMonthTonnage, thisYearTonnage, months, monthlyProvider, monthlyClient, monthlyGap, monthlyTrips, gapByProduct, gapByBase, driverRisk, anomalies, trips, drivers, trucks, providers, products, bases, filters } = props;

    const applyFilter = (key: string, value: string | number | null) => {
        const newFilters = { ...filters, [key]: value ? String(value) : '' };
        Object.keys(newFilters).forEach((k) => { if (!newFilters[k]) delete newFilters[k]; });
        router.get('/dashboard/trackings', newFilters, { preserveState: true, preserveScroll: true });
    };

    const clearFilters = () => {
        router.get('/dashboard/trackings', {}, { preserveState: true });
    };

    const hasFilters = Object.keys(filters).filter(k => k !== 'from' && k !== 'to').length > 0;

    const driverOpts = drivers.map((d) => ({ value: d.id, label: d.name }));
    const truckOpts = trucks.map((t) => ({ value: t.id, label: t.matricule }));
    const providerOpts = providers.map((p) => ({ value: p.id, label: p.name }));
    const productOpts = products.map((p) => ({ value: p.id, label: p.name }));
    const baseOpts = bases.map((b) => ({ value: b.id, label: b.name }));

    return (
        <AuthenticatedLayout title="Dashboard Analytics">
            <Head title="Dashboard Analytics" />

            {/* Filters — onChange */}
            <Card className="mb-6">
                <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-7 gap-3 items-end">
                    <FormInput label="Du (22/mm)" type="date" name="from" value={filters.from ?? ''} onChange={(e) => applyFilter('from', e.target.value)} />
                    <FormInput label="Au (21/mm)" type="date" name="to" value={filters.to ?? ''} onChange={(e) => applyFilter('to', e.target.value)} />
                    <FormSelect label="Conducteur" placeholder="Tous" options={driverOpts} value={filters.driver_id ?? null} onChange={(v) => applyFilter('driver_id', v)} wrapperClass="mb-0" />
                    <FormSelect label="Camion" placeholder="Tous" options={truckOpts} value={filters.truck_id ?? null} onChange={(v) => applyFilter('truck_id', v)} wrapperClass="mb-0" />
                    <FormSelect label="Fournisseur" placeholder="Tous" options={providerOpts} value={filters.provider_id ?? null} onChange={(v) => applyFilter('provider_id', v)} wrapperClass="mb-0" />
                    <FormSelect label="Produit" placeholder="Tous" options={productOpts} value={filters.product ?? null} onChange={(v) => applyFilter('product', v)} wrapperClass="mb-0" />
                    <FormSelect label="Base" placeholder="Toutes" options={baseOpts} value={filters.base ?? null} onChange={(v) => applyFilter('base', v)} wrapperClass="mb-0" />
                </div>
                {hasFilters && (
                    <div className="mt-3 flex justify-end">
                        <button onClick={clearFilters} className="text-xs text-[var(--color-danger)] hover:underline flex items-center gap-1">
                            <X size={12} /> Réinitialiser les filtres
                        </button>
                    </div>
                )}
                <p className="text-xs text-[var(--color-text-muted)] mt-2">Période comptable : du 22 au 21 de chaque mois. Basé sur la date client.</p>
            </Card>

            {/* KPIs */}
            <KpiGrid>
                <KpiCard label="Poids transporté" value={totalProviderWeight} unit="T" icon={<Weight size={22} />} color="var(--color-primary)" />
                <KpiCard label="Poids reçu" value={totalClientWeight} unit="T" icon={<Scale size={22} />} color="var(--color-success)" />
                <KpiCard label="Écart total" value={Math.abs(totalGap)} unit="T" icon={<TrendingDown size={22} />} color="var(--color-danger)" />
                <KpiCard label="Rotations" value={totalTrips} icon={<BarChart3 size={22} />} color="var(--color-info)" />
            </KpiGrid>

            {/* Summary stats */}
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-4">
                <Card>
                    <p className="text-xs text-[var(--color-text-muted)] uppercase">Ce mois (22→21)</p>
                    <p className="text-xl font-bold text-[var(--color-text)] mt-1">{fmtT(thisMonthTonnage)}</p>
                </Card>
                <Card>
                    <p className="text-xs text-[var(--color-text-muted)] uppercase">Cette année</p>
                    <p className="text-xl font-bold text-[var(--color-text)] mt-1">{fmtT(thisYearTonnage)}</p>
                </Card>
                <Card>
                    <p className="text-xs text-[var(--color-text-muted)] uppercase">Anomalies</p>
                    <p className="text-xl font-bold text-[var(--color-danger)] mt-1">{totalDiscrepanciesCount}</p>
                    <p className="text-xs text-[var(--color-text-muted)]">{fmtT(totalDiscrepancyKg)} d'écart</p>
                </Card>
                <Card>
                    <p className="text-xs text-[var(--color-text-muted)] uppercase">Conducteurs suspects</p>
                    <p className="text-xl font-bold text-amber-500 mt-1">{suspiciousDrivers}</p>
                </Card>
            </div>

            {/* Monthly tonnage */}
            {months.length > 0 && (
                <Card className="mt-6">
                    <h3 className="text-lg font-semibold text-[var(--color-text)] mb-4">Tonnage par période (22→21)</h3>
                    <div className="overflow-x-auto rounded-lg border border-[var(--color-border)]">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="bg-[var(--color-surface-hover)]">
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Période</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Transporté</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Reçu</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Écart</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Rotations</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[var(--color-border)]">
                                {months.map((m, i) => (
                                    <tr key={m} className="hover:bg-[var(--color-surface-hover)]">
                                        <td className="px-4 py-3 font-medium text-[var(--color-text)]">{m}</td>
                                        <td className="px-4 py-3 text-right font-mono text-[var(--color-text)]">{fmtT(monthlyProvider[i])}</td>
                                        <td className="px-4 py-3 text-right font-mono text-[var(--color-text)]">{fmtT(monthlyClient[i])}</td>
                                        <td className="px-4 py-3 text-right">
                                            <span className={clsx('font-mono', monthlyGap[i] > 500 ? 'text-red-600 font-bold' : 'text-[var(--color-text)]')}>{fmtT(monthlyGap[i])}</span>
                                        </td>
                                        <td className="px-4 py-3 text-right font-mono text-[var(--color-text)]">{monthlyTrips[i]}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>
            )}

            <div className="grid lg:grid-cols-2 gap-6 mt-6">
                {gapByProduct.length > 0 && (
                    <Card>
                        <h3 className="text-lg font-semibold text-[var(--color-text)] mb-4">Écart par produit</h3>
                        <div className="space-y-3">
                            {gapByProduct.map((g) => (
                                <div key={g.product} className="flex items-center justify-between p-3 rounded-lg bg-[var(--color-surface-hover)]">
                                    <div>
                                        <span className="font-medium text-[var(--color-text)]">{g.product}</span>
                                        <span className="text-xs text-[var(--color-text-muted)] ml-2">({g.trips} rotations)</span>
                                    </div>
                                    <Badge variant={g.gap_sum > 1000 ? 'danger' : 'warning'}>{fmtT(g.gap_sum)}</Badge>
                                </div>
                            ))}
                        </div>
                    </Card>
                )}
                {gapByBase.length > 0 && (
                    <Card>
                        <h3 className="text-lg font-semibold text-[var(--color-text)] mb-4">Écart par base</h3>
                        <div className="space-y-3">
                            {gapByBase.map((g) => (
                                <div key={g.base} className="flex items-center justify-between p-3 rounded-lg bg-[var(--color-surface-hover)]">
                                    <div>
                                        <span className="font-medium text-[var(--color-text)]">{g.base === 'mr' ? 'Mauritanie' : g.base === 'sn' ? 'Sénégal' : g.base}</span>
                                        <span className="text-xs text-[var(--color-text-muted)] ml-2">({g.trips} rotations)</span>
                                    </div>
                                    <Badge variant={g.gap_sum > 1000 ? 'danger' : 'warning'}>{fmtT(g.gap_sum)}</Badge>
                                </div>
                            ))}
                        </div>
                    </Card>
                )}
            </div>

            {/* Driver risk */}
            {driverRisk.length > 0 && (
                <Card className="mt-6">
                    <div className="flex items-center gap-2 mb-4">
                        <Users size={18} className="text-amber-500" />
                        <h3 className="text-lg font-semibold text-[var(--color-text)]">Classement conducteurs par écart</h3>
                    </div>
                    <div className="overflow-x-auto rounded-lg border border-[var(--color-border)]">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="bg-[var(--color-surface-hover)]">
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Conducteur</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Rotations</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Écart total</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Écart moyen</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Gros écarts</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[var(--color-border)]">
                                {driverRisk.map((d, i) => (
                                    <tr key={d.driver_id} className="hover:bg-[var(--color-surface-hover)]">
                                        <td className="px-4 py-3"><span className={clsx('font-medium', i < 3 ? 'text-[var(--color-danger)]' : 'text-[var(--color-text)]')}>{d.driver_name}</span></td>
                                        <td className="px-4 py-3 text-right text-[var(--color-text)]">{d.trip_count}</td>
                                        <td className="px-4 py-3 text-right font-mono text-[var(--color-danger)]">{fmtT(d.sum_gap)}</td>
                                        <td className="px-4 py-3 text-right font-mono text-[var(--color-text)]">{fmtT(d.avg_gap)}</td>
                                        <td className="px-4 py-3 text-right">{d.large_count > 0 ? <Badge variant="danger">{d.large_count}</Badge> : <span className="text-[var(--color-text-muted)]">0</span>}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>
            )}

            {/* Anomalies */}
            {anomalies.length > 0 && (
                <Card className="mt-6">
                    <div className="flex items-center gap-2 mb-4">
                        <FileWarning size={18} className="text-[var(--color-danger)]" />
                        <h3 className="text-lg font-semibold text-[var(--color-text)]">Anomalies ({anomalies.length})</h3>
                    </div>
                    <div className="overflow-x-auto rounded-lg border border-[var(--color-border)]">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="bg-[var(--color-surface-hover)]">
                                    <th className="px-3 py-2 text-left text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Réf.</th>
                                    <th className="px-3 py-2 text-left text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Date</th>
                                    <th className="px-3 py-2 text-left text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Camion</th>
                                    <th className="px-3 py-2 text-left text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Conducteur</th>
                                    <th className="px-3 py-2 text-right text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Transporté</th>
                                    <th className="px-3 py-2 text-right text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Reçu</th>
                                    <th className="px-3 py-2 text-right text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Écart</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[var(--color-border)]">
                                {anomalies.map((a) => (
                                    <tr key={a.id} className="hover:bg-[var(--color-surface-hover)]">
                                        <td className="px-3 py-2"><a href={`/transport_tracking/${a.id}/show-page`} className="text-[var(--color-primary)] hover:underline">{a.reference}</a></td>
                                        <td className="px-3 py-2 text-[var(--color-text)]">{a.provider_date}</td>
                                        <td className="px-3 py-2 text-[var(--color-text)]">{a.truck?.matricule ?? '-'}</td>
                                        <td className="px-3 py-2 text-[var(--color-text)]">{a.driver?.name ?? '-'}</td>
                                        <td className="px-3 py-2 text-right font-mono text-[var(--color-text)]">{fmt(a.provider_net_weight)}</td>
                                        <td className="px-3 py-2 text-right font-mono text-[var(--color-text)]">{fmt(a.client_net_weight)}</td>
                                        <td className="px-3 py-2 text-right font-mono font-bold text-[var(--color-danger)]">{fmt(Math.abs(a.gap))}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>
            )}

            {/* All trips */}
            <Card className="mt-6" padding={false}>
                <div className="p-5">
                    <h3 className="text-lg font-semibold text-[var(--color-text)] mb-4">Toutes les rotations</h3>
                    <div className="overflow-x-auto rounded-lg border border-[var(--color-border)]">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="bg-[var(--color-surface-hover)]">
                                    <th className="px-3 py-2 text-left text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Réf.</th>
                                    <th className="px-3 py-2 text-left text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Date client</th>
                                    <th className="px-3 py-2 text-left text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Camion</th>
                                    <th className="px-3 py-2 text-left text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Conducteur</th>
                                    <th className="px-3 py-2 text-left text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Produit</th>
                                    <th className="px-3 py-2 text-right text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Transporté</th>
                                    <th className="px-3 py-2 text-right text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Reçu</th>
                                    <th className="px-3 py-2 text-center text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Écart</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[var(--color-border)]">
                                {trips.data.length === 0 ? (
                                    <tr><td colSpan={8} className="px-3 py-8 text-center text-[var(--color-text-muted)]">Aucune rotation</td></tr>
                                ) : trips.data.map((t) => (
                                    <tr key={t.id} className="hover:bg-[var(--color-surface-hover)]">
                                        <td className="px-3 py-2"><a href={`/transport_tracking/${t.id}/show-page`} className="text-[var(--color-primary)] hover:underline">{t.reference}</a></td>
                                        <td className="px-3 py-2 text-[var(--color-text)]">{t.client_date ?? t.provider_date}</td>
                                        <td className="px-3 py-2 text-[var(--color-text)]">{t.truck?.matricule ?? '-'}</td>
                                        <td className="px-3 py-2 text-[var(--color-text)]">{t.driver?.name ?? '-'}</td>
                                        <td className="px-3 py-2 text-[var(--color-text)]">{t.product ?? '-'}</td>
                                        <td className="px-3 py-2 text-right font-mono text-[var(--color-text)]">{fmt(t.provider_net_weight)}</td>
                                        <td className="px-3 py-2 text-right font-mono text-[var(--color-text)]">{fmt(t.client_net_weight)}</td>
                                        <td className="px-3 py-2 text-center">
                                            <Badge variant={Math.abs(t.gap) > 150 ? 'danger' : t.gap !== 0 ? 'warning' : 'success'}>{fmt(t.gap)}</Badge>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
                <div className="px-5 pb-5">
                    <Pagination meta={trips} />
                </div>
            </Card>
        </AuthenticatedLayout>
    );
}
