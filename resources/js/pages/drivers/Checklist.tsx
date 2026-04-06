import { Head, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import FormSelect from '@/components/ui/FormSelect';
import FormInput from '@/components/ui/FormInput';
import Badge from '@/components/ui/Badge';
import { ClipboardCheck, CheckCircle2 } from 'lucide-react';

const conditionOptions = [
    { value: 'bon', label: 'Bon' },
    { value: 'moyen', label: 'Moyen' },
    { value: 'mauvais', label: 'Mauvais' },
];

const fuelOptions = [
    { value: 'plein', label: 'Plein' },
    { value: 'demi', label: 'Demi' },
    { value: 'quart', label: 'Quart' },
    { value: 'vide', label: 'Vide' },
];

interface ChecklistIssue {
    id: number;
    category: string;
    flagged: boolean;
    issue_notes: string | null;
}

interface ChecklistEntry {
    id: number;
    checklist_date: string;
    tire_condition: string;
    fuel_level: string;
    oil_level: string;
    brakes: string;
    lights: string;
    general_condition_notes: string;
    notes: string | null;
    issues: ChecklistIssue[];
}

interface Props {
    driver: { id: number; name: string };
    truck: { id: number; matricule: string };
    todayChecklist: ChecklistEntry | null;
    history: ChecklistEntry[];
}

export default function Checklist({ driver, truck, todayChecklist, history }: Props) {
    const form = useForm({
        tire_condition: 'bon',
        fuel_level: 'plein',
        oil_level: 'bon',
        brakes: 'bon',
        lights: 'bon',
        general_condition_notes: '',
        notes: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/drivers/checklist-submit');
    };

    return (
        <AuthenticatedLayout title="Checklist quotidien">
            <Head title="Checklist quotidien" />

            <div className="mb-4 flex flex-wrap items-center gap-4 text-sm text-[var(--color-text-secondary)]">
                <span>Conducteur : <strong className="text-[var(--color-text)]">{driver.name}</strong></span>
                <span>Camion : <strong className="text-[var(--color-text)]">{truck.matricule}</strong></span>
            </div>

            {todayChecklist ? (
                <Card>
                    <div className="flex items-center gap-2 mb-4">
                        <CheckCircle2 size={20} className="text-emerald-500" />
                        <h3 className="text-lg font-semibold text-[var(--color-text)]">Checklist d'aujourd'hui (soumis)</h3>
                    </div>
                    <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        {[
                            ['Pneus', todayChecklist.tire_condition],
                            ['Carburant', todayChecklist.fuel_level],
                            ['Huile', todayChecklist.oil_level],
                            ['Freins', todayChecklist.brakes],
                            ['Feux', todayChecklist.lights],
                            ['État général', todayChecklist.general_condition_notes || '-'],
                        ].map(([label, value]) => (
                            <div key={label as string}>
                                <p className="text-xs text-[var(--color-text-muted)] uppercase">{label}</p>
                                <p className="text-sm text-[var(--color-text)] mt-0.5">{value}</p>
                            </div>
                        ))}
                    </div>
                    {todayChecklist.notes && (
                        <div className="mt-4">
                            <p className="text-xs text-[var(--color-text-muted)] uppercase">Notes</p>
                            <p className="text-sm text-[var(--color-text)] mt-0.5">{todayChecklist.notes}</p>
                        </div>
                    )}
                    {todayChecklist.issues.length > 0 && (
                        <div className="mt-4">
                            <p className="text-xs text-[var(--color-text-muted)] uppercase mb-2">Problèmes signalés</p>
                            <div className="flex flex-wrap gap-2">
                                {todayChecklist.issues.filter(i => i.flagged).map((issue) => (
                                    <Badge key={issue.id} variant="danger">{issue.category}{issue.issue_notes ? ` - ${issue.issue_notes}` : ''}</Badge>
                                ))}
                            </div>
                        </div>
                    )}
                </Card>
            ) : (
                <Card>
                    <div className="flex items-center gap-2 mb-4">
                        <ClipboardCheck size={20} className="text-[var(--color-primary)]" />
                        <h3 className="text-lg font-semibold text-[var(--color-text)]">Checklist du jour</h3>
                    </div>
                    <form onSubmit={submit} className="max-w-lg space-y-1">
                        <FormSelect label="État des pneus" options={conditionOptions} value={form.data.tire_condition} onChange={(v) => form.setData('tire_condition', String(v ?? 'bon'))} required />
                        <FormSelect label="Niveau carburant" options={fuelOptions} value={form.data.fuel_level} onChange={(v) => form.setData('fuel_level', String(v ?? 'plein'))} required />
                        <FormSelect label="Niveau huile" options={conditionOptions} value={form.data.oil_level} onChange={(v) => form.setData('oil_level', String(v ?? 'bon'))} required />
                        <FormSelect label="Freins" options={conditionOptions} value={form.data.brakes} onChange={(v) => form.setData('brakes', String(v ?? 'bon'))} required />
                        <FormSelect label="Feux" options={conditionOptions} value={form.data.lights} onChange={(v) => form.setData('lights', String(v ?? 'bon'))} required />
                        <FormInput label="État général" name="general_condition_notes" value={form.data.general_condition_notes} onChange={(e) => form.setData('general_condition_notes', e.target.value)} />
                        <FormInput label="Notes supplémentaires" name="notes" value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
                        <div className="pt-4">
                            <Button type="submit" loading={form.processing}>Soumettre</Button>
                        </div>
                    </form>
                </Card>
            )}

            {history.length > 0 && (
                <Card className="mt-6">
                    <h3 className="text-lg font-semibold text-[var(--color-text)] mb-4">Historique</h3>
                    <div className="space-y-3">
                        {history.map((entry) => (
                            <div key={entry.id} className="rounded-lg border border-[var(--color-border)] p-3">
                                <div className="flex items-center justify-between mb-2">
                                    <span className="text-sm font-medium text-[var(--color-text)]">{entry.checklist_date}</span>
                                    {entry.issues.filter(i => i.flagged).length > 0 && (
                                        <Badge variant="danger">{entry.issues.filter(i => i.flagged).length} problème(s)</Badge>
                                    )}
                                </div>
                                <div className="flex flex-wrap gap-3 text-xs text-[var(--color-text-secondary)]">
                                    <span>Pneus: {entry.tire_condition}</span>
                                    <span>Carburant: {entry.fuel_level}</span>
                                    <span>Huile: {entry.oil_level}</span>
                                    <span>Freins: {entry.brakes}</span>
                                    <span>Feux: {entry.lights}</span>
                                </div>
                            </div>
                        ))}
                    </div>
                </Card>
            )}
        </AuthenticatedLayout>
    );
}
