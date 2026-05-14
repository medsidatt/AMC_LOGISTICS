import { Head, router } from '@inertiajs/react';
import { useState, useRef } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import FormInput from '@/components/ui/FormInput';
import { formatNumber } from '@/utils/formatters';
import { Upload, CheckCircle2, AlertTriangle, Fuel, FileSpreadsheet } from 'lucide-react';

interface EdkValidRow {
    line: number;
    txn_id: string;
    date: string;
    date_display: string;
    montant: number;
    litres: number;
    carte: string;
    porteur: string;
    truck_id: number;
    truck_matricule: string;
    driver_id: number | null;
    driver_name: string | null;
}

interface EdkInvalidRow {
    line: number;
    reason: string;
    [key: string]: any;
}

interface EdkPreview {
    valid: EdkValidRow[];
    invalid: EdkInvalidRow[];
    totals: { count_valid: number; count_invalid: number; total_litres: number; total_fcfa: number };
    token: string;
}

interface FleetiValidRow {
    sheet: number;
    row: number;
    truck_id: number;
    truck_matricule: string;
    date: string;
    date_display: string;
    kilometers: number;
    volume_initial: number;
    volume_final: number;
    consumed: number;
    consumed_per_100km: number | null;
    refills_count: number;
    refills_volume: number;
    drains_count: number;
    drains_volume: number;
}

interface FleetiPreview {
    valid: FleetiValidRow[];
    invalid: { line: number; reason: string; raw?: any }[];
    period: { from: string | null; to: string | null };
    totals: { count_rows: number; count_trucks: number; litres_refilled: number; litres_consumed: number; km: number };
    token: string;
}

interface RecentEdk {
    id: number;
    date: string | null;
    truck: string | null;
    driver: string | null;
    amount: number;
    litres: number;
    transaction_id: string | null;
}

interface RecentFleeti {
    id: number;
    date: string | null;
    truck: string | null;
    kilometers: number;
    consumed: number;
    refills_volume: number;
    refills_count: number;
}

interface Props {
    pricePerLitre: number;
    recentEdk: RecentEdk[];
    recentFleeti: RecentFleeti[];
    totals: {
        edk_transactions: number;
        edk_litres: number;
        edk_fcfa: number;
        fleeti_days: number;
        fleeti_litres: number;
    };
}

export default function FuelImportPage({ pricePerLitre, recentEdk, recentFleeti, totals }: Props) {
    // EDK state
    const [edkFile, setEdkFile] = useState<File | null>(null);
    const [edkPrice, setEdkPrice] = useState<string>(String(pricePerLitre));
    const [edkPreview, setEdkPreview] = useState<EdkPreview | null>(null);
    const [edkLoading, setEdkLoading] = useState(false);
    const [edkCommitting, setEdkCommitting] = useState(false);
    const edkInputRef = useRef<HTMLInputElement>(null);

    // Fleeti state
    const [fleetiFile, setFleetiFile] = useState<File | null>(null);
    const [fleetiPreview, setFleetiPreview] = useState<FleetiPreview | null>(null);
    const [fleetiLoading, setFleetiLoading] = useState(false);
    const [fleetiCommitting, setFleetiCommitting] = useState(false);
    const fleetiInputRef = useRef<HTMLInputElement>(null);

    const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';

    const previewEdk = async () => {
        if (!edkFile) return;
        setEdkLoading(true);
        const data = new FormData();
        data.append('file', edkFile);
        data.append('price_per_litre', edkPrice);
        try {
            const res = await fetch('/fuel/import/edk/preview', {
                method: 'POST',
                body: data,
                headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
            });
            const json = await res.json();
            setEdkPreview(json);
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
            onFinish: () => {
                setEdkCommitting(false);
                setEdkPreview(null);
                setEdkFile(null);
                if (edkInputRef.current) edkInputRef.current.value = '';
            },
        });
    };

    const previewFleeti = async () => {
        if (!fleetiFile) return;
        setFleetiLoading(true);
        const data = new FormData();
        data.append('file', fleetiFile);
        try {
            const res = await fetch('/fuel/import/fleeti/preview', {
                method: 'POST',
                body: data,
                headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
            });
            const json: FleetiPreview = await res.json();
            setFleetiPreview(json);
        } finally {
            setFleetiLoading(false);
        }
    };

    const commitFleeti = () => {
        if (!fleetiPreview || fleetiPreview.valid.length === 0) return;
        if (!confirm(`Importer ${fleetiPreview.valid.length} jours de données Fleeti (${fleetiPreview.totals.count_trucks} camions) ?`)) return;
        setFleetiCommitting(true);
        router.post('/fuel/import/fleeti/commit', { token: fleetiPreview.token }, {
            preserveScroll: true,
            onFinish: () => {
                setFleetiCommitting(false);
                setFleetiPreview(null);
                setFleetiFile(null);
                if (fleetiInputRef.current) fleetiInputRef.current.value = '';
            },
        });
    };

    return (
        <AuthenticatedLayout title="Import carburant">
            <Head title="Import carburant" />

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {/* EDK */}
                <Card
                    header={
                        <div className="flex items-center gap-2">
                            <Fuel size={16} className="text-[var(--color-primary)]" />
                            <span className="text-sm font-semibold">Import EDK (recharges de cartes)</span>
                        </div>
                    }
                >
                    <p className="text-xs text-[var(--color-text-muted)] mb-3">
                        Fichier CSV exporté depuis le portail EDK. Le montant en FCFA est divisé par le prix au litre pour obtenir les litres.
                    </p>
                    <FormInput
                        label="Prix au litre (FCFA)"
                        type="number"
                        step="0.01"
                        value={edkPrice}
                        onChange={(e) => setEdkPrice(e.target.value)}
                    />
                    <input
                        ref={edkInputRef}
                        type="file"
                        accept=".csv,.txt"
                        onChange={(e) => setEdkFile(e.target.files?.[0] ?? null)}
                        className="block w-full text-sm text-[var(--color-text)] file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-[var(--color-primary)] file:text-white hover:file:bg-[var(--color-primary)]/90"
                    />
                    <div className="flex gap-2 mt-3">
                        <Button
                            icon={<Upload size={14} />}
                            onClick={previewEdk}
                            loading={edkLoading}
                            disabled={!edkFile}
                        >
                            Prévisualiser
                        </Button>
                    </div>

                    {edkPreview && (
                        <div className="mt-4 space-y-3">
                            <div className="flex flex-wrap gap-2">
                                <Badge variant="success">{edkPreview.totals.count_valid} OK</Badge>
                                {edkPreview.totals.count_invalid > 0 && (
                                    <Badge variant="warning">{edkPreview.totals.count_invalid} à vérifier</Badge>
                                )}
                                <Badge variant="info">{formatNumber(edkPreview.totals.total_litres, 0)} L</Badge>
                                <Badge variant="muted">{formatNumber(edkPreview.totals.total_fcfa, 0)} FCFA</Badge>
                            </div>

                            {edkPreview.valid.length > 0 && (
                                <div className="max-h-64 overflow-auto border border-[var(--color-border)] rounded">
                                    <table className="w-full text-xs">
                                        <thead className="bg-[var(--color-surface-hover)] sticky top-0">
                                            <tr>
                                                <th className="text-left p-2">Date</th>
                                                <th className="text-left p-2">Camion</th>
                                                <th className="text-left p-2">Chauffeur</th>
                                                <th className="text-right p-2">FCFA</th>
                                                <th className="text-right p-2">L</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {edkPreview.valid.slice(0, 50).map((r) => (
                                                <tr key={r.line} className="border-t border-[var(--color-border)]">
                                                    <td className="p-2">{r.date_display}</td>
                                                    <td className="p-2 font-medium">{r.truck_matricule}</td>
                                                    <td className="p-2 text-[var(--color-text-muted)]">{r.driver_name ?? '-'}</td>
                                                    <td className="p-2 text-right">{formatNumber(r.montant, 0)}</td>
                                                    <td className="p-2 text-right font-medium">{formatNumber(r.litres, 1)}</td>
                                                </tr>
                                            ))}
                                            {edkPreview.valid.length > 50 && (
                                                <tr><td colSpan={5} className="p-2 text-center text-[var(--color-text-muted)]">… {edkPreview.valid.length - 50} autres</td></tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            )}

                            {edkPreview.invalid.length > 0 && (
                                <details className="text-xs">
                                    <summary className="cursor-pointer text-[var(--color-warning)]"><AlertTriangle size={12} className="inline" /> {edkPreview.invalid.length} ligne(s) ignorée(s)</summary>
                                    <ul className="mt-2 space-y-1 pl-4">
                                        {edkPreview.invalid.slice(0, 20).map((r) => (
                                            <li key={r.line} className="text-[var(--color-text-muted)]">L{r.line} — {r.reason}{r.porteur ? ` : ${r.porteur}` : ''}</li>
                                        ))}
                                    </ul>
                                </details>
                            )}

                            <Button
                                variant="primary"
                                icon={<CheckCircle2 size={14} />}
                                onClick={commitEdk}
                                loading={edkCommitting}
                                disabled={edkPreview.valid.length === 0}
                            >
                                Importer {edkPreview.valid.length} transactions
                            </Button>
                        </div>
                    )}
                </Card>

                {/* Fleeti */}
                <Card
                    header={
                        <div className="flex items-center gap-2">
                            <FileSpreadsheet size={16} className="text-[var(--color-info)]" />
                            <span className="text-sm font-semibold">Import Fleeti (rapport mensuel)</span>
                        </div>
                    }
                >
                    <p className="text-xs text-[var(--color-text-muted)] mb-3">
                        Fichier XLSX du rapport résumé Fleeti. Importe les remplissages, vidages, km parcourus et consommation par camion.
                    </p>
                    <input
                        ref={fleetiInputRef}
                        type="file"
                        accept=".xlsx,.xls"
                        onChange={(e) => setFleetiFile(e.target.files?.[0] ?? null)}
                        className="block w-full text-sm text-[var(--color-text)] file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-[var(--color-info)] file:text-white hover:file:bg-[var(--color-info)]/90"
                    />
                    <div className="flex gap-2 mt-3">
                        <Button icon={<Upload size={14} />} onClick={previewFleeti} loading={fleetiLoading} disabled={!fleetiFile}>
                            Prévisualiser
                        </Button>
                    </div>

                    {fleetiPreview && (
                        <div className="mt-4 space-y-3">
                            <div className="flex flex-wrap gap-2">
                                <Badge variant="success">{fleetiPreview.totals.count_rows} jours</Badge>
                                <Badge variant="info">{fleetiPreview.totals.count_trucks} camions</Badge>
                                <Badge variant="info">{formatNumber(fleetiPreview.totals.litres_refilled, 0)} L remplis</Badge>
                                <Badge variant="warning">{formatNumber(fleetiPreview.totals.litres_consumed, 0)} L consommés</Badge>
                                <Badge variant="muted">{formatNumber(fleetiPreview.totals.km, 0)} km</Badge>
                                {fleetiPreview.period.from && (
                                    <Badge variant="primary">{fleetiPreview.period.from} → {fleetiPreview.period.to}</Badge>
                                )}
                            </div>

                            <div className="max-h-80 overflow-auto border border-[var(--color-border)] rounded">
                                <table className="w-full text-xs">
                                    <thead className="bg-[var(--color-surface-hover)] sticky top-0">
                                        <tr>
                                            <th className="text-left p-2">Date</th>
                                            <th className="text-left p-2">Camion</th>
                                            <th className="text-right p-2">Km</th>
                                            <th className="text-right p-2">Conso L</th>
                                            <th className="text-right p-2">L/100km</th>
                                            <th className="text-right p-2">Remplis L</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {fleetiPreview.valid.slice(0, 100).map((r, idx) => (
                                            <tr key={idx} className="border-t border-[var(--color-border)]">
                                                <td className="p-2">{r.date_display}</td>
                                                <td className="p-2 font-medium">{r.truck_matricule}</td>
                                                <td className="p-2 text-right">{formatNumber(r.kilometers, 0)}</td>
                                                <td className="p-2 text-right font-medium">{formatNumber(r.consumed, 1)}</td>
                                                <td className="p-2 text-right">{r.consumed_per_100km !== null ? formatNumber(r.consumed_per_100km, 1) : '-'}</td>
                                                <td className="p-2 text-right">{r.refills_volume > 0 ? formatNumber(r.refills_volume, 0) : '-'}</td>
                                            </tr>
                                        ))}
                                        {fleetiPreview.valid.length > 100 && (
                                            <tr><td colSpan={6} className="p-2 text-center text-[var(--color-text-muted)]">… {fleetiPreview.valid.length - 100} autres jours</td></tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>

                            {fleetiPreview.invalid.length > 0 && (
                                <details className="text-xs">
                                    <summary className="cursor-pointer text-[var(--color-warning)]"><AlertTriangle size={12} className="inline" /> {fleetiPreview.invalid.length} ligne(s) ignorée(s)</summary>
                                    <ul className="mt-2 space-y-1 pl-4">
                                        {fleetiPreview.invalid.map((r, idx) => (
                                            <li key={idx} className="text-[var(--color-text-muted)]">{r.reason}</li>
                                        ))}
                                    </ul>
                                </details>
                            )}

                            <Button variant="primary" icon={<CheckCircle2 size={14} />} onClick={commitFleeti} loading={fleetiCommitting}>
                                Importer {fleetiPreview.valid.length} jours
                            </Button>
                        </div>
                    )}
                </Card>
            </div>

            {/* Two separate tables: EDK transactions & Fleeti daily records */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
                <Card
                    header={
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <Fuel size={16} className="text-[var(--color-primary)]" />
                                <span className="text-sm font-semibold">Transactions EDK</span>
                            </div>
                            <div className="text-xs text-[var(--color-text-muted)]">
                                <span className="font-semibold text-[var(--color-text)]">{formatNumber(totals.edk_transactions)}</span> txn ·{' '}
                                <span className="font-semibold text-[var(--color-text)]">{formatNumber(totals.edk_litres, 0)}</span> L ·{' '}
                                <span className="font-semibold text-[var(--color-text)]">{formatNumber(totals.edk_fcfa, 0)}</span> FCFA
                            </div>
                        </div>
                    }
                >
                    {recentEdk.length === 0 ? (
                        <p className="text-sm text-[var(--color-text-muted)] text-center py-4">Aucune transaction importée.</p>
                    ) : (
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-xs text-[var(--color-text-muted)] uppercase border-b border-[var(--color-border)]">
                                    <th className="text-left py-2">Date</th>
                                    <th className="text-left py-2">Camion</th>
                                    <th className="text-left py-2">Chauffeur</th>
                                    <th className="text-right py-2">FCFA</th>
                                    <th className="text-right py-2">Litres</th>
                                </tr>
                            </thead>
                            <tbody>
                                {recentEdk.map((r) => (
                                    <tr key={r.id} className="border-b border-[var(--color-border)] last:border-0">
                                        <td className="py-2 whitespace-nowrap">{r.date}</td>
                                        <td className="py-2 font-medium">{r.truck}</td>
                                        <td className="py-2 text-[var(--color-text-muted)]">{r.driver ?? '-'}</td>
                                        <td className="py-2 text-right">{formatNumber(r.amount, 0)}</td>
                                        <td className="py-2 text-right font-medium">{formatNumber(r.litres, 1)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </Card>

                <Card
                    header={
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <FileSpreadsheet size={16} className="text-[var(--color-info)]" />
                                <span className="text-sm font-semibold">Données journalières Fleeti</span>
                            </div>
                            <div className="text-xs text-[var(--color-text-muted)]">
                                <span className="font-semibold text-[var(--color-text)]">{formatNumber(totals.fleeti_days)}</span> jours ·{' '}
                                <span className="font-semibold text-[var(--color-text)]">{formatNumber(totals.fleeti_litres, 0)}</span> L consommés
                            </div>
                        </div>
                    }
                >
                    {recentFleeti.length === 0 ? (
                        <p className="text-sm text-[var(--color-text-muted)] text-center py-4">Aucun rapport importé.</p>
                    ) : (
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-xs text-[var(--color-text-muted)] uppercase border-b border-[var(--color-border)]">
                                    <th className="text-left py-2">Date</th>
                                    <th className="text-left py-2">Camion</th>
                                    <th className="text-right py-2">Km</th>
                                    <th className="text-right py-2">Conso L</th>
                                    <th className="text-right py-2">Remplis L</th>
                                </tr>
                            </thead>
                            <tbody>
                                {recentFleeti.map((r) => (
                                    <tr key={r.id} className="border-b border-[var(--color-border)] last:border-0">
                                        <td className="py-2 whitespace-nowrap">{r.date}</td>
                                        <td className="py-2 font-medium">{r.truck}</td>
                                        <td className="py-2 text-right">{formatNumber(r.kilometers, 0)}</td>
                                        <td className="py-2 text-right font-medium">{formatNumber(r.consumed, 1)}</td>
                                        <td className="py-2 text-right text-[var(--color-text-muted)]">
                                            {r.refills_volume > 0 ? `${formatNumber(r.refills_volume, 0)} (${r.refills_count})` : '-'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
