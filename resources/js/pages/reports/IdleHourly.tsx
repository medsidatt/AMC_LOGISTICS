import { Head } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import FormInput from '@/components/ui/FormInput';
import { Clock, Download, Loader2, Search } from 'lucide-react';

interface TruckOption {
    id: number;
    matricule: string;
}

interface IdleRow {
    truck_id: number;
    truck_matricule: string;
    date: string;
    hour: number;
    idle_minutes: number;
    location_label: string;
    classification: string;
    place_id: number | null;
    latitude: number;
    longitude: number;
}

interface Props {
    trucks: TruckOption[];
}

function classificationBadge(c: string) {
    const map: Record<string, string> = {
        'Carrière': 'bg-amber-100 text-amber-800 border-amber-200',
        'Autre site': 'bg-blue-100 text-blue-800 border-blue-200',
        'Sur route': 'bg-emerald-100 text-emerald-800 border-emerald-200',
    };
    const cls = map[c] ?? 'bg-slate-100 text-slate-700 border-slate-200';
    return (
        <span className={`inline-block px-2 py-0.5 rounded-full text-xs font-medium border ${cls}`}>{c}</span>
    );
}

export default function IdleHourlyReport({ trucks }: Props) {
    const today = new Date().toISOString().slice(0, 10);
    const yesterday = new Date(Date.now() - 86400000).toISOString().slice(0, 10);

    const [from, setFrom] = useState(yesterday);
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
        const t = { quarry: 0, site: 0, route: 0, total: 0 };
        for (const r of rows) {
            t.total += r.idle_minutes;
            if (r.classification === 'Carrière') t.quarry += r.idle_minutes;
            else if (r.classification === 'Autre site') t.site += r.idle_minutes;
            else t.route += r.idle_minutes;
        }
        return t;
    }, [rows]);

    const fmtMin = (m: number) => `${(m / 60).toFixed(1)} h (${m.toFixed(0)} min)`;

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
                <div className="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                    <SummaryCard label="Total ralenti" value={fmtMin(totals.total)} />
                    <SummaryCard label="À la carrière" value={fmtMin(totals.quarry)} accent="text-amber-700" />
                    <SummaryCard label="Autre site" value={fmtMin(totals.site)} accent="text-blue-700" />
                    <SummaryCard label="Sur route" value={fmtMin(totals.route)} accent="text-emerald-700" />
                </div>
            )}

            <div className="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead className="bg-[var(--color-surface-hover)] text-left">
                            <tr>
                                <Th>Camion</Th>
                                <Th>Date</Th>
                                <Th>Heure</Th>
                                <Th>Minutes ralenti</Th>
                                <Th>Lieu</Th>
                                <Th>Classification</Th>
                                <Th>Coordonnées</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length === 0 && (
                                <tr>
                                    <td colSpan={7} className="px-4 py-8 text-center text-[var(--color-text-muted)]">
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
                                    <td className="px-3 py-2">{classificationBadge(r.classification)}</td>
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
