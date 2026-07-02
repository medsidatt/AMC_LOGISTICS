import { useState } from 'react';
import { router } from '@inertiajs/react';
import Drawer from '@/components/ui/Drawer';
import Button from '@/components/ui/Button';
import FormInput from '@/components/ui/FormInput';
import { FileText, PenLine, Pencil, Truck as TruckIcon, CheckCircle2, Clock } from 'lucide-react';
import type { MaintenanceRecord, MaintenanceRefs, MaintenanceStatus } from '../types';

interface Props {
    record: MaintenanceRecord;
    refs: MaintenanceRefs;
    canEdit: boolean;
    canApprove: boolean;
    currentUserName: string;
    onEdit: () => void;
    onClose: () => void;
}

const STATUS_META: Record<MaintenanceStatus, { label: string; pill: string; Icon: typeof Clock }> = {
    pending: { label: 'En attente', pill: 'bg-amber-100 text-amber-800 ring-amber-200', Icon: Clock },
    assigned: { label: 'En attente', pill: 'bg-amber-100 text-amber-800 ring-amber-200', Icon: Clock },
    completed: { label: 'En attente', pill: 'bg-amber-100 text-amber-800 ring-amber-200', Icon: Clock },
    approved: { label: 'Signée', pill: 'bg-emerald-100 text-emerald-800 ring-emerald-200', Icon: CheckCircle2 },
};
const formatKm = (v: number | null | undefined) => (v == null ? '—' : Number(v).toLocaleString('fr-FR') + ' km');

function StatusPill({ status }: { status: MaintenanceStatus }) {
    const m = STATUS_META[status];
    return <span className={`inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold ring-1 ${m.pill}`}><m.Icon size={12} /> {m.label}</span>;
}
function ViewRow({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div className="flex justify-between gap-3 py-1.5 border-b border-[var(--color-border)] last:border-0 text-sm">
            <span className="text-[var(--color-text-muted)] text-xs uppercase tracking-wide font-medium">{label}</span>
            <span className="text-[var(--color-text)] font-medium text-right">{children}</span>
        </div>
    );
}

/** Maintenance record details (read) + in-drawer Sign/Approve + Edit/PDF. No stacked drawers. */
export default function MaintenanceDetailsDrawer({ record: m, refs, canEdit, canApprove, currentUserName, onEdit, onClose }: Props) {
    const [signing, setSigning] = useState(false);
    const [showSign, setShowSign] = useState(false);
    const [signatureName, setSignatureName] = useState(currentUserName);

    const submitSign = () => {
        if (!signatureName.trim()) return;
        setSigning(true);
        router.post(`/maintenance/${m.id}/approve`, { signature_name: signatureName.trim() }, { preserveScroll: true, onSuccess: onClose, onFinish: () => setSigning(false) });
    };

    const items = m.items ?? [];
    const checks = m.control_checks ?? {};
    const checkedEntries = Object.entries(refs.controlChecks).filter(([key]) => checks[key]);
    const filters: Array<[string, boolean | undefined]> = [['Huile', m.filter_oil_changed], ['Hydraulique', m.filter_hydraulic_changed], ['Air', m.filter_air_changed], ['Carburant', m.filter_fuel_changed]];
    const notApproved = m.status !== 'approved';

    return (
        <Drawer
            open
            onClose={onClose}
            size="lg"
            icon={<TruckIcon size={18} className="text-[var(--color-primary)]" />}
            title={`Maintenance N° ${m.id} — ${m.truck}`}
            footer={
                <>
                    <a href={`/maintenance/${m.id}/pdf`} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-1 text-xs px-3 py-2 rounded-lg border border-[var(--color-border)] hover:bg-[var(--color-surface-hover)] text-[var(--color-text-secondary)]"><FileText size={14} /> PDF</a>
                    {canEdit && notApproved && <Button variant="secondary" icon={<Pencil size={15} />} onClick={onEdit}>Modifier</Button>}
                    {canApprove && notApproved && <Button icon={<PenLine size={15} />} onClick={() => setShowSign((s) => !s)}>Signer</Button>}
                </>
            }
        >
            <div className="rounded-lg bg-[var(--color-surface-hover)] border border-[var(--color-border)] p-3 flex items-center justify-between flex-wrap gap-2">
                <div className="flex items-center gap-2 text-sm">
                    <TruckIcon size={16} className="text-[var(--color-text-muted)]" />
                    <span className="font-semibold text-[var(--color-text)]">{m.truck}</span>
                    <span className="text-[var(--color-text-muted)]">· {m.maintenance_date}</span>
                    <span className="text-[var(--color-text-muted)] font-mono">· {formatKm(m.kilometers_at_maintenance)}</span>
                </div>
                <StatusPill status={m.status} />
            </div>

            {showSign && canApprove && notApproved && (
                <div className="rounded-lg border border-[var(--color-primary)]/40 bg-[var(--color-primary)]/5 p-3 space-y-2">
                    <FormInput label="Nom pour la signature" value={signatureName} onChange={(e) => setSignatureName(e.target.value)} wrapperClass="mb-0" autoFocus />
                    {signatureName.trim() && <div className="text-center py-2 text-[26px] text-[var(--color-text)]" style={{ fontFamily: '"Dancing Script", cursive', lineHeight: 1.1 }}>{signatureName.trim()}</div>}
                    <div className="flex justify-end gap-2">
                        <Button variant="ghost" onClick={() => setShowSign(false)} disabled={signing}>Annuler</Button>
                        <Button onClick={submitSign} loading={signing} disabled={!signatureName.trim()} icon={<PenLine size={14} />}>Signer électroniquement</Button>
                    </div>
                </div>
            )}

            <div className="grid md:grid-cols-2 gap-4">
                <section>
                    <h3 className="text-xs font-semibold uppercase tracking-wide text-[var(--color-text-muted)] mb-1.5 border-l-2 border-amber-500 pl-2">Huile moteur</h3>
                    <ViewRow label="Type d'huile">{m.oil_type ? (refs.oilTypes[m.oil_type] ?? m.oil_type) : '—'}</ViewRow>
                    <ViewRow label="Quantité">{m.oil_quantity_liters != null ? `${Number(m.oil_quantity_liters).toLocaleString('fr-FR')} L` : '—'}</ViewRow>
                    <ViewRow label="Prochaine vidange à"><span className="text-red-600 font-semibold">{formatKm(m.next_oil_change_km)}</span></ViewRow>
                </section>
                <section>
                    <h3 className="text-xs font-semibold uppercase tracking-wide text-[var(--color-text-muted)] mb-1.5 border-l-2 border-red-500 pl-2">État des organes</h3>
                    <ViewRow label="Boîte de vitesse">{m.gearbox_status ?? '—'}</ViewRow>
                    <ViewRow label="Différentiel">{m.differential_status ?? '—'}</ViewRow>
                    <ViewRow label="Hydraulique">{m.hydraulic_status ?? '—'}</ViewRow>
                    <ViewRow label="Graissage">{m.greasing_status ?? '—'}</ViewRow>
                    <ViewRow label="Freins">{m.brake_status ?? '—'}</ViewRow>
                    <ViewRow label="Refroidissement">{m.coolant_status ?? '—'}</ViewRow>
                    <ViewRow label="Batterie">{m.battery_status ?? '—'}</ViewRow>
                </section>
            </div>

            <section>
                <h3 className="text-xs font-semibold uppercase tracking-wide text-[var(--color-text-muted)] mb-1.5 border-l-2 border-blue-500 pl-2">Filtres changés</h3>
                <div className="grid grid-cols-2 md:grid-cols-4 gap-2">
                    {filters.map(([label, on]) => (
                        <div key={label} className={`px-3 py-2 rounded-lg text-sm flex items-center justify-between ${on ? 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200' : 'bg-[var(--color-surface-hover)] text-[var(--color-text-muted)]'}`}>
                            <span>{label}</span><span className="font-bold">{on ? '✓' : '—'}</span>
                        </div>
                    ))}
                </div>
            </section>

            {items.length > 0 && (
                <section>
                    <h3 className="text-xs font-semibold uppercase tracking-wide text-[var(--color-text-muted)] mb-1.5 border-l-2 border-emerald-500 pl-2">Facture — pièces & main d'œuvre</h3>
                    <div className="overflow-x-auto rounded-lg border border-[var(--color-border)]">
                        <table className="w-full text-sm">
                            <thead><tr className="bg-[var(--color-surface-hover)] text-[11px] uppercase tracking-wide text-[var(--color-text-secondary)]">
                                <th className="px-3 py-2 text-left font-semibold">Désignation</th><th className="px-3 py-2 text-left font-semibold">Réf.</th>
                                <th className="px-3 py-2 text-right font-semibold">Qté</th><th className="px-3 py-2 text-right font-semibold">Prix U.</th><th className="px-3 py-2 text-right font-semibold">Total</th>
                            </tr></thead>
                            <tbody className="divide-y divide-[var(--color-border)]">
                                {items.map((it, i) => (
                                    <tr key={i}>
                                        <td className="px-3 py-2">{it.designation}<span className="block text-xs text-[var(--color-text-muted)]">{refs.itemCategories[it.category] ?? it.category}</span></td>
                                        <td className="px-3 py-2 text-[var(--color-text-muted)]">{it.reference || '—'}</td>
                                        <td className="px-3 py-2 text-right font-mono">{Number(it.quantity).toLocaleString('fr-FR')} {refs.itemUnits[it.unit] ?? ''}</td>
                                        <td className="px-3 py-2 text-right font-mono">{Math.round(it.unit_price).toLocaleString('fr-FR')}</td>
                                        <td className="px-3 py-2 text-right font-mono font-semibold">{Math.round(it.line_total).toLocaleString('fr-FR')}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            )}

            {m.attachment_url && (
                <a href={m.attachment_url} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-1 text-sm text-[var(--color-primary)] hover:underline"><FileText size={14} /> {m.attachment_filename ?? 'Ouvrir la facture'}</a>
            )}

            {checkedEntries.length > 0 && (
                <section>
                    <h3 className="text-xs font-semibold uppercase tracking-wide text-[var(--color-text-muted)] mb-1.5 border-l-2 border-blue-500 pl-2">Fiche de contrôle après travaux</h3>
                    <div className="rounded-lg border border-[var(--color-border)] divide-y divide-[var(--color-border)]">
                        {checkedEntries.map(([key, label]) => (
                            <div key={key} className="flex items-center justify-between gap-3 px-3 py-1.5 text-sm">
                                <span className="text-[var(--color-text)]">{label}</span>
                                {checks[key] === 'bon' ? <span className="font-semibold text-emerald-600">Bon</span> : checks[key] === 'mauvais' ? <span className="font-semibold text-red-600">Mauvais</span> : <span className="font-semibold text-gray-500">N/A</span>}
                            </div>
                        ))}
                    </div>
                </section>
            )}

            {m.notes && (
                <section>
                    <h3 className="text-xs font-semibold uppercase tracking-wide text-[var(--color-text-muted)] mb-1.5 border-l-2 border-gray-400 pl-2">Notes</h3>
                    <div className="rounded-lg bg-[var(--color-surface-hover)] border border-[var(--color-border)] p-3 whitespace-pre-wrap text-sm text-[var(--color-text)]">{m.notes}</div>
                </section>
            )}

            {m.dashboard_photo_url && (
                <section>
                    <h3 className="text-xs font-semibold uppercase tracking-wide text-[var(--color-text-muted)] mb-1.5 border-l-2 border-gray-400 pl-2">Photo du tableau de bord</h3>
                    <img src={m.dashboard_photo_url} alt="Tableau de bord" className="max-h-60 w-auto rounded-lg border border-[var(--color-border)]" />
                </section>
            )}

            {m.status === 'approved' && m.signed_by && (
                <section className="rounded-lg border border-[var(--color-border)] border-l-4 border-l-red-600 bg-amber-50 p-3">
                    <div className="text-xs font-semibold uppercase tracking-wide text-[var(--color-text-muted)]">Signée par</div>
                    <div className="mt-1 text-2xl text-[var(--color-text)] break-words" style={{ fontFamily: '"Dancing Script", cursive', lineHeight: 1.1 }}>{m.signed_by}</div>
                    {m.approved_at && <div className="text-xs text-[var(--color-text-muted)] mt-2">Le {m.approved_at}</div>}
                </section>
            )}
        </Drawer>
    );
}
