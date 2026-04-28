import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import KpiCard from '@/components/dashboard/KpiCard';
import KpiGrid from '@/components/dashboard/KpiGrid';
import FormSelect from '@/components/ui/FormSelect';
import Pagination from '@/components/ui/Pagination';
import Button from '@/components/ui/Button';
import { Weight, Scale, TrendingDown, BarChart3, Users, FileWarning, X, Filter, ChevronDown, ChevronUp, Package, MapPin } from 'lucide-react';
import AnalyticsTabs from '@/components/analytics/AnalyticsTabs';
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

const BASE_LABELS: Record<string, string> = { mr: 'Mauritanie', sn: 'Sénégal', none: 'Non définie' };

export default function Reports(props: Props) {
    const { totalTrips, totalProviderWeight, totalClientWeight, totalGap, totalDiscrepanciesCount, totalDiscrepancyKg, suspiciousDrivers, thisMonthTonnage, thisYearTonnage, months, monthlyProvider, monthlyClient, monthlyGap, monthlyTrips, gapByProduct, gapByBase, driverRisk, anomalies, trips, drivers, trucks, providers, products, bases, filters } = props;

    const [showFilters, setShowFilters] = useState(
        Object.keys(filters).filter(k => k !== 'from' && k !== 'to').length > 0
    );

    const applyFilter = (key: string, value: string | number | null) => {
        const f = { ...filters, [key]: value ? String(value) : '' };
        Object.keys(f).forEach((k) => { if (!f[k]) delete f[k]; });
        router.get('/dashboard/trackings', f, { preserveState: true, preserveScroll: true });
    };

    const clearFilters = () => router.get('/dashboard/trackings', {}, { preserveState: true });

    const hasFilters = Object.keys(filters).length > 0;

    return (
        <AuthenticatedLayout title="Analytiques Transport">
            <Head title="Analytiques Transport" />
            <AnalyticsTabs />

            {/* ── Filters ── */}
            <Card className="mb-6">
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
                    <div>
                        <label className="block text-xs font-medium text-[var(--color-text-muted)] uppercase mb-1.5">Date du</label>
                        <input
                            type="date"
                            value={filters.from ?? ''}
                            onChange={(e) => applyFilter('from', e.target.value || null)}
                            className="w-full px-3 py-2.5 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm"
                        />
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-[var(--color-text-muted)] uppercase mb-1.5">Date au</label>
                        <input
                            type="date"
                            value={filters.to ?? ''}
                            onChange={(e) => applyFilter('to', e.target.value || null)}
                            className="w-full px-3 py-2.5 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm"
                        />
                    </div>
                    <FormSelect label="Produit" placeholder="Tous" options={products.map(p => ({ value: p.id, label: p.name }))} value={filters.product ?? null} onChange={(v) => applyFilter('product', v)} wrapperClass="mb-0" />
                    <FormSelect label="Base" placeholder="Toutes" options={bases.map(b => ({ value: b.id, label: b.name }))} value={filters.base ?? null} onChange={(v) => applyFilter('base', v)} wrapperClass="mb-0" />
                </div>

                {/* Expandable advanced filters */}
                <div className="mt-3 flex items-center gap-2">
                    <button
                        onClick={() => setShowFilters(!showFilters)}
                        className="inline-flex items-center gap-1.5 text-xs font-medium text-[var(--color-text-secondary)] hover:text-[var(--color-text)] transition"
                    >
                        <Filter size={12} />
                        Plus de filtres
                        {showFilters ? <ChevronUp size={12} /> : <ChevronDown size={12} />}
                    </button>
                    {hasFilters && (
                        <button onClick={clearFilters} className="text-xs text-[var(--color-danger)] hover:underline flex items-center gap-1 ml-auto">
                            <X size={12} /> Réinitialiser
                        </button>
                    )}
                </div>

                {showFilters && (
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mt-3 pt-3 border-t border-[var(--color-border)]">
                        <FormSelect label="Conducteur" placeholder="Tous" options={drivers.map(d => ({ value: d.id, label: d.name }))} value={filters.driver_id ?? null} onChange={(v) => applyFilter('driver_id', v)} wrapperClass="mb-0" />
                        <FormSelect label="Camion" placeholder="Tous" options={trucks.map(t => ({ value: t.id, label: t.matricule }))} value={filters.truck_id ?? null} onChange={(v) => applyFilter('truck_id', v)} wrapperClass="mb-0" />
                        <FormSelect label="Fournisseur" placeholder="Tous" options={providers.map(p => ({ value: p.id, label: p.name }))} value={filters.provider_id ?? null} onChange={(v) => applyFilter('provider_id', v)} wrapperClass="mb-0" />
                    </div>
                )}

                <p className="text-[10px] text-[var(--color-text-muted)] mt-3">Les périodes mensuelles vont du 22 au 21 (ex : mars = 22/02 au 21/03). Basé sur la date client.</p>
            </Card>

            {/* ── KPIs ── */}
            <KpiGrid>
                <KpiCard label="Poids transporté" value={totalProviderWeight} unit="T" icon={<Weight size={22} />} color="var(--color-primary)" />
                <KpiCard label="Poids reçu" value={totalClientWeight} unit="T" icon={<Scale size={22} />} color="var(--color-success)" />
                <KpiCard label="Perte totale" value={Math.abs(totalGap)} unit="T" icon={<TrendingDown size={22} />} color="var(--color-danger)" />
                <KpiCard label="Rotations" value={totalTrips} icon={<BarChart3 size={22} />} color="var(--color-info)" />
            </KpiGrid>

            {/* ── Summary stats ── */}
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-4">
                <Card>
                    <div className="text-xs text-[var(--color-text-muted)] uppercase font-medium">Ce mois</div>
                    <div className="text-xl font-bold text-[var(--color-text)] mt-1">{fmtT(thisMonthTonnage)}</div>
                    <div className="text-[10px] text-[var(--color-text-muted)]">Période 22→21</div>
                </Card>
                <Card>
                    <div className="text-xs text-[var(--color-text-muted)] uppercase font-medium">Cette année</div>
                    <div className="text-xl font-bold text-[var(--color-text)] mt-1">{fmtT(thisYearTonnage)}</div>
                </Card>
                <Card>
                    <div className="text-xs text-[var(--color-text-muted)] uppercase font-medium">Anomalies de poids</div>
                    <div className="text-xl font-bold text-red-500 mt-1">{totalDiscrepanciesCount}</div>
                    <div className="text-[10px] text-[var(--color-text-muted)]">{fmtT(totalDiscrepancyKg)} de perte</div>
                </Card>
                <Card>
                    <div className="text-xs text-[var(--color-text-muted)] uppercase font-medium">Conducteurs suspects</div>
                    <div className="text-xl font-bold text-amber-500 mt-1">{suspiciousDrivers}</div>
                </Card>
            </div>

            {/* ── Monthly tonnage ── */}
            {months.length > 0 && (
                <Card className="mt-6">
                    <h3 className="text-sm font-semibold text-[var(--color-text)] mb-3">Tonnage par période (22→21)</h3>
                    <div className="overflow-x-auto rounded-lg border border-[var(--color-border)]">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="bg-[var(--color-surface-hover)]">
                                    <th className="px-4 py-2.5 text-left text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Période</th>
                                    <th className="px-4 py-2.5 text-right text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Rotations</th>
                                    <th className="px-4 py-2.5 text-right text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Transporté</th>
                                    <th className="px-4 py-2.5 text-right text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Reçu</th>
                                    <th className="px-4 py-2.5 text-right text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Perte</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[var(--color-border)]">
                                {months.map((m, i) => (
                                    <tr key={m} className="hover:bg-[var(--color-surface-hover)]">
                                        <td className="px-4 py-2.5 font-medium text-[var(--color-text)]">{m}</td>
                                        <td className="px-4 py-2.5 text-right font-mono text-[var(--color-text)]">{monthlyTrips[i]}</td>
                                        <td className="px-4 py-2.5 text-right font-mono text-[var(--color-text)]">{fmtT(monthlyProvider[i])}</td>
                                        <td className="px-4 py-2.5 text-right font-mono text-[var(--color-text)]">{fmtT(monthlyClient[i])}</td>
                                        <td className="px-4 py-2.5 text-right">
                                            <span className={clsx('font-mono', monthlyGap[i] > 500 ? 'text-red-600 font-bold' : 'text-[var(--color-text)]')}>{fmtT(monthlyGap[i])}</span>
                                        </td>
                                    </tr>
                                ))}
                                {/* Totals row */}
                                <tr className="bg-[var(--color-surface-hover)] font-bold">
                                    <td className="px-4 py-2.5 text-[var(--color-text)]">Total</td>
                                    <td className="px-4 py-2.5 text-right font-mono text-[var(--color-text)]">{fmt(monthlyTrips.reduce((a, b) => a + b, 0))}</td>
                                    <td className="px-4 py-2.5 text-right font-mono text-[var(--color-text)]">{fmtT(monthlyProvider.reduce((a, b) => a + b, 0))}</td>
                                    <td className="px-4 py-2.5 text-right font-mono text-[var(--color-text)]">{fmtT(monthlyClient.reduce((a, b) => a + b, 0))}</td>
                                    <td className="px-4 py-2.5 text-right font-mono text-red-600">{fmtT(monthlyGap.reduce((a, b) => a + b, 0))}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </Card>
            )}

            {/* ── Perte par produit + base ── */}
            <div className="grid lg:grid-cols-2 gap-5 mt-6">
                {gapByProduct.length > 0 && (
                    <Card>
                        <h3 className="text-sm font-semibold text-[var(--color-text)] mb-3 flex items-center gap-2">
                            <Package size={16} className="text-[var(--color-primary)]" /> Perte par produit
                        </h3>
                        <div className="space-y-2">
                            {gapByProduct.map((g) => (
                                <div key={g.product} className="flex items-center justify-between p-3 rounded-xl bg-[var(--color-surface-hover)]">
                                    <div>
                                        <span className="text-sm font-bold text-[var(--color-text)]">{g.product}</span>
                                        <span className="text-xs text-[var(--color-text-muted)] ml-2">{g.trips} rot.</span>
                                    </div>
                                    <Badge variant={g.gap_sum > 1000 ? 'danger' : 'warning'}>{fmtT(g.gap_sum)}</Badge>
                                </div>
                            ))}
                        </div>
                    </Card>
                )}
                {gapByBase.length > 0 && (
                    <Card>
                        <h3 className="text-sm font-semibold text-[var(--color-text)] mb-3 flex items-center gap-2">
                            <MapPin size={16} className="text-[var(--color-primary)]" /> Perte par base
                        </h3>
                        <div className="space-y-2">
                            {gapByBase.map((g) => (
                                <div key={g.base} className="flex items-center justify-between p-3 rounded-xl bg-[var(--color-surface-hover)]">
                                    <div>
                                        <span className="text-sm font-bold text-[var(--color-text)]">{BASE_LABELS[g.base] ?? g.base}</span>
                                        <span className="text-xs text-[var(--color-text-muted)] ml-2">{g.trips} rot.</span>
                                    </div>
                                    <Badge variant={g.gap_sum > 1000 ? 'danger' : 'warning'}>{fmtT(g.gap_sum)}</Badge>
                                </div>
                            ))}
                        </div>
                    </Card>
                )}
            </div>

            {/* ── Driver risk ── */}
            {driverRisk.length > 0 && (
                <Card className="mt-6">
                    <h3 className="text-sm font-semibold text-[var(--color-text)] mb-3 flex items-center gap-2">
                        <Users size={16} className="text-amber-500" /> Classement conducteurs par perte
                    </h3>
                    <div className="overflow-x-auto rounded-lg border border-[var(--color-border)]">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="bg-[var(--color-surface-hover)]">
                                    <th className="px-4 py-2.5 text-left text-xs font-semibold uppercase text-[var(--color-text-secondary)]">#</th>
                                    <th className="px-4 py-2.5 text-left text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Conducteur</th>
                                    <th className="px-4 py-2.5 text-right text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Rotations</th>
                                    <th className="px-4 py-2.5 text-right text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Perte totale</th>
                                    <th className="px-4 py-2.5 text-right text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Perte moyenne</th>
                                    <th className="px-4 py-2.5 text-right text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Grosses pertes</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[var(--color-border)]">
                                {driverRisk.map((d, i) => (
                                    <tr key={d.driver_id} className="hover:bg-[var(--color-surface-hover)]">
                                        <td className="px-4 py-2.5 text-xs text-[var(--color-text-muted)]">{i + 1}</td>
                                        <td className="px-4 py-2.5">
                                            <span className={clsx('font-medium', i < 3 ? 'text-red-600' : 'text-[var(--color-text)]')}>{d.driver_name}</span>
                                        </td>
                                        <td className="px-4 py-2.5 text-right text-[var(--color-text)]">{d.trip_count}</td>
                                        <td className="px-4 py-2.5 text-right font-mono text-red-600">{fmtT(d.sum_gap)}</td>
                                        <td className="px-4 py-2.5 text-right font-mono text-[var(--color-text)]">{fmtT(d.avg_gap)}</td>
                                        <td className="px-4 py-2.5 text-right">
                                            {d.large_count > 0
                                                ? <Badge variant="danger">{d.large_count}</Badge>
                                                : <span className="text-[var(--color-text-muted)]">0</span>
                                            }
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>
            )}

            {/* ── Anomalies ── */}
            {anomalies.length > 0 && (
                <Card className="mt-6">
                    <h3 className="text-sm font-semibold text-[var(--color-text)] mb-3 flex items-center gap-2">
                        <FileWarning size={16} className="text-red-500" /> Anomalies de poids
                        <Badge variant="danger">{anomalies.length}</Badge>
                    </h3>
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
                                    <th className="px-3 py-2 text-right text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Perte</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[var(--color-border)]">
                                {anomalies.map((a) => (
                                    <tr key={a.id} className="hover:bg-[var(--color-surface-hover)]">
                                        <td className="px-3 py-2"><a href={`/transport_tracking/${a.id}/show-page`} className="text-[var(--color-primary)] hover:underline font-medium">{a.reference}</a></td>
                                        <td className="px-3 py-2 text-[var(--color-text)]">{a.provider_date}</td>
                                        <td className="px-3 py-2 text-[var(--color-text)]">{a.truck?.matricule ?? '-'}</td>
                                        <td className="px-3 py-2 text-[var(--color-text)]">{a.driver?.name ?? '-'}</td>
                                        <td className="px-3 py-2 text-right font-mono">{fmt(a.provider_net_weight)}</td>
                                        <td className="px-3 py-2 text-right font-mono">{fmt(a.client_net_weight)}</td>
                                        <td className="px-3 py-2 text-right font-mono font-bold text-red-600">{fmt(Math.abs(a.gap))}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>
            )}

            {/* ── All trips ── */}
            <Card className="mt-6" padding={false}>
                <div className="p-5">
                    <h3 className="text-sm font-semibold text-[var(--color-text)] mb-3">Toutes les rotations</h3>
                    <div className="overflow-x-auto rounded-lg border border-[var(--color-border)]">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="bg-[var(--color-surface-hover)]">
                                    <th className="px-3 py-2 text-left text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Réf.</th>
                                    <th className="px-3 py-2 text-left text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Date</th>
                                    <th className="px-3 py-2 text-left text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Camion</th>
                                    <th className="px-3 py-2 text-left text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Conducteur</th>
                                    <th className="px-3 py-2 text-left text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Produit</th>
                                    <th className="px-3 py-2 text-right text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Transporté</th>
                                    <th className="px-3 py-2 text-right text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Reçu</th>
                                    <th className="px-3 py-2 text-center text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Perte</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[var(--color-border)]">
                                {trips.data.length === 0 ? (
                                    <tr><td colSpan={8} className="px-3 py-8 text-center text-[var(--color-text-muted)]">Aucune rotation</td></tr>
                                ) : trips.data.map((t) => (
                                    <tr key={t.id} className="hover:bg-[var(--color-surface-hover)]">
                                        <td className="px-3 py-2"><a href={`/transport_tracking/${t.id}/show-page`} className="text-[var(--color-primary)] hover:underline font-medium">{t.reference}</a></td>
                                        <td className="px-3 py-2 text-[var(--color-text)]">{t.client_date ?? t.provider_date}</td>
                                        <td className="px-3 py-2 text-[var(--color-text)]">{t.truck?.matricule ?? '-'}</td>
                                        <td className="px-3 py-2 text-[var(--color-text)]">{t.driver?.name ?? '-'}</td>
                                        <td className="px-3 py-2 text-[var(--color-text)]">{t.product ?? '-'}</td>
                                        <td className="px-3 py-2 text-right font-mono">{fmt(t.provider_net_weight)}</td>
                                        <td className="px-3 py-2 text-right font-mono">{fmt(t.client_net_weight)}</td>
                                        <td className="px-3 py-2 text-center">
                                            {t.gap < 0
                                                ? <Badge variant="danger">{t.gap.toLocaleString('fr-FR')}</Badge>
                                                : t.gap > 0
                                                    ? <Badge variant="info">+{t.gap.toLocaleString('fr-FR')}</Badge>
                                                    : <Badge variant="success">0</Badge>
                                            }
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
