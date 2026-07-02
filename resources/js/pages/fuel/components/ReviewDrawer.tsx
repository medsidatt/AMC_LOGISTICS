import { useCallback, useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import Drawer from '@/components/ui/Drawer';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import DetailPanel, { DetailItem } from '@/components/ui/DetailPanel';
import FormInput from '@/components/ui/FormInput';
import { apiFetch } from '@/utils/csrf';
import { formatNumber } from '@/utils/formatters';
import { ClipboardCheck, Loader2, History } from 'lucide-react';

interface Opt { id: number | string; name: string }
interface Outcome { value: string; label: string; requires_truck: boolean }
interface Props { id: number; outcomes: Outcome[]; trucks: Opt[]; onClose: () => void }

/** Review details (immutable proposal + effective values + history) and the reviewer decision form. */
export default function ReviewDrawer({ id, outcomes, trucks, onClose }: Props) {
    const [data, setData] = useState<any | null>(null);
    const [loading, setLoading] = useState(true);
    const [outcome, setOutcome] = useState('');
    const [truckId, setTruckId] = useState('');
    const [note, setNote] = useState('');
    const [saving, setSaving] = useState(false);

    const load = useCallback(async () => {
        setLoading(true);
        try {
            const res = await apiFetch(`/fuel/review/${id}`);
            if (res.ok) setData(await res.json());
        } finally {
            setLoading(false);
        }
    }, [id]);
    useEffect(() => { load(); }, [load]);

    const requiresTruck = outcomes.find((o) => o.value === outcome)?.requires_truck ?? false;

    const submit = () => {
        if (!outcome) return;
        setSaving(true);
        router.post(`/fuel/review/${id}`, { outcome, truck_id: requiresTruck ? truckId : null, note: note || null }, {
            preserveScroll: true,
            onSuccess: onClose,
            onFinish: () => setSaving(false),
        });
    };

    const r = data?.record;
    const eff = data?.effective;
    const prop = data?.proposal;
    const hist = data?.history ?? [];
    const resolved = eff?.review_status === 'RESOLVED';

    return (
        <Drawer open onClose={onClose} size="lg" icon={<ClipboardCheck size={18} className="text-[var(--color-primary)]" />} title={`Revue ${r?.transaction_ref ?? ''}`}>
            {loading ? (
                <div className="flex flex-col items-center justify-center py-16">
                    <Loader2 size={28} className="animate-spin text-[var(--color-primary)]" />
                    <p className="text-sm text-[var(--color-text-muted)] mt-3">Chargement…</p>
                </div>
            ) : !r ? (
                <p className="text-sm text-[var(--color-text-muted)] text-center py-10">Introuvable.</p>
            ) : (
                <div className="space-y-5 pt-2">
                    <DetailPanel columns={2}>
                        <DetailItem label="Date" value={r.date} />
                        <DetailItem label="Type" value={r.type} />
                        <DetailItem label="Montant" value={`${formatNumber(r.amount, 0)} FCFA`} />
                        <DetailItem label="Litres estimés" value={r.estimated_litres != null ? `${formatNumber(r.estimated_litres, 1)} L` : '—'} />
                        <DetailItem label="Plaque détectée" value={r.detected_plate ?? '—'} />
                        <DetailItem label="Porteur" value={r.holder_raw ?? '—'} />
                    </DetailPanel>

                    <div>
                        <div className="text-xs font-semibold uppercase tracking-wide text-[var(--color-text-muted)] mb-2">Proposition (validateur — figée)</div>
                        <div className="flex flex-wrap gap-1.5">
                            {prop.business_findings.length === 0 && prop.technical_findings.length === 0 && (
                                <span className="text-xs text-[var(--color-text-muted)]">Aucune anomalie</span>
                            )}
                            {[...prop.business_findings, ...prop.technical_findings].map((f: string) => <Badge key={f} variant="warning">{f}</Badge>)}
                            <Badge variant={prop.kpi_eligible ? 'success' : 'muted'}>KPI proposé : {prop.kpi_eligible ? 'oui' : 'non'}</Badge>
                            <Badge variant="muted">policy {prop.policy_version}</Badge>
                        </div>
                    </div>

                    <DetailPanel columns={2}>
                        <DetailItem label="Camion (effectif)" value={eff.truck ?? '—'} />
                        <DetailItem label="KPI éligible" value={eff.kpi_eligible ? 'Oui' : 'Non'} />
                        <DetailItem label="Statut revue" value={eff.review_status} />
                        <DetailItem label="Décision" value={eff.review_outcome ?? '—'} />
                        <DetailItem label="Revu par" value={eff.reviewed_by ? `${eff.reviewed_by} · ${eff.reviewed_at}` : '—'} />
                    </DetailPanel>

                    {!resolved && (
                        <div className="rounded-lg border border-[var(--color-border)] p-4 space-y-3">
                            <div className="text-sm font-semibold">Décision</div>
                            <select className="w-full h-10 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] px-3 text-sm" value={outcome} onChange={(e) => setOutcome(e.target.value)}>
                                <option value="">— Choisir une décision —</option>
                                {outcomes.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
                            </select>
                            {requiresTruck && (
                                <select className="w-full h-10 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] px-3 text-sm" value={truckId} onChange={(e) => setTruckId(e.target.value)}>
                                    <option value="">— Choisir un camion —</option>
                                    {trucks.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
                                </select>
                            )}
                            <FormInput label="Note" value={note} onChange={(e) => setNote(e.target.value)} placeholder="Optionnel" />
                            <Button variant="primary" onClick={submit} loading={saving} disabled={!outcome || (requiresTruck && !truckId)}>Enregistrer la décision</Button>
                        </div>
                    )}

                    {hist.length > 0 && (
                        <div>
                            <div className="text-xs font-semibold uppercase tracking-wide text-[var(--color-text-muted)] mb-2 flex items-center gap-1"><History size={13} /> Historique</div>
                            <div className="rounded-lg border border-[var(--color-border)] divide-y divide-[var(--color-border)]">
                                {hist.map((h: any) => (
                                    <div key={h.id} className="px-3 py-2 text-xs">
                                        <div className="flex items-center justify-between">
                                            <span className="font-medium">{h.outcome}</span>
                                            <span className="text-[var(--color-text-muted)]">{h.reviewer ?? '—'} · {h.at}</span>
                                        </div>
                                        {h.note && <div className="text-[var(--color-text-muted)] mt-0.5">{h.note}</div>}
                                        <div className="text-[var(--color-text-muted)] mt-0.5">KPI {String(h.before?.kpi_eligible)} → {String(h.after?.kpi_eligible)}</div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            )}
        </Drawer>
    );
}
