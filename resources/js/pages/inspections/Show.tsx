import { Head, Link, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import { ShieldCheck, Edit2, FileDown, Camera, ClipboardList, FileText } from 'lucide-react';

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
}

export default function InspectionShow({ inspection, options }: Props) {
    const { auth } = usePage().props as any;
    const can = (perm: string) => Array.isArray(auth?.permissions) && auth.permissions.includes(perm);
    const editable = inspection.status !== 'validated' && can('inspection-edit');

    return (
        <AuthenticatedLayout>
            <Head title={`Inspection #${inspection.id}`} />
            <div className="space-y-4 max-w-4xl">
                <div className="flex items-center justify-between flex-wrap gap-2">
                    <div className="flex items-center gap-2">
                        <ShieldCheck size={22} className="text-emerald-500" />
                        <h1 className="text-xl font-semibold">Inspection #{inspection.id}</h1>
                    </div>
                    <div className="flex gap-2">
                        <a href={`/hse/inspections/${inspection.id}/pdf`}>
                            <Button variant="secondary"><FileDown size={14} className="mr-1" /> Télécharger PDF</Button>
                        </a>
                        {editable && (
                            <Link href={`/logistics/inspections/${inspection.id}/edit`}>
                                <Button variant="secondary"><Edit2 size={14} className="mr-1" /> Modifier</Button>
                            </Link>
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
            </div>
        </AuthenticatedLayout>
    );
}
