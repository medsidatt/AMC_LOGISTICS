import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import Drawer from '@/components/ui/Drawer';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import Tabs from '@/components/ui/Tabs';
import EmptyState from '@/components/ui/EmptyState';
import DetailPanel, { DetailItem } from '@/components/ui/DetailPanel';
import FormInput from '@/components/ui/FormInput';
import { Truck as TruckIcon, Wrench, ShieldAlert, Receipt, FileText } from 'lucide-react';
import type { BoardTruck, InspectionIssue } from '../types';

interface Props {
    truck: BoardTruck;
    canRecord: boolean;
    onRecord: () => void;
    onClose: () => void;
}

const SEVERITY_VARIANT: Record<string, 'default' | 'warning' | 'danger'> = { minor: 'default', major: 'warning', critical: 'danger' };
const statusBadge = (s?: string) => {
    const v = s === 'red' ? 'danger' : s === 'yellow' ? 'warning' : 'success';
    const l = s === 'red' ? 'Urgent' : s === 'yellow' ? 'Bientôt' : 'OK';
    return <Badge variant={v}>{l}</Badge>;
};
const fcfa = (v: string | null | undefined) => (v == null || v === '' ? null : `${Number(v).toLocaleString('fr-FR')} FCFA`);

/** Per-truck maintenance details: scheduling (Échéances) + inspection findings with
 * inline cost/devis (Problèmes). Uses board-row data; no stacked drawers. */
export default function TruckMaintenanceDrawer({ truck, canRecord, onRecord, onClose }: Props) {
    const [tab, setTab] = useState('echeances');
    const [costId, setCostId] = useState<number | null>(null);
    const costForm = useForm<Record<string, any>>({ parts_cost: '', labor_cost: '', devis: null as File | null });

    const openCost = (i: InspectionIssue) => {
        setCostId(i.id);
        costForm.setData({ parts_cost: i.parts_cost ?? '', labor_cost: i.labor_cost ?? '', devis: null });
        costForm.clearErrors();
    };
    const submitCost = (e: React.FormEvent) => {
        e.preventDefault();
        if (!costId) return;
        costForm.post(`/maintenance/issues/${costId}/cost`, { forceFormData: true, preserveScroll: true, onSuccess: onClose });
    };
    const costTotal = (Number(costForm.data.parts_cost) || 0) + (Number(costForm.data.labor_cost) || 0);

    return (
        <Drawer
            open
            onClose={onClose}
            size="lg"
            icon={<TruckIcon size={18} className="text-[var(--color-primary)]" />}
            title={truck.matricule}
            footer={canRecord ? <Button icon={<Wrench size={15} />} onClick={onRecord}>Enregistrer maintenance</Button> : undefined}
        >
            <div className="flex flex-wrap items-center gap-2">
                {statusBadge(truck.profiles.find((p) => p.type === 'general')?.status)}
                <span className="text-sm text-[var(--color-text-muted)] font-mono">{truck.total_kilometers?.toLocaleString('fr-FR')} km</span>
            </div>

            <Tabs active={tab} onChange={setTab} tabs={[
                { key: 'echeances', label: 'Échéances', icon: <Wrench size={15} /> },
                { key: 'problemes', label: `Problèmes${truck.open_inspection_issues ? ` (${truck.open_inspection_issues})` : ''}`, icon: <ShieldAlert size={15} /> },
            ]} />

            <div className="pt-4">
                {tab === 'echeances' && (
                    truck.profiles.length === 0 ? (
                        <EmptyState icon={<Wrench size={28} />} title="Aucune règle" description="Aucun intervalle de maintenance défini pour ce camion." />
                    ) : (
                        <div className="space-y-3">
                            {truck.profiles.map((p) => (
                                <div key={p.type} className="rounded-lg border border-[var(--color-border)] p-3">
                                    <div className="flex items-center justify-between mb-2">
                                        <span className="font-medium capitalize">{p.type}</span>
                                        {statusBadge(p.status)}
                                    </div>
                                    <DetailPanel columns={3}>
                                        <DetailItem label="Intervalle" value={`${p.interval_km?.toLocaleString('fr-FR')} km`} />
                                        <DetailItem label="Prochaine à" value={p.next_km != null ? `${p.next_km.toLocaleString('fr-FR')} km` : '—'} />
                                        <DetailItem label="Restant" value={p.remaining != null ? `${p.remaining.toLocaleString('fr-FR')} km` : '—'} />
                                    </DetailPanel>
                                </div>
                            ))}
                        </div>
                    )
                )}

                {tab === 'problemes' && (
                    truck.inspection_issues.length === 0 ? (
                        <EmptyState icon={<ShieldAlert size={28} />} title="Aucun finding ouvert" description="Aucun problème d'inspection en attente pour ce camion." />
                    ) : (
                        <div className="space-y-2">
                            {truck.inspection_issues.map((issue) => (
                                <div key={issue.id} className="rounded-lg border border-[var(--color-border)] p-3">
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="flex-1 min-w-0">
                                            <span className="font-medium">{issue.category}</span>{' '}
                                            <Badge variant={SEVERITY_VARIANT[issue.severity] ?? 'default'}>{issue.severity}</Badge>
                                            {issue.issue_notes && <span className="block text-xs text-[var(--color-text-muted)]">{issue.issue_notes}</span>}
                                            <span className="block text-xs text-[var(--color-text-muted)]">Inspection du {issue.inspection_date}</span>
                                            <div className="mt-1 flex flex-wrap items-center gap-3 text-xs">
                                                {fcfa(issue.total_cost) ? <span className="font-semibold text-[var(--color-text)]">Coût : {fcfa(issue.total_cost)}</span> : <span className="text-[var(--color-text-muted)]">Aucun coût</span>}
                                                {issue.devis_url && <a href={issue.devis_url} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-1 text-[var(--color-primary)] hover:underline"><FileText size={12} /> Devis</a>}
                                            </div>
                                        </div>
                                        {canRecord && costId !== issue.id && (
                                            <Button size="sm" variant="secondary" type="button" icon={<Receipt size={14} />} onClick={() => openCost(issue)}>Coût</Button>
                                        )}
                                    </div>

                                    {costId === issue.id && (
                                        <form onSubmit={submitCost} className="mt-3 pt-3 border-t border-[var(--color-border)] space-y-2">
                                            <div className="grid grid-cols-2 gap-2">
                                                <FormInput label="Pièces (FCFA)" type="number" min="0" step="0.01" wrapperClass="mb-0" value={costForm.data.parts_cost} onChange={(e) => costForm.setData('parts_cost', e.target.value)} error={costForm.errors.parts_cost} />
                                                <FormInput label="Main d'œuvre (FCFA)" type="number" min="0" step="0.01" wrapperClass="mb-0" value={costForm.data.labor_cost} onChange={(e) => costForm.setData('labor_cost', e.target.value)} error={costForm.errors.labor_cost} />
                                            </div>
                                            <div className="text-sm font-semibold">Total : {costTotal.toLocaleString('fr-FR')} FCFA</div>
                                            <input type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" onChange={(e) => costForm.setData('devis', e.target.files?.[0] ?? null)} className="block w-full text-sm file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-[var(--color-surface-hover)] file:text-[var(--color-text-secondary)]" />
                                            {costForm.errors.devis && <p className="text-xs text-red-600">{costForm.errors.devis}</p>}
                                            <div className="flex justify-end gap-2">
                                                <Button size="sm" variant="ghost" type="button" onClick={() => setCostId(null)}>Annuler</Button>
                                                <Button size="sm" type="submit" loading={costForm.processing}>Enregistrer</Button>
                                            </div>
                                        </form>
                                    )}
                                </div>
                            ))}
                        </div>
                    )
                )}
            </div>
        </Drawer>
    );
}
