import { Head, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import FormInput from '@/components/ui/FormInput';
import FormSelect from '@/components/ui/FormSelect';
import FormTextarea from '@/components/ui/FormTextarea';
import { ShieldCheck } from 'lucide-react';

interface Truck { id: number; matricule: string; }

interface Section {
    label: string;
    fields: Record<string, string>;
}

interface Props {
    trucks: Truck[];
    options: {
        categories: Record<string, string>;
        conditions: Record<string, string>;
        fields: string[];
        sections: Record<string, Section>;
    };
}

export default function InspectionCreate({ trucks, options }: Props) {
    const initial: Record<string, any> = {
        truck_id: '',
        inspection_date: new Date().toISOString().split('T')[0],
        category: 'comprehensive',
        findings_summary: '',
        recommendations: '',
        submit: false,
        issue_flags: [] as string[],
        issue_notes: {} as Record<string, string>,
        issue_severity: {} as Record<string, string>,
    };
    options.fields.forEach((f) => { initial[f] = 'ok'; });

    const form = useForm(initial);

    const toggleIssue = (cat: string) => {
        const flags: string[] = form.data.issue_flags as string[];
        const next = flags.includes(cat) ? flags.filter((f) => f !== cat) : [...flags, cat];
        form.setData('issue_flags', next);
    };

    const submit = (asSubmit: boolean) => (e: React.FormEvent) => {
        e.preventDefault();
        form.setData('submit', asSubmit);
        form.post('/hse/inspections');
    };

    return (
        <AuthenticatedLayout>
            <Head title="Nouvelle inspection HSE" />
            <form onSubmit={submit(false)} className="space-y-4 max-w-4xl">
                <div className="flex items-center gap-2">
                    <ShieldCheck size={22} className="text-emerald-500" />
                    <h1 className="text-xl font-semibold">Nouvelle inspection HSE</h1>
                </div>

                <Card>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <FormSelect
                            label="Camion"
                            value={String(form.data.truck_id)}
                            onChange={(v) => form.setData('truck_id', v)}
                            options={[{ value: '', label: '— sélectionner —' }, ...trucks.map((t) => ({ value: String(t.id), label: t.matricule }))]}
                            error={form.errors.truck_id as any}
                        />
                        <FormInput
                            label="Date d'inspection"
                            type="date"
                            value={form.data.inspection_date}
                            onChange={(e) => form.setData('inspection_date', e.target.value)}
                            error={form.errors.inspection_date as any}
                        />
                        <FormSelect
                            label="Catégorie"
                            value={form.data.category}
                            onChange={(v) => form.setData('category', v)}
                            options={Object.entries(options.categories).map(([k, l]) => ({ value: k, label: l }))}
                            error={form.errors.category as any}
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
                    <h2 className="text-lg font-semibold mb-3">Issues détectées</h2>
                    <div className="space-y-2">
                        {Object.entries(options.sections).flatMap(([_, section]) => Object.entries(section.fields)).map(([field, label]) => {
                            const flagged = (form.data.issue_flags as string[]).includes(field);
                            return (
                                <div key={field} className="flex items-start gap-3 p-2 rounded-lg border border-[var(--color-border)]">
                                    <label className="flex items-center gap-2 min-w-[260px]">
                                        <input
                                            type="checkbox"
                                            checked={flagged}
                                            onChange={() => toggleIssue(field)}
                                        />
                                        <span className="text-sm">{label}</span>
                                    </label>
                                    {flagged && (
                                        <>
                                            <select
                                                value={(form.data.issue_severity as any)[field] ?? 'minor'}
                                                onChange={(e) => form.setData('issue_severity', { ...(form.data.issue_severity as any), [field]: e.target.value })}
                                                className="border rounded px-2 py-1 text-sm bg-[var(--color-surface)]"
                                            >
                                                <option value="minor">Mineure</option>
                                                <option value="major">Majeure</option>
                                                <option value="critical">Critique</option>
                                            </select>
                                            <input
                                                type="text"
                                                placeholder="Notes..."
                                                value={(form.data.issue_notes as any)[field] ?? ''}
                                                onChange={(e) => form.setData('issue_notes', { ...(form.data.issue_notes as any), [field]: e.target.value })}
                                                className="flex-1 border rounded px-2 py-1 text-sm bg-[var(--color-surface)]"
                                            />
                                        </>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                </Card>

                <Card>
                    <FormTextarea
                        label="Résumé des constatations"
                        value={form.data.findings_summary}
                        onChange={(e) => form.setData('findings_summary', e.target.value)}
                        rows={3}
                    />
                    <FormTextarea
                        label="Recommandations"
                        value={form.data.recommendations}
                        onChange={(e) => form.setData('recommendations', e.target.value)}
                        rows={3}
                    />
                </Card>

                <div className="flex gap-3 justify-end">
                    <Button type="submit" variant="secondary" disabled={form.processing}>
                        Enregistrer brouillon
                    </Button>
                    <Button type="button" onClick={submit(true) as any} disabled={form.processing}>
                        Soumettre pour validation
                    </Button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
