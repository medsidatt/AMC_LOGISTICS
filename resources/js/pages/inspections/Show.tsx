import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import Modal from '@/components/ui/Modal';
import FormInput from '@/components/ui/FormInput';
import { ShieldCheck, Edit2, FileDown, Camera, ClipboardList, FileText, PenLine, CheckCircle2, Clock } from 'lucide-react';

interface Inspection {
    id: number;
    truck: { id: number; matricule: string } | null;
    driver?: { id: number; name: string } | null;
    project?: { id: number; name: string; code?: string | null } | null;
    activity?: string | null;
    inspector: string | null;
    inspection_date: string;
    status: string;
    findings_summary: string | null;
    recommendations: string | null;
    vehicle_photo_url?: string | null;
    vehicle_photo_filename?: string | null;
    field_remarks?: Record<string, string> | null;
    validator?: string | null;
    validated_at?: string | null;
    electronic_signature_name?: string | null;
    [key: string]: any;
}

interface Section {
    label: string;
    fields: Record<string, string>;
}

interface Props {
    inspection: Inspection;
    options: {
        categories: Record<string, string>;
        conditions: Record<string, string>;
        fields: string[];
        sections: Record<string, Section>;
    };
    canSign: boolean;
    currentUserName: string;
}

export default function InspectionShow({ inspection, options, canSign, currentUserName }: Props) {
    const { auth } = usePage().props as any;
    const can = (perm: string) => Array.isArray(auth?.permissions) && auth.permissions.includes(perm);
    const isValidated = inspection.status === 'validated';
    const editable = !isValidated && can('inspection-edit');

    const [signOpen, setSignOpen] = useState(false);
    const [signatureName, setSignatureName] = useState('');
    const [submitting, setSubmitting] = useState(false);

    const openSign = () => {
        setSignatureName(currentUserName);
        setSignOpen(true);
    };
    const closeSign = () => { setSignOpen(false); setSignatureName(''); };
    const submitSign = () => {
        if (!signatureName.trim()) return;
        setSubmitting(true);
        router.post(`/hse/inspections/${inspection.id}/sign`, { signature_name: signatureName.trim() }, {
            preserveScroll: true,
            onFinish: () => { setSubmitting(false); closeSign(); },
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title={`Inspection #${inspection.id}`} />
            <div className="space-y-4 max-w-4xl">
                <div className="flex items-center justify-between flex-wrap gap-2">
                    <div className="flex items-center gap-2">
                        <ShieldCheck size={22} className="text-emerald-500" />
                        <h1 className="text-xl font-semibold">Inspection #{inspection.id}</h1>
                        <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold ring-1 ${
                            isValidated
                                ? 'bg-emerald-100 text-emerald-800 ring-emerald-200'
                                : 'bg-amber-100 text-amber-800 ring-amber-200'
                        }`}>
                            {isValidated ? <CheckCircle2 size={11} /> : <Clock size={11} />}
                            {isValidated ? 'Signée' : 'En attente'}
                        </span>
                    </div>
                    <div className="flex gap-2 flex-wrap">
                        <a href={`/hse/inspections/${inspection.id}/pdf`}>
                            <Button variant="secondary"><FileDown size={14} className="mr-1" /> Télécharger PDF</Button>
                        </a>
                        {editable && (
                            <Link href={`/logistics/inspections/${inspection.id}/edit`}>
                                <Button variant="secondary"><Edit2 size={14} className="mr-1" /> Modifier</Button>
                            </Link>
                        )}
                        {canSign && !isValidated && (
                            <Button variant="primary" onClick={openSign}>
                                <PenLine size={14} className="mr-1" /> Signer
                            </Button>
                        )}
                    </div>
                </div>

                {/* Informations générales */}
                <Card>
                    <h2 className="text-base font-semibold mb-3 flex items-center gap-2">
                        <ClipboardList size={16} className="text-emerald-500" /> Informations générales
                    </h2>
                    <div className="grid grid-cols-2 md:grid-cols-3 gap-3 text-sm">
                        <div><div className="text-[var(--color-text-muted)]">Camion</div><div className="font-medium">{inspection.truck?.matricule ?? '—'}</div></div>
                        <div><div className="text-[var(--color-text-muted)]">Conducteur</div><div className="font-medium">{inspection.driver?.name ?? '—'}</div></div>
                        <div><div className="text-[var(--color-text-muted)]">Projet</div><div className="font-medium">{inspection.project?.name ?? '—'}</div></div>
                        <div><div className="text-[var(--color-text-muted)]">Activité</div><div className="font-medium">{inspection.activity ?? '—'}</div></div>
                        <div><div className="text-[var(--color-text-muted)]">Inspecteur</div><div className="font-medium">{inspection.inspector ?? '—'}</div></div>
                        <div><div className="text-[var(--color-text-muted)]">Date</div><div className="font-medium">{inspection.inspection_date}</div></div>
                    </div>
                </Card>

                {/* Photo véhicule */}
                {inspection.vehicle_photo_url && (
                    <Card>
                        <h2 className="text-base font-semibold mb-3 flex items-center gap-2">
                            <Camera size={16} className="text-emerald-500" /> Photo du véhicule
                        </h2>
                        <a href={inspection.vehicle_photo_url} target="_blank" rel="noopener noreferrer" title="Cliquer pour ouvrir en grand">
                            <img
                                src={inspection.vehicle_photo_url}
                                alt="Véhicule"
                                className="max-h-96 w-auto rounded border border-[var(--color-border)] cursor-zoom-in hover:opacity-90 transition"
                            />
                        </a>
                        <p className="text-xs text-[var(--color-text-muted)] mt-2">Cliquer sur l'image pour l'ouvrir en taille réelle.</p>
                    </Card>
                )}

                {/* Points de contrôle par section */}
                {Object.entries(options.sections).map(([sectionKey, section]) => (
                    <Card key={sectionKey}>
                        <h2 className="text-base font-semibold mb-3">{section.label}</h2>
                        <div className="space-y-1 text-sm">
                            {Object.entries(section.fields).map(([field, label]) => {
                                const remark = inspection.field_remarks?.[field];
                                return (
                                    <div key={field} className="border-b border-[var(--color-border)] py-1.5 last:border-0">
                                        <div className="flex justify-between items-start gap-2">
                                            <span>{label}</span>
                                            <span className="font-medium whitespace-nowrap">{options.conditions[inspection[field]] ?? '—'}</span>
                                        </div>
                                        {remark && (
                                            <div className="text-xs text-[var(--color-text-muted)] italic mt-0.5 pl-2 border-l-2 border-[var(--color-border)]">
                                                Remarque : {remark}
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    </Card>
                ))}

                {/* Notes globales */}
                {(inspection.findings_summary || inspection.recommendations) && (
                    <Card>
                        <h2 className="text-base font-semibold mb-3 flex items-center gap-2">
                            <FileText size={16} className="text-emerald-500" /> Notes
                        </h2>
                        {inspection.findings_summary && (
                            <div className="mb-3">
                                <div className="text-sm font-semibold mb-1">Constatations</div>
                                <div className="text-sm whitespace-pre-wrap">{inspection.findings_summary}</div>
                            </div>
                        )}
                        {inspection.recommendations && (
                            <div>
                                <div className="text-sm font-semibold mb-1">Recommandations</div>
                                <div className="text-sm whitespace-pre-wrap">{inspection.recommendations}</div>
                            </div>
                        )}
                    </Card>
                )}

                {/* Signature électronique */}
                {isValidated && (
                    <Card className="border-l-4 border-l-red-600 bg-amber-50">
                        <div className="text-xs font-semibold uppercase tracking-wide text-[var(--color-text-muted)]">Signée par le Responsable Logistique</div>
                        <div className="mt-1 text-2xl sm:text-3xl text-[var(--color-text)] break-words" style={{ fontFamily: '"Dancing Script", cursive', lineHeight: 1.1 }}>
                            {inspection.electronic_signature_name ?? inspection.validator ?? '—'}
                        </div>
                        {inspection.validated_at && (
                            <div className="text-xs text-[var(--color-text-muted)] mt-2">Le {inspection.validated_at}</div>
                        )}
                    </Card>
                )}
            </div>

            <Modal open={signOpen} onClose={closeSign} title="Signer l'inspection" size="md">
                <div className="space-y-4">
                    <div className="rounded-lg bg-[var(--color-surface-hover)] border border-[var(--color-border)] p-3 text-sm">
                        <div className="flex items-center gap-2 mb-1">
                            <ShieldCheck size={14} className="text-emerald-500" />
                            <span className="font-semibold text-[var(--color-text)]">Inspection N° {inspection.id}</span>
                            <span className="text-[var(--color-text-muted)]">— {inspection.truck?.matricule ?? '—'}</span>
                        </div>
                        <div className="text-xs text-[var(--color-text-muted)]">Date : {inspection.inspection_date} · Inspecteur : {inspection.inspector ?? '—'}</div>
                    </div>
                    <FormInput
                        label="Nom préféré pour la signature"
                        value={signatureName}
                        onChange={(e) => setSignatureName(e.target.value)}
                        autoFocus
                        required
                    />
                    <p className="text-xs text-[var(--color-text-muted)] -mt-2">
                        Ce nom apparaîtra en signature manuscrite sur le PDF de l'inspection.
                        Par défaut, votre nom de compte est proposé — modifiez-le si nécessaire.
                    </p>
                    {signatureName.trim() && (
                        <div className="text-center py-3 border border-dashed border-[var(--color-border)] rounded-lg bg-[var(--color-surface)]">
                            <span className="text-[24px] sm:text-[32px] break-words text-[var(--color-text)]" style={{ fontFamily: '"Dancing Script", cursive', lineHeight: 1.1 }}>
                                {signatureName.trim()}
                            </span>
                            <p className="text-xs text-[var(--color-text-muted)] mt-2">Aperçu de la signature</p>
                        </div>
                    )}
                    <div className="flex items-center justify-end gap-2 pt-2">
                        <Button variant="ghost" onClick={closeSign} disabled={submitting}>Annuler</Button>
                        <Button variant="primary" onClick={submitSign} loading={submitting} disabled={!signatureName.trim()} icon={<PenLine size={14} />}>
                            Signer électroniquement
                        </Button>
                    </div>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}
