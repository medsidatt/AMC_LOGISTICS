import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import Drawer from '@/components/ui/Drawer';
import Button from '@/components/ui/Button';
import FormInput from '@/components/ui/FormInput';
import DetailPanel, { DetailItem } from '@/components/ui/DetailPanel';
import { ShieldCheck, FileText, PenLine, Pencil, CheckCircle2, Clock, AlertTriangle } from 'lucide-react';
import { apiFetch } from '@/utils/csrf';
import type { InspectionDetail, InspectionOptions } from '../types';

interface Props {
    inspectionId: number;
    canEdit: boolean;
    onEdit: (inspection: InspectionDetail) => void;
    onClose: () => void;
}

interface ShowPayload {
    inspection: InspectionDetail;
    options: InspectionOptions;
    canSign: boolean;
    currentUserName: string;
}

const CONDITION_STYLE: Record<string, string> = {
    ok: 'text-emerald-600',
    needs_attention: 'text-amber-600',
    critical: 'text-red-600',
    na: 'text-gray-400',
};
const SEVERITY_STYLE: Record<string, string> = {
    minor: 'bg-[var(--color-surface-hover)] text-[var(--color-text-secondary)] ring-[var(--color-border)]',
    major: 'bg-amber-100 text-amber-800 ring-amber-200',
    critical: 'bg-red-100 text-red-800 ring-red-200',
};

/** Inspection details (read) + in-drawer Sign + Edit/PDF. Fetches the detail on open. */
export default function InspectionDetailsDrawer({ inspectionId, canEdit, onEdit, onClose }: Props) {
    const [data, setData] = useState<ShowPayload | null>(null);
    const [loading, setLoading] = useState(true);
    const [showSign, setShowSign] = useState(false);
    const [signing, setSigning] = useState(false);
    const [signatureName, setSignatureName] = useState('');

    useEffect(() => {
        let alive = true;
        apiFetch(`/hse/inspections/${inspectionId}`)
            .then((r) => (r.ok ? r.json() : null))
            .then((j: ShowPayload | null) => {
                if (!alive) return;
                setData(j);
                if (j) setSignatureName(j.currentUserName ?? '');
                setLoading(false);
            })
            .catch(() => { if (alive) setLoading(false); });
        return () => { alive = false; };
    }, [inspectionId]);

    const submitSign = () => {
        if (!signatureName.trim()) return;
        setSigning(true);
        router.post(`/hse/inspections/${inspectionId}/sign`, { signature_name: signatureName.trim() }, { preserveScroll: true, onSuccess: onClose, onFinish: () => setSigning(false) });
    };

    if (loading || !data) {
        return (
            <Drawer open onClose={onClose} size="lg" icon={<ShieldCheck size={18} className="text-[var(--color-primary)]" />} title="Inspection">
                <p className="text-sm text-[var(--color-text-muted)]">Chargement…</p>
            </Drawer>
        );
    }

    const { inspection: i, options, canSign } = data;
    const isValidated = i.status === 'validated';

    return (
        <Drawer
            open
            onClose={onClose}
            size="lg"
            icon={<ShieldCheck size={18} className="text-[var(--color-primary)]" />}
            title={`Inspection N° ${i.id} — ${i.truck?.matricule ?? ''}`}
            footer={
                <>
                    <a href={`/hse/inspections/${i.id}/pdf`} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-1 text-xs px-3 py-2 rounded-lg border border-[var(--color-border)] hover:bg-[var(--color-surface-hover)] text-[var(--color-text-secondary)]"><FileText size={14} /> PDF</a>
                    {canEdit && !isValidated && <Button variant="secondary" icon={<Pencil size={15} />} onClick={() => onEdit(i)}>Modifier</Button>}
                    {canSign && !isValidated && <Button icon={<PenLine size={15} />} onClick={() => setShowSign((s) => !s)}>Signer</Button>}
                </>
            }
        >
            <div className="rounded-lg bg-[var(--color-surface-hover)] border border-[var(--color-border)] p-3 flex items-center justify-between flex-wrap gap-2">
                <div className="flex items-center gap-2 text-sm">
                    <ShieldCheck size={16} className="text-[var(--color-text-muted)]" />
                    <span className="font-semibold text-[var(--color-text)]">{i.truck?.matricule ?? '—'}</span>
                    <span className="text-[var(--color-text-muted)]">· {i.inspection_date}</span>
                    <span className="text-[var(--color-text-muted)]">· {options.categories[i.category] ?? i.category}</span>
                </div>
                {isValidated
                    ? <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold ring-1 bg-emerald-100 text-emerald-800 ring-emerald-200"><CheckCircle2 size={12} /> Signée</span>
                    : <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold ring-1 bg-amber-100 text-amber-800 ring-amber-200"><Clock size={12} /> En attente</span>}
            </div>

            {showSign && canSign && !isValidated && (
                <div className="rounded-lg border border-[var(--color-primary)]/40 bg-[var(--color-primary)]/5 p-3 space-y-2">
                    <FormInput label="Nom pour la signature" value={signatureName} onChange={(e) => setSignatureName(e.target.value)} wrapperClass="mb-0" autoFocus />
                    {signatureName.trim() && <div className="text-center py-2 text-[26px] text-[var(--color-text)]" style={{ fontFamily: '"Dancing Script", cursive', lineHeight: 1.1 }}>{signatureName.trim()}</div>}
                    <div className="flex justify-end gap-2">
                        <Button variant="ghost" onClick={() => setShowSign(false)} disabled={signing}>Annuler</Button>
                        <Button onClick={submitSign} loading={signing} disabled={!signatureName.trim()} icon={<PenLine size={14} />}>Signer électroniquement</Button>
                    </div>
                </div>
            )}

            <DetailPanel columns={3}>
                <DetailItem label="Camion" value={i.truck?.matricule} />
                <DetailItem label="Conducteur" value={i.driver?.name} />
                <DetailItem label="Projet" value={i.project?.name} />
                <DetailItem label="Activité" value={i.activity} />
                <DetailItem label="Inspecteur" value={i.inspector} />
                <DetailItem label="Date" value={i.inspection_date} />
            </DetailPanel>

            {i.vehicle_photo_url && (
                <section>
                    <h3 className="text-xs font-semibold uppercase tracking-wide text-[var(--color-text-muted)] mb-1.5 border-l-2 border-gray-400 pl-2">Photo du véhicule</h3>
                    <a href={i.vehicle_photo_url} target="_blank" rel="noopener noreferrer">
                        <img src={i.vehicle_photo_url} alt="Véhicule" className="max-h-60 w-auto rounded-lg border border-[var(--color-border)] hover:opacity-90" />
                    </a>
                </section>
            )}

            {Object.entries(options.sections).map(([sectionKey, section]) => (
                <section key={sectionKey}>
                    <h3 className="text-xs font-semibold uppercase tracking-wide text-[var(--color-text-muted)] mb-1.5 border-l-2 border-[var(--color-primary)] pl-2">{section.label}</h3>
                    <div className="rounded-lg border border-[var(--color-border)] divide-y divide-[var(--color-border)]">
                        {Object.entries(section.fields).map(([field, label]) => {
                            const value = i[field] as string | undefined;
                            const remark = i.field_remarks?.[field];
                            return (
                                <div key={field} className="px-3 py-1.5 text-sm">
                                    <div className="flex items-center justify-between gap-3">
                                        <span className="text-[var(--color-text)]">{label}</span>
                                        <span className={`font-semibold ${CONDITION_STYLE[value ?? 'na'] ?? ''}`}>{options.conditions[value ?? 'na'] ?? value ?? '—'}</span>
                                    </div>
                                    {remark && <p className="mt-0.5 text-xs italic text-[var(--color-text-muted)]">{remark}</p>}
                                </div>
                            );
                        })}
                    </div>
                </section>
            ))}

            {i.issues.length > 0 && (
                <section>
                    <h3 className="text-xs font-semibold uppercase tracking-wide text-[var(--color-text-muted)] mb-1.5 border-l-2 border-amber-500 pl-2 flex items-center gap-1"><AlertTriangle size={13} /> Anomalies signalées</h3>
                    <div className="space-y-1.5">
                        {i.issues.map((issue) => (
                            <div key={issue.id} className="rounded-lg border border-[var(--color-border)] p-2.5 text-sm">
                                <div className="flex items-center gap-2">
                                    <span className="font-medium text-[var(--color-text)]">{issue.category}</span>
                                    <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold ring-1 ${SEVERITY_STYLE[issue.severity] ?? SEVERITY_STYLE.minor}`}>{issue.severity}</span>
                                    {issue.resolved_at && <span className="ml-auto text-xs text-emerald-600">Résolue le {issue.resolved_at}</span>}
                                </div>
                                {issue.issue_notes && <p className="mt-1 text-xs text-[var(--color-text-muted)]">{issue.issue_notes}</p>}
                                {issue.resolution_notes && <p className="mt-1 text-xs text-emerald-700">Résolution : {issue.resolution_notes}</p>}
                            </div>
                        ))}
                    </div>
                </section>
            )}

            {(i.findings_summary || i.recommendations) && (
                <section className="grid md:grid-cols-2 gap-3">
                    {i.findings_summary && (
                        <div>
                            <h3 className="text-xs font-semibold uppercase tracking-wide text-[var(--color-text-muted)] mb-1.5 border-l-2 border-gray-400 pl-2">Constatations</h3>
                            <div className="rounded-lg bg-[var(--color-surface-hover)] border border-[var(--color-border)] p-3 whitespace-pre-wrap text-sm text-[var(--color-text)]">{i.findings_summary}</div>
                        </div>
                    )}
                    {i.recommendations && (
                        <div>
                            <h3 className="text-xs font-semibold uppercase tracking-wide text-[var(--color-text-muted)] mb-1.5 border-l-2 border-gray-400 pl-2">Recommandations</h3>
                            <div className="rounded-lg bg-[var(--color-surface-hover)] border border-[var(--color-border)] p-3 whitespace-pre-wrap text-sm text-[var(--color-text)]">{i.recommendations}</div>
                        </div>
                    )}
                </section>
            )}

            {i.attachment_url && (
                <a href={i.attachment_url} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-1 text-sm text-[var(--color-primary)] hover:underline"><FileText size={14} /> {i.attachment_filename ?? 'Ouvrir la pièce jointe'}</a>
            )}

            {isValidated && i.validator && (
                <section className="rounded-lg border border-[var(--color-border)] border-l-4 border-l-red-600 bg-amber-50 p-3">
                    <div className="text-xs font-semibold uppercase tracking-wide text-[var(--color-text-muted)]">Signée par le Responsable Logistique</div>
                    <div className="mt-1 text-2xl text-[var(--color-text)] break-words" style={{ fontFamily: '"Dancing Script", cursive', lineHeight: 1.1 }}>{i.validator}</div>
                    {i.validated_at && <div className="text-xs text-[var(--color-text-muted)] mt-2">Le {i.validated_at}</div>}
                </section>
            )}
        </Drawer>
    );
}
