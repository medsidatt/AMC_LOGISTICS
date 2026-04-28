import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import { ShieldCheck, Edit2 } from 'lucide-react';

interface Issue {
    id: number;
    category: string;
    severity: string;
    flagged: boolean;
    issue_notes: string | null;
    resolution_notes: string | null;
    resolved_at: string | null;
}

interface Inspection {
    id: number;
    truck: { id: number; matricule: string } | null;
    inspector: string | null;
    inspection_date: string;
    category: string;
    status: string;
    findings_summary: string | null;
    recommendations: string | null;
    validator: string | null;
    validated_at: string | null;
    validation_notes: string | null;
    issues: Issue[];
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

const STATUS_VARIANT: Record<string, 'default' | 'success' | 'warning' | 'danger'> = {
    draft: 'default',
    submitted: 'warning',
    validated: 'success',
    rejected: 'danger',
};

const SEVERITY_VARIANT: Record<string, 'default' | 'warning' | 'danger'> = {
    minor: 'default',
    major: 'warning',
    critical: 'danger',
};

export default function InspectionShow({ inspection, options }: Props) {
    const editable = inspection.status !== 'validated';

    return (
        <AuthenticatedLayout>
            <Head title={`Inspection #${inspection.id}`} />
            <div className="space-y-4 max-w-4xl">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <ShieldCheck size={22} className="text-emerald-500" />
                        <h1 className="text-xl font-semibold">Inspection #{inspection.id}</h1>
                        <Badge variant={STATUS_VARIANT[inspection.status]}>{inspection.status}</Badge>
                    </div>
                    {editable && (
                        <Link href={`/hse/inspections/${inspection.id}/edit`}>
                            <Button variant="secondary"><Edit2 size={14} className="mr-1" /> Modifier</Button>
                        </Link>
                    )}
                </div>

                <Card>
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                        <div><div className="text-[var(--color-text-muted)]">Camion</div><div className="font-medium">{inspection.truck?.matricule ?? '—'}</div></div>
                        <div><div className="text-[var(--color-text-muted)]">Inspecteur</div><div className="font-medium">{inspection.inspector ?? '—'}</div></div>
                        <div><div className="text-[var(--color-text-muted)]">Date</div><div className="font-medium">{inspection.inspection_date}</div></div>
                        <div><div className="text-[var(--color-text-muted)]">Catégorie</div><div className="font-medium">{options.categories[inspection.category] ?? inspection.category}</div></div>
                    </div>
                </Card>

                {Object.entries(options.sections).map(([sectionKey, section]) => (
                    <Card key={sectionKey}>
                        <h2 className="text-lg font-semibold mb-3">{section.label}</h2>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
                            {Object.entries(section.fields).map(([field, label]) => (
                                <div key={field} className="flex justify-between border-b border-[var(--color-border)] py-1">
                                    <span>{label}</span>
                                    <span className="font-medium">{options.conditions[inspection[field]] ?? '—'}</span>
                                </div>
                            ))}
                        </div>
                    </Card>
                ))}

                {inspection.issues.length > 0 && (
                    <Card>
                        <h2 className="text-lg font-semibold mb-3">Issues</h2>
                        <ul className="space-y-2">
                            {inspection.issues.map((i) => (
                                <li key={i.id} className="flex items-start justify-between border-b border-[var(--color-border)] pb-2">
                                    <div>
                                        <div className="font-medium">{i.category}</div>
                                        <div className="text-xs text-[var(--color-text-muted)]">{i.issue_notes ?? '—'}</div>
                                        {i.resolved_at && (
                                            <div className="text-xs text-emerald-500 mt-1">
                                                Résolu {i.resolved_at}: {i.resolution_notes}
                                            </div>
                                        )}
                                    </div>
                                    <Badge variant={SEVERITY_VARIANT[i.severity] ?? 'default'}>{i.severity}</Badge>
                                </li>
                            ))}
                        </ul>
                    </Card>
                )}

                {(inspection.findings_summary || inspection.recommendations) && (
                    <Card>
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

                {inspection.validated_at && (
                    <Card>
                        <div className="text-sm">
                            <div className="font-semibold mb-1">Validation</div>
                            <div>Par {inspection.validator} le {inspection.validated_at}</div>
                            {inspection.validation_notes && <div className="mt-1 text-[var(--color-text-muted)]">{inspection.validation_notes}</div>}
                        </div>
                    </Card>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
