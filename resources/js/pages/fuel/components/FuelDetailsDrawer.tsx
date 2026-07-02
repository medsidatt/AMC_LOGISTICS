import { useCallback, useEffect, useState } from 'react';
import Drawer from '@/components/ui/Drawer';
import Badge from '@/components/ui/Badge';
import Tabs from '@/components/ui/Tabs';
import DetailPanel, { DetailItem } from '@/components/ui/DetailPanel';
import EmptyState from '@/components/ui/EmptyState';
import { apiFetch } from '@/utils/csrf';
import { formatNumber } from '@/utils/formatters';
import { Fuel, FileSpreadsheet, Info, ShieldCheck, History, Loader2 } from 'lucide-react';

interface Props {
    type: 'edk' | 'fleeti';
    id: number;
    onClose: () => void;
}

/**
 * Tabbed fuel-record details (Détails / Validation / Historique). Validation
 * cross-references the other data source (EDK ↔ Fleeti) for the same truck/day —
 * a budget-vs-consumption check between EDK card recharges (estimated litres) and
 * Fleeti GPS consumption. Read-only. EDK figures are estimates, not measured purchases.
 */
export default function FuelDetailsDrawer({ type, id, onClose }: Props) {
    const [tab, setTab] = useState('details');
    const [data, setData] = useState<any | null>(null);
    const [loading, setLoading] = useState(true);

    const load = useCallback(async () => {
        setLoading(true);
        try {
            const res = await apiFetch(`/fuel/${type}/${id}`);
            if (res.ok) setData(await res.json());
        } finally {
            setLoading(false);
        }
    }, [type, id]);

    useEffect(() => { load(); }, [load]);

    const r = data?.record;
    const v = data?.validation;
    const history = data?.history ?? [];

    return (
        <Drawer
            open
            onClose={onClose}
            size="lg"
            icon={type === 'edk' ? <Fuel size={18} className="text-[var(--color-primary)]" /> : <FileSpreadsheet size={18} className="text-[var(--color-info)]" />}
            title={type === 'edk' ? `Recharge ${r?.transaction_id ?? ''}` : `Consommation ${r?.truck ?? ''}`}
        >
            {loading ? (
                <div className="flex flex-col items-center justify-center py-16">
                    <Loader2 size={28} className="animate-spin text-[var(--color-primary)]" />
                    <p className="text-sm text-[var(--color-text-muted)] mt-3">Chargement…</p>
                </div>
            ) : !r ? (
                <p className="text-sm text-[var(--color-text-muted)] text-center py-10">Introuvable.</p>
            ) : (
                <>
                    <Tabs
                        active={tab}
                        onChange={setTab}
                        tabs={[
                            { key: 'details', label: 'Détails', icon: <Info size={15} /> },
                            { key: 'validation', label: 'Validation', icon: <ShieldCheck size={15} /> },
                            { key: 'history', label: `Historique${history.length ? ` (${history.length})` : ''}`, icon: <History size={15} /> },
                        ]}
                    />
                    <div className="pt-4">
                        {tab === 'details' && (
                            type === 'edk' ? (
                                <DetailPanel columns={2}>
                                    <DetailItem label="Date" value={r.date} />
                                    <DetailItem label="Camion" value={r.truck} />
                                    <DetailItem label="Chauffeur" value={r.driver} />
                                    <DetailItem label="Montant rechargé" value={`${formatNumber(r.amount, 0)} FCFA`} />
                                    <DetailItem label="Litres estimés" value={`${formatNumber(r.litres, 1)} L`} />
                                    <DetailItem label="Prix / litre" value={`${formatNumber(r.price_per_litre, 0)} FCFA`} />
                                    <DetailItem label="Réf. recharge" value={r.transaction_id} />
                                    <DetailItem label="Importé par" value={r.imported_by ? `${r.imported_by} · ${r.imported_at}` : '—'} />
                                </DetailPanel>
                            ) : (
                                <DetailPanel columns={2}>
                                    <DetailItem label="Date" value={r.date} />
                                    <DetailItem label="Camion" value={r.truck} />
                                    <DetailItem label="Kilomètres" value={`${formatNumber(r.kilometers, 0)} km`} />
                                    <DetailItem label="Consommé" value={`${formatNumber(r.consumed, 1)} L`} />
                                    <DetailItem label="L / 100km" value={r.consumed_per_100km !== null ? formatNumber(r.consumed_per_100km, 1) : '—'} />
                                    <DetailItem label="Volume initial → final" value={`${formatNumber(r.volume_initial, 0)} → ${formatNumber(r.volume_final, 0)} L`} />
                                    <DetailItem label="Remplissages" value={`${formatNumber(r.refills_volume, 0)} L (${r.refills_count})`} />
                                    <DetailItem label="Vidanges" value={`${formatNumber(r.drains_volume, 0)} L (${r.drains_count})`} />
                                </DetailPanel>
                            )
                        )}

                        {tab === 'validation' && (
                            !v ? (
                                <EmptyState icon={<ShieldCheck size={28} />} title="Aucune correspondance" description={type === 'edk' ? 'Pas de données Fleeti pour ce camion à cette date.' : 'Pas de recharge EDK pour ce camion à cette date.'} />
                            ) : type === 'edk' ? (
                                <div className="space-y-3">
                                    <p className="text-xs text-[var(--color-text-muted)]">Consommation Fleeti (GPS) pour ce camion le {v.date} :</p>
                                    <DetailPanel columns={2}>
                                        <DetailItem label="Kilomètres" value={`${formatNumber(v.kilometers, 0)} km`} />
                                        <DetailItem label="Consommé (GPS)" value={`${formatNumber(v.consumed, 1)} L`} />
                                        <DetailItem label="L / 100km" value={v.consumed_per_100km !== null ? formatNumber(v.consumed_per_100km, 1) : '—'} />
                                        <DetailItem label="Remplis (GPS)" value={`${formatNumber(v.refills_volume, 0)} L`} />
                                    </DetailPanel>
                                    <div className="rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-hover)] p-3 text-sm flex items-center justify-between">
                                        <span className="text-[var(--color-text-secondary)]">Écart estimé</span>
                                        <Badge variant="info">{formatNumber(r.litres - v.refills_volume, 1)} L</Badge>
                                    </div>
                                </div>
                            ) : (
                                <div className="space-y-3">
                                    <div className="flex flex-wrap gap-2">
                                        <Badge variant="success">{v.count} recharge(s) EDK</Badge>
                                        <Badge variant="info">{formatNumber(v.litres, 1)} L</Badge>
                                        <Badge variant="muted">{formatNumber(v.amount, 0)} FCFA</Badge>
                                    </div>
                                    <div className="rounded-lg border border-[var(--color-border)] divide-y divide-[var(--color-border)]">
                                        {v.transactions.map((t: any) => (
                                            <div key={t.id} className="flex items-center justify-between px-3 py-2 text-sm">
                                                <span>{t.driver ?? '—'}</span>
                                                <span className="text-[var(--color-text-muted)]">{formatNumber(t.amount, 0)} FCFA · {formatNumber(t.litres, 1)} L</span>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )
                        )}

                        {tab === 'history' && (
                            history.length === 0 ? (
                                <EmptyState icon={<History size={28} />} title="Aucun historique" description="Aucun autre enregistrement pour ce camion." />
                            ) : (
                                <div className="rounded-lg border border-[var(--color-border)] divide-y divide-[var(--color-border)]">
                                    {history.map((h: any) => (
                                        <div key={h.id} className="flex items-center justify-between px-3 py-2 text-sm">
                                            <span className="whitespace-nowrap">{h.date}</span>
                                            <span className="text-[var(--color-text-muted)]">
                                                {type === 'edk'
                                                    ? `${formatNumber(h.amount, 0)} FCFA · ${formatNumber(h.litres, 1)} L`
                                                    : `${formatNumber(h.consumed, 1)} L · ${formatNumber(h.kilometers, 0)} km`}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            )
                        )}
                    </div>
                </>
            )}
        </Drawer>
    );
}
