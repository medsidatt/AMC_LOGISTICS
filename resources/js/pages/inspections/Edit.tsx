import { Head, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import FormInput from '@/components/ui/FormInput';
import FormSelect from '@/components/ui/FormSelect';
import FormTextarea from '@/components/ui/FormTextarea';
import { ShieldCheck } from 'lucide-react';

interface Inspection {
    id: number;
    inspection_date: string;
    category: string;
    findings_summary: string | null;
    recommendations: string | null;
    status: string;
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
        submit: false,
    };
    options.fields.forEach((f) => { initial[f] = inspection[f] ?? 'ok'; });
    const form = useForm(initial);

    const submit = (asSubmit: boolean) => (e: React.FormEvent) => {
        e.preventDefault();
        form.setData('submit', asSubmit);
        form.put(`/hse/inspections/${inspection.id}`);
    };

    return (
        <AuthenticatedLayout>
            <Head title={`Inspection #${inspection.id} — édition`} />
            <form onSubmit={submit(false)} className="space-y-4 max-w-4xl">
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

                <div className="flex gap-3 justify-end">
                    <Button type="submit" variant="secondary" disabled={form.processing}>Enregistrer brouillon</Button>
                    {inspection.status === 'draft' && (
                        <Button type="button" onClick={submit(true) as any} disabled={form.processing}>Soumettre pour validation</Button>
                    )}
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
