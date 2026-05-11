import { Head } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import FormInput from '@/components/ui/FormInput';
import { Clock, Download, Loader2, Search } from 'lucide-react';

interface TruckOption {
    id: number;
    matricule: string;
}

type IdleCategory =
    | 'parking'
    | 'provider_site'
    | 'client_site'
    | 'base'
    | 'fuel_station'
    | 'other_place'
    | 'on_road';

interface IdleRow {
    truck_id: number;
    truck_matricule: string;
    date: string;
    hour: number;
    idle_minutes: number;
    location_label: string;
    classification: string;
    category: IdleCategory;
    place_id: number | null;
    place_type: string | null;
    nearest_quarry_name: string | null;
    nearest_quarry_km: number | null;
    nearest_client_name: string | null;
    nearest_client_km: number | null;
    latitude: number;
    longitude: number;
}

const CATEGORY_LABEL: Record<IdleCategory, string> = {
    parking: 'Parking',
    provider_site: 'Carrière',
    client_site: 'Base client',
    base: 'Base / Hub',
    fuel_station: 'Station-service',
    other_place: 'Zone connue',
    on_road: 'Sur route',
};

const CATEGORY_BAR: Record<IdleCategory, string> = {
    parking: 'h-full bg-violet-500',
    provider_site: 'h-full bg-amber-500',
    client_site: 'h-full bg-blue-500',
    base: 'h-full bg-slate-500',
    fuel_station: 'h-full bg-rose-500',
    other_place: 'h-full bg-cyan-500',
    on_road: 'h-full bg-emerald-500',
};

const CATEGORY_BADGE: Record<IdleCategory, string> = {
    parking: 'bg-violet-100 text-violet-800 border-violet-200',
    provider_site: 'bg-amber-100 text-amber-800 border-amber-200',
    client_site: 'bg-blue-100 text-blue-800 border-blue-200',
    base: 'bg-slate-100 text-slate-700 border-slate-200',
    fuel_station: 'bg-rose-100 text-rose-800 border-rose-200',
    other_place: 'bg-cyan-100 text-cyan-800 border-cyan-200',
    on_road: 'bg-emerald-100 text-emerald-800 border-emerald-200',
};

const CATEGORY_ORDER: IdleCategory[] = [
    'parking',
    'provider_site',
    'client_site',
    'base',
    'fuel_station',
    'other_place',
    'on_road',
];

interface Props {
    trucks: TruckOption[];
}

function categoryBadge(c: IdleCategory) {
    const cls = CATEGORY_BADGE[c] ?? 'bg-slate-100 text-slate-700 border-slate-200';
    return (
        <span className={`inline-block px-2 py-0.5 rounded-full text-xs font-medium border ${cls}`}>
            {CATEGORY_LABEL[c] ?? c}
        </span>
    );
}

export default function IdleHourlyReport({ trucks }: Props) {
    const today = new Date().toISOString().slice(0, 10);
    const sevenDaysAgo = new Date(Date.now() - 7 * 86400000).toISOString().slice(0, 10);

    const [from, setFrom] = useState(sevenDaysAgo);
    const [to, setTo] = useState(today);
    const [selected, setSelected] = useState<number[]>([]);
    const [rows, setRows] = useState<IdleRow[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [hasFetched, setHasFetched] = useState(false);

    const toggleTruck = (id: number) => {
        setSelected((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]));
    };

    const selectAll = () => setSelected(trucks.map((t) => t.id));
    const clearAll = () => setSelected([]);

    const queryString = useMemo(() => {
        const params = new URLSearchParams();
        params.set('from', from);
        params.set('to', to);
        selected.forEach((id) => params.append('truck_ids[]', String(id)));
        return params.toString();
    }, [from, to, selected]);

    const handlePreview = async () => {
        if (selected.length === 0) {
            setError('Sélectionnez au moins un camion.');
            return;
        }
        setLoading(true);
        setError(null);
        try {
            const res = await fetch(`/reports/idle-hourly/data?${queryString}`, {
                headers: { Accept: 'application/json' },
            });
            if (!res.ok) {
                throw new Error(`Erreur serveur (${res.status})`);
            }
            const data = await res.json();
            setRows(data.rows ?? []);
            setHasFetched(true);
        } catch (e: any) {
            setError(e?.message ?? 'Erreur de chargement');
        } finally {
            setLoading(false);
        }
    };

    const totals = useMemo(() => {
        const total = rows.reduce((s, r) => s + r.idle_minutes, 0);
        const byCat: Record<IdleCategory, number> = {
            parking: 0, provider_site: 0, client_site: 0, base: 0,
            fuel_station: 0, other_place: 0, on_road: 0,
        };
        for (const r of rows) byCat[r.category] = (byCat[r.category] ?? 0) + r.idle_minutes;
        return { total, byCat };
    }, [rows]);

    const categoryRows = useMemo(() => {
        const total = totals.total || 1;
        return CATEGORY_ORDER
            .map((cat) => ({
                category: cat,
                label: CATEGORY_LABEL[cat],
                minutes: totals.byCat[cat] ?? 0,
                pct: ((totals.byCat[cat] ?? 0) / total) * 100,
            }))
            .filter((r) => r.minutes > 0)
            .sort((a, b) => b.minutes - a.minutes);
    }, [totals]);

    const byPlace = useMemo(() => {
        const map = new Map<string, { label: string; category: IdleCategory; minutes: number; hours: Set<string> }>();
        for (const r of rows) {
            const key = `${r.category}||${r.location_label}`;
            const cur = map.get(key) ?? { label: r.location_label, category: r.category, minutes: 0, hours: new Set<string>() };
            cur.minutes += r.idle_minutes;
            cur.hours.add(`${r.date} ${r.hour}`);
            map.set(key, cur);
        }
        const total = totals.total || 1;
        return Array.from(map.values())
            .map((g) => ({ ...g, hourCount: g.hours.size, pct: (g.minutes / total) * 100 }))
            .sort((a, b) => b.minutes - a.minutes);
    }, [rows, totals.total]);

    const fmtMin = (m: number) => `${(m / 60).toFixed(2)} h (${m.toFixed(0)} min)`;

    return (
        <AuthenticatedLayout title="Ralenti horaire">
            <Head title="Ralenti horaire" />

            <div className="mb-6 flex items-start justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-bold text-[var(--color-text)] flex items-center gap-2">
                        <Clock size={22} /> Ralenti horaire
                    </h1>
                    <p className="text-sm text-[var(--color-text-muted)] mt-1">
                        Heures où le camion est démarré sans bouger — comparaison carrière vs route.
                    </p>
                </div>
            </div>

            <div className="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] p-5 mb-6">
                <div className="grid md:grid-cols-2 gap-4">
                    <FormInput label="Du" type="date" name="from" value={from} onChange={(e) => setFrom(e.target.value)} />
                    <FormInput label="Au" type="date" name="to" value={to} onChange={(e) => setTo(e.target.value)} />
                </div>

                <div className="mt-2">
                    <div className="flex items-center justify-between mb-2">
                        <label className="text-xs font-medium text-[var(--color-text-secondary)]">
                            Camions ({selected.length}/{trucks.length})
                        </label>
                        <div className="flex gap-2">
                            <button type="button" onClick={selectAll} className="text-xs text-[var(--color-primary)] hover:underline">Tout sélectionner</button>
                            <button type="button" onClick={clearAll} className="text-xs text-[var(--color-text-muted)] hover:underline">Effacer</button>
                        </div>
                    </div>
                    <div className="max-h-48 overflow-y-auto border border-[var(--color-border)] rounded-lg p-2 grid grid-cols-2 md:grid-cols-4 gap-1">
                        {trucks.map((t) => (
                            <label key={t.id} className="flex items-center gap-2 px-2 py-1 rounded hover:bg-[var(--color-surface-hover)] cursor-pointer text-sm">
                                <input
                                    type="checkbox"
                                    checked={selected.includes(t.id)}
                                    onChange={() => toggleTruck(t.id)}
                                    className="rounded"
                                />
                                <span className="text-[var(--color-text)]">{t.matricule}</span>
                            </label>
                        ))}
                    </div>
                </div>

                <div className="flex flex-wrap gap-2 mt-4">
                    <button
                        type="button"
                        onClick={handlePreview}
                        disabled={loading}
                        className="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-[var(--color-primary)] hover:opacity-90 text-white text-sm font-medium transition shadow-sm disabled:opacity-50"
                    >
                        {loading ? <Loader2 size={16} className="animate-spin" /> : <Search size={16} />}
                        Prévisualiser
                    </button>
                    <a
                        href={selected.length > 0 ? `/reports/idle-hourly/excel?${queryString}` : '#'}
                        onClick={(e) => { if (selected.length === 0) e.preventDefault(); }}
                        className={`inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-white text-sm font-medium transition shadow-sm ${selected.length === 0 ? 'bg-emerald-400 cursor-not-allowed opacity-60' : 'bg-emerald-600 hover:bg-emerald-700'}`}
                    >
                        <Download size={16} /> Exporter Excel
                    </a>
                </div>

                {error && <p className="text-sm text-red-600 mt-3">{error}</p>}
            </div>

            {hasFetched && (
                <>
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                        <SummaryCard label="Total ralenti" value={fmtMin(totals.total)} />
                        {categoryRows.slice(0, 3).map((c) => (
                            <SummaryCard
                                key={c.category}
                                label={`${c.label} (${c.pct.toFixed(1)}%)`}
                                value={fmtMin(c.minutes)}
                            />
                        ))}
                    </div>

                    {categoryRows.length > 0 && (
                        <div className="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] overflow-hidden mb-6">
                            <div className="px-5 py-3 border-b border-[var(--color-border)]">
                                <h2 className="text-base font-semibold text-[var(--color-text)]">Ralenti par catégorie</h2>
                            </div>
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="bg-[var(--color-surface-hover)] text-left">
                                        <tr>
                                            <Th>Catégorie</Th>
                                            <Th>Heures de ralenti</Th>
                                            <Th>% du total</Th>
                                            <Th>Répartition</Th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {categoryRows.map((c) => (
                                            <tr key={c.category} className="border-t border-[var(--color-border)]">
                                                <td className="px-3 py-2">{categoryBadge(c.category)}</td>
                                                <td className="px-3 py-2 tabular-nums">{fmtMin(c.minutes)}</td>
                                                <td className="px-3 py-2 tabular-nums font-semibold">{c.pct.toFixed(1)}%</td>
                                                <td className="px-3 py-2 min-w-[140px]">
                                                    <div className="h-2 rounded bg-slate-200 overflow-hidden">
                                                        <div className={CATEGORY_BAR[c.category]} style={{ width: `${Math.min(100, c.pct)}%` }} />
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}

                    <div className="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] overflow-hidden mb-6">
                        <div className="px-5 py-3 border-b border-[var(--color-border)] flex items-center justify-between">
                            <h2 className="text-base font-semibold text-[var(--color-text)]">Ralenti par lieu</h2>
                            <span className="text-xs text-[var(--color-text-muted)]">Trié par durée décroissante</span>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="bg-[var(--color-surface-hover)] text-left">
                                    <tr>
                                        <Th>Lieu</Th>
                                        <Th>Catégorie</Th>
                                        <Th>Heures de ralenti</Th>
                                        <Th>Heures distinctes</Th>
                                        <Th>% du total</Th>
                                        <Th>Répartition</Th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {byPlace.length === 0 && (
                                        <tr>
                                            <td colSpan={6} className="px-4 py-8 text-center text-[var(--color-text-muted)]">
                                                Aucun ralenti détecté pour la période sélectionnée.
                                            </td>
                                        </tr>
                                    )}
                                    {byPlace.map((g) => (
                                        <tr key={`${g.category}-${g.label}`} className="border-t border-[var(--color-border)] hover:bg-[var(--color-surface-hover)]/50">
                                            <td className="px-3 py-2 font-medium">{g.label}</td>
                                            <td className="px-3 py-2">{categoryBadge(g.category)}</td>
                                            <td className="px-3 py-2 tabular-nums">{fmtMin(g.minutes)}</td>
                                            <td className="px-3 py-2 tabular-nums">{g.hourCount}</td>
                                            <td className="px-3 py-2 tabular-nums font-semibold">{g.pct.toFixed(1)}%</td>
                                            <td className="px-3 py-2 min-w-[140px]">
                                                <div className="h-2 rounded bg-slate-200 overflow-hidden">
                                                    <div className={CATEGORY_BAR[g.category]} style={{ width: `${Math.min(100, g.pct)}%` }} />
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </>
            )}

            <div className="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] overflow-hidden">
                <div className="px-5 py-3 border-b border-[var(--color-border)]">
                    <h2 className="text-base font-semibold text-[var(--color-text)]">Détail heure par heure</h2>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead className="bg-[var(--color-surface-hover)] text-left">
                            <tr>
                                <Th>Camion</Th>
                                <Th>Date</Th>
                                <Th>Heure</Th>
                                <Th>Minutes ralenti</Th>
                                <Th>Lieu</Th>
                                <Th>Catégorie</Th>
                                <Th>Carrière proche</Th>
                                <Th>Client proche</Th>
                                <Th>Coordonnées</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length === 0 && (
                                <tr>
                                    <td colSpan={9} className="px-4 py-8 text-center text-[var(--color-text-muted)]">
                                        {hasFetched ? 'Aucun ralenti détecté pour la période sélectionnée.' : 'Choisissez des camions et une période, puis cliquez sur Prévisualiser.'}
                                    </td>
                                </tr>
                            )}
                            {rows.map((r, idx) => (
                                <tr key={`${r.truck_id}-${r.date}-${r.hour}-${idx}`} className="border-t border-[var(--color-border)] hover:bg-[var(--color-surface-hover)]/50">
                                    <td className="px-3 py-2 font-medium">{r.truck_matricule}</td>
                                    <td className="px-3 py-2">{r.date}</td>
                                    <td className="px-3 py-2">{String(r.hour).padStart(2, '0')}:00</td>
                                    <td className="px-3 py-2 tabular-nums">{r.idle_minutes.toFixed(1)}</td>
                                    <td className="px-3 py-2">{r.location_label}</td>
                                    <td className="px-3 py-2">{categoryBadge(r.category)}</td>
                                    <td className="px-3 py-2 text-xs">
                                        {r.nearest_quarry_name
                                            ? <><span className="font-medium">{r.nearest_quarry_name}</span> <span className="text-[var(--color-text-muted)]">({r.nearest_quarry_km?.toFixed(1)} km)</span></>
                                            : <span className="text-[var(--color-text-muted)]">—</span>}
                                    </td>
                                    <td className="px-3 py-2 text-xs">
                                        {r.nearest_client_name
                                            ? <><span className="font-medium">{r.nearest_client_name}</span> <span className="text-[var(--color-text-muted)]">({r.nearest_client_km?.toFixed(1)} km)</span></>
                                            : <span className="text-[var(--color-text-muted)]">—</span>}
                                    </td>
                                    <td className="px-3 py-2 tabular-nums text-xs text-[var(--color-text-muted)]">
                                        {r.latitude.toFixed(5)}, {r.longitude.toFixed(5)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function Th({ children }: { children: React.ReactNode }) {
    return <th className="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-[var(--color-text-secondary)]">{children}</th>;
}

function SummaryCard({ label, value, accent }: { label: string; value: string; accent?: string }) {
    return (
        <div className="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] p-4">
            <div className="text-xs text-[var(--color-text-muted)]">{label}</div>
            <div className={`text-lg font-semibold mt-1 ${accent ?? 'text-[var(--color-text)]'}`}>{value}</div>
        </div>
    );
}
