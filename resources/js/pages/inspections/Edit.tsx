import { Head, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import FormInput from '@/components/ui/FormInput';
import FormSelect from '@/components/ui/FormSelect';
import FormTextarea from '@/components/ui/FormTextarea';
import { ShieldCheck, Paperclip } from 'lucide-react';

interface Inspection {
    id: number;
    inspection_date: string;
    category: string;
    findings_summary: string | null;
    recommendations: string | null;
    status: string;
    attachment_url?: string | null;
    attachment_filename?: string | null;
    [key: string]: any;
}

interface Section {
    label: string;
    fields: Record<string, string>;
}

interface Props {
    inspection: Inspection;
    trucks: { id: number; matricule: string }[];
    options: {
        categories: Record<string, string>;
        conditions: Record<string, string>;
        fields: string[];
        sections: Record<string, Section>;
    };
}

export default function InspectionEdit({ inspection, options }: Props) {
    const initial: Record<string, any> = {
        inspection_date: inspection.inspection_date,
        category: inspection.category,
        findings_summary: inspection.findings_summary ?? '',
        recommendations: inspection.recommendations ?? '',
        attachment: null as File | null,
        _method: 'put',
    };
    options.fields.forEach((f) => { initial[f] = inspection[f] ?? 'ok'; });
    const form = useForm(initial);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(`/logistics/inspections/${inspection.id}`, { forceFormData: true });
    };

    return (
        <AuthenticatedLayout>
            <Head title={`Inspection #${inspection.id} — édition`} />
            <form onSubmit={submit} className="space-y-4 max-w-4xl">
                <div className="flex items-center gap-2">
                    <ShieldCheck size={22} className="text-emerald-500" />
                    <h1 className="text-xl font-semibold">Inspection #{inspection.id}</h1>
                </div>

                <Card>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <FormInput
                            label="Date d'inspection"
                            type="date"
                            value={form.data.inspection_date}
                            onChange={(e) => form.setData('inspection_date', e.target.value)}
                        />
                        <FormSelect
                            label="Catégorie"
                            value={form.data.category}
                            onChange={(v) => form.setData('category', v)}
                            options={Object.entries(options.categories).map(([k, l]) => ({ value: k, label: l }))}
                        />
                    </div>
                </Card>

                {Object.entries(options.sections).map(([sectionKey, section]) => (
                    <Card key={sectionKey}>
                        <h2 className="text-lg font-semibold mb-3">{section.label}</h2>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                            {Object.entries(section.fields).map(([field, label]) => (
                                <FormSelect
                                    key={field}
                                    label={label}
                                    value={form.data[field] as string}
                                    onChange={(v) => form.setData(field, v)}
                                    options={Object.entries(options.conditions).map(([k, l]) => ({ value: k, label: l }))}
                                />
                            ))}
                        </div>
                    </Card>
                ))}

                <Card>
                    <FormTextarea label="Constatations" value={form.data.findings_summary} onChange={(e) => form.setData('findings_summary', e.target.value)} rows={3} />
                    <FormTextarea label="Recommandations" value={form.data.recommendations} onChange={(e) => form.setData('recommendations', e.target.value)} rows={3} />
                </Card>

                <Card>
                    <h2 className="text-lg font-semibold mb-3 flex items-center gap-2">
                        <Paperclip size={18} /> Fiche d'inspection scannée
                    </h2>
                    {inspection.attachment_url && (
                        <p className="text-sm mb-2">
                            Fiche actuelle: <a href={inspection.attachment_url} target="_blank" rel="noopener noreferrer" className="text-[var(--color-primary)] hover:underline">{inspection.attachment_filename ?? 'Ouvrir'}</a>
                        </p>
                    )}
                    <input
                        type="file"
                        accept="application/pdf,image/jpeg,image/png"
                        onChange={(e) => form.setData('attachment', e.target.files?.[0] ?? null)}
                        className="block w-full text-sm"
                    />
                    {form.errors.attachment && (
                        <p className="text-xs text-red-500 mt-1">{form.errors.attachment as any}</p>
                    )}
                    <p className="text-xs text-[var(--color-text-muted)] mt-1">Téléverser une nouvelle fiche remplace l'existante. PDF / JPG / PNG, max 10 Mo.</p>
                </Card>

                <div className="flex gap-3 justify-end">
                    <Button type="submit" disabled={form.processing}>Enregistrer</Button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
