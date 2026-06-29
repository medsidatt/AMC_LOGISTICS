import { useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import Drawer from '@/components/ui/Drawer';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import Tabs from '@/components/ui/Tabs';
import FormInput from '@/components/ui/FormInput';
import { apiFetch } from '@/utils/csrf';
import { formatNumber } from '@/utils/formatters';
import { Upload, CheckCircle2, AlertTriangle, Fuel, FileSpreadsheet } from 'lucide-react';

interface EdkPreview {
    valid: { line: number; date_display: string; truck_matricule: string; driver_name: string | null; montant: number; litres: number }[];
    invalid: { line: number; reason: string; [k: string]: any }[];
    totals: { count_valid: number; count_invalid: number; total_litres: number; total_fcfa: number };
    token: string;
}
interface FleetiPreview {
    valid: { truck_matricule: string; date_display: string; kilometers: number; consumed: number; consumed_per_100km: number | null; refills_volume: number }[];
    invalid: { reason: string }[];
    period: { from: string | null; to: string | null };
    totals: { count_rows: number; count_trucks: number; litres_refilled: number; litres_consumed: number; km: number };
    token: string;
}

interface Props {
    pricePerLitre: number;
    onClose: () => void;
}

/** Import EDK card recharges (CSV) or Fleeti monthly reports (XLSX) — preview then
 * commit. Reuses the existing import endpoints unchanged, inside the shared Drawer. */
export default function FuelImportDrawer({ pricePerLitre, onClose }: Props) {
    const [tab, setTab] = useState('edk');

    const [edkFile, setEdkFile] = useState<File | null>(null);
    const [edkPrice, setEdkPrice] = useState(String(pricePerLitre));
    const [edkPreview, setEdkPreview] = useState<EdkPreview | null>(null);
    const [edkLoading, setEdkLoading] = useState(false);
    const [edkCommitting, setEdkCommitting] = useState(false);
    const edkInputRef = useRef<HTMLInputElement>(null);

    const [fleetiFile, setFleetiFile] = useState<File | null>(null);
    const [fleetiPreview, setFleetiPreview] = useState<FleetiPreview | null>(null);
    const [fleetiLoading, setFleetiLoading] = useState(false);
    const [fleetiCommitting, setFleetiCommitting] = useState(false);
    const fleetiInputRef = useRef<HTMLInputElement>(null);

    const previewEdk = async () => {
        if (!edkFile) return;
        setEdkLoading(true);
        const data = new FormData();
        data.append('file', edkFile);
        data.append('price_per_litre', edkPrice);
        try {
            const res = await apiFetch('/fuel/import/edk/preview', { method: 'POST', body: data });
            const json = await res.json().catch(() => null);
            if (!res.ok || !json?.totals) { alert("Échec de l'analyse EDK : " + (json?.error || json?.message || `Erreur ${res.status}`)); return; }
            setEdkPreview(json);
        } catch (e: any) {
            alert('Erreur réseau : ' + (e?.message ?? e));
        } finally {
            setEdkLoading(false);
        }
    };

    const commitEdk = () => {
        if (!edkPreview || edkPreview.valid.length === 0) return;
        if (!confirm(`Importer ${edkPreview.valid.length} transaction(s) EDK ?`)) return;
        setEdkCommitting(true);
        router.post('/fuel/import/edk/commit', { token: edkPreview.token }, {
            preserveScroll: true,
            onSuccess: onClose,
            onFinish: () => setEdkCommitting(false),
        });
    };

    const previewFleeti = async () => {
        if (!fleetiFile) return;
        setFleetiLoading(true);
        const data = new FormData();
        data.append('file', fleetiFile);
        try {
            const res = await apiFetch('/fuel/import/fleeti/preview', { method: 'POST', body: data });
            const json = await res.json().catch(() => null);
            if (!res.ok || !json?.totals) { alert("Échec de l'analyse Fleeti : " + (json?.error || json?.message || `Erreur ${res.status}`)); return; }
            setFleetiPreview(json);
        } catch (e: any) {
            alert('Erreur réseau : ' + (e?.message ?? e));
        } finally {
            setFleetiLoading(false);
        }
    };

    const commitFleeti = () => {
        if (!fleetiPreview || fleetiPreview.valid.length === 0) return;
        if (!confirm(`Importer ${fleetiPreview.valid.length} jours de données Fleeti ?`)) return;
        setFleetiCommitting(true);
        router.post('/fuel/import/fleeti/commit', { token: fleetiPreview.token }, {
            preserveScroll: true,
            onSuccess: onClose,
            onFinish: () => setFleetiCommitting(false),
        });
    };

    return (
        <Drawer
            open
            onClose={onClose}
            size="lg"
            icon={<Upload size={18} className="text-[var(--color-primary)]" />}
            title="Importer du carburant"
        >
            <Tabs
                active={tab}
                onChange={setTab}
                tabs={[
                    { key: 'edk', label: 'EDK (cartes)', icon: <Fuel size={15} /> },
                    { key: 'fleeti', label: 'Fleeti (rapport)', icon: <FileSpreadsheet size={15} /> },
                ]}
            />

            <div className="pt-4">
                {tab === 'edk' && (
                    <div className="space-y-3">
                        <p className="text-xs text-[var(--color-text-muted)]">Fichier CSV exporté depuis le portail EDK. Le montant FCFA ÷ prix au litre = litres.</p>
                        <FormInput label="Prix au litre (FCFA)" type="number" step="0.01" value={edkPrice} onChange={(e) => setEdkPrice(e.target.value)} />
                        <input ref={edkInputRef} type="file" accept=".csv,.txt" onChange={(e) => setEdkFile(e.target.files?.[0] ?? null)}
                            className="block w-full text-sm text-[var(--color-text)] file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-[var(--color-primary)] file:text-white hover:file:bg-[var(--color-primary)]/90" />
                        <Button icon={<Upload size={14} />} onClick={previewEdk} loading={edkLoading} disabled={!edkFile}>Prévisualiser</Button>

                        {edkPreview && (
                            <div className="space-y-3">
                                <div className="flex flex-wrap gap-2">
                                    <Badge variant="success">{edkPreview.totals.count_valid} OK</Badge>
                                    {edkPreview.totals.count_invalid > 0 && <Badge variant="warning">{edkPreview.totals.count_invalid} à vérifier</Badge>}
                                    <Badge variant="info">{formatNumber(edkPreview.totals.total_litres, 0)} L</Badge>
                                    <Badge variant="muted">{formatNumber(edkPreview.totals.total_fcfa, 0)} FCFA</Badge>
                                </div>
                                {edkPreview.valid.length > 0 && (
                                    <div className="max-h-64 overflow-auto border border-[var(--color-border)] rounded">
                                        <table className="w-full text-xs">
                                            <thead className="bg-[var(--color-surface-hover)] sticky top-0"><tr>
                                                <th className="text-left p-2">Date</th><th className="text-left p-2">Camion</th><th className="text-right p-2">FCFA</th><th className="text-right p-2">L</th>
                                            </tr></thead>
                                            <tbody>
                                                {edkPreview.valid.slice(0, 50).map((r) => (
                                                    <tr key={r.line} className="border-t border-[var(--color-border)]">
                                                        <td className="p-2">{r.date_display}</td><td className="p-2 font-medium">{r.truck_matricule}</td>
                                                        <td className="p-2 text-right">{formatNumber(r.montant, 0)}</td><td className="p-2 text-right font-medium">{formatNumber(r.litres, 1)}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                                {edkPreview.invalid.length > 0 && (
                                    <details className="text-xs"><summary className="cursor-pointer text-[var(--color-warning)]"><AlertTriangle size={12} className="inline" /> {edkPreview.invalid.length} ligne(s) ignorée(s)</summary>
                                        <ul className="mt-2 space-y-1 pl-4">{edkPreview.invalid.slice(0, 20).map((r) => <li key={r.line} className="text-[var(--color-text-muted)]">L{r.line} — {r.reason}</li>)}</ul>
                                    </details>
                                )}
                                <Button variant="primary" icon={<CheckCircle2 size={14} />} onClick={commitEdk} loading={edkCommitting} disabled={edkPreview.valid.length === 0}>
                                    Importer {edkPreview.valid.length} transactions
                                </Button>
                            </div>
                        )}
                    </div>
                )}

                {tab === 'fleeti' && (
                    <div className="space-y-3">
                        <p className="text-xs text-[var(--color-text-muted)]">Fichier XLSX du rapport résumé Fleeti (remplissages, vidages, km, consommation par camion).</p>
                        <input ref={fleetiInputRef} type="file" accept=".xlsx,.xls" onChange={(e) => setFleetiFile(e.target.files?.[0] ?? null)}
                            className="block w-full text-sm text-[var(--color-text)] file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-[var(--color-info)] file:text-white hover:file:bg-[var(--color-info)]/90" />
                        <Button icon={<Upload size={14} />} onClick={previewFleeti} loading={fleetiLoading} disabled={!fleetiFile}>Prévisualiser</Button>

                        {fleetiPreview && (
                            <div className="space-y-3">
                                <div className="flex flex-wrap gap-2">
                                    <Badge variant="success">{fleetiPreview.totals.count_rows} jours</Badge>
                                    <Badge variant="info">{fleetiPreview.totals.count_trucks} camions</Badge>
                                    <Badge variant="info">{formatNumber(fleetiPreview.totals.litres_refilled, 0)} L remplis</Badge>
                                    <Badge variant="warning">{formatNumber(fleetiPreview.totals.litres_consumed, 0)} L consommés</Badge>
                                    {fleetiPreview.period.from && <Badge variant="primary">{fleetiPreview.period.from} → {fleetiPreview.period.to}</Badge>}
                                </div>
                                <div className="max-h-64 overflow-auto border border-[var(--color-border)] rounded">
                                    <table className="w-full text-xs">
                                        <thead className="bg-[var(--color-surface-hover)] sticky top-0"><tr>
                                            <th className="text-left p-2">Date</th><th className="text-left p-2">Camion</th><th className="text-right p-2">Km</th><th className="text-right p-2">Conso L</th><th className="text-right p-2">Remplis L</th>
                                        </tr></thead>
                                        <tbody>
                                            {fleetiPreview.valid.slice(0, 100).map((r, idx) => (
                                                <tr key={idx} className="border-t border-[var(--color-border)]">
                                                    <td className="p-2">{r.date_display}</td><td className="p-2 font-medium">{r.truck_matricule}</td>
                                                    <td className="p-2 text-right">{formatNumber(r.kilometers, 0)}</td><td className="p-2 text-right font-medium">{formatNumber(r.consumed, 1)}</td>
                                                    <td className="p-2 text-right">{r.refills_volume > 0 ? formatNumber(r.refills_volume, 0) : '-'}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                                {fleetiPreview.invalid.length > 0 && (
                                    <details className="text-xs"><summary className="cursor-pointer text-[var(--color-warning)]"><AlertTriangle size={12} className="inline" /> {fleetiPreview.invalid.length} ligne(s) ignorée(s)</summary>
                                        <ul className="mt-2 space-y-1 pl-4">{fleetiPreview.invalid.map((r, idx) => <li key={idx} className="text-[var(--color-text-muted)]">{r.reason}</li>)}</ul>
                                    </details>
                                )}
                                <Button variant="primary" icon={<CheckCircle2 size={14} />} onClick={commitFleeti} loading={fleetiCommitting}>
                                    Importer {fleetiPreview.valid.length} jours
                                </Button>
                            </div>
                        )}
                    </div>
                )}
            </div>
        </Drawer>
    );
}
