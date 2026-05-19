import { Head, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import FormInput from '@/components/ui/FormInput';
import FormSelect from '@/components/ui/FormSelect';
import FormTextarea from '@/components/ui/FormTextarea';
import { ArrowLeft } from 'lucide-react';

interface NameRef { id: number; name: string; code?: string }

interface DemandData {
    id: number;
    week_start_date: string;
    project_id: number | null;
    provider_id: number | null;
    client_name: string | null;
    required_tons: number;
    required_trucks: number | null;
    product: string | null;
    priority: number;
    notes: string | null;
}

interface Props {
    demand?: DemandData;
    projects: NameRef[];
    providers: NameRef[];
    products: string[];
    priorities: Record<number, string>;
    defaultWeekStart: string;
}

export default function DemandsCreate({ demand, projects, providers, products, priorities, defaultWeekStart }: Props) {
    const isEdit = !!demand;

    const form = useForm({
        week_start_date: demand?.week_start_date ?? defaultWeekStart,
        project_id: (demand?.project_id ?? '') as string | number,
        provider_id: (demand?.provider_id ?? '') as string | number,
        client_name: demand?.client_name ?? '',
        required_tons: String(demand?.required_tons ?? ''),
        required_trucks: demand?.required_trucks != null ? String(demand.required_trucks) : '',
        product: demand?.product ?? '',
        priority: String(demand?.priority ?? 3),
        notes: demand?.notes ?? '',
        change_note: '',
    });

    const originalTons = demand?.required_tons != null ? String(demand.required_tons) : '';
    const originalTrucks = demand?.required_trucks != null ? String(demand.required_trucks) : '';
    const objectiveChanged = !isEdit
        || form.data.required_tons !== originalTons
        || form.data.required_trucks !== originalTrucks;

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isEdit && demand) {
            form.put(`/logistics/demands/${demand.id}`);
        } else {
            form.post('/logistics/demands');
        }
    };

    return (
        <AuthenticatedLayout title={isEdit ? 'Modifier la demande' : 'Nouvelle demande'}>
            <Head title={isEdit ? 'Modifier la demande' : 'Nouvelle demande'} />

            <div className="mb-4">
                <Button variant="ghost" icon={<ArrowLeft size={16} />} onClick={() => window.history.back()}>Retour</Button>
            </div>

            <Card>
                <form onSubmit={submit} className="max-w-2xl space-y-1">
                    <FormInput
                        label="Semaine (date du lundi)"
                        name="week_start_date"
                        type="date"
                        value={form.data.week_start_date}
                        onChange={(e) => form.setData('week_start_date', e.target.value)}
                        error={form.errors.week_start_date}
                        required
                    />

                    <FormSelect
                        label="Projet / Chantier"
                        options={projects.map((p) => ({ value: p.id, label: p.name }))}
                        value={form.data.project_id || null}
                        onChange={(v) => form.setData('project_id', v ?? '')}
                        error={form.errors.project_id}
                        placeholder="Sélectionner (optionnel)"
                    />

                    <FormInput
                        label="Nom du client (si pas de projet)"
                        name="client_name"
                        value={form.data.client_name}
                        onChange={(e) => form.setData('client_name', e.target.value)}
                        error={form.errors.client_name}
                    />

                    <FormSelect
                        label="Carrière source"
                        options={providers.map((p) => ({ value: p.id, label: p.name }))}
                        value={form.data.provider_id || null}
                        onChange={(v) => form.setData('provider_id', v ?? '')}
                        error={form.errors.provider_id}
                        placeholder="Laisser libre (optimiseur choisit)"
                    />

                    <FormSelect
                        label="Produit"
                        options={products.map((p) => ({ value: p, label: p }))}
                        value={form.data.product || null}
                        onChange={(v) => form.setData('product', (v as string) ?? '')}
                        error={form.errors.product}
                        placeholder="Tous produits"
                    />

                    <div className="grid grid-cols-2 gap-3">
                        <FormInput
                            label="Tonnage requis (t)"
                            name="required_tons"
                            type="number"
                            step="0.01"
                            value={form.data.required_tons}
                            onChange={(e) => form.setData('required_tons', e.target.value)}
                            error={form.errors.required_tons}
                            required
                        />
                        <FormInput
                            label="Nb camions imposé (optionnel)"
                            name="required_trucks"
                            type="number"
                            value={form.data.required_trucks}
                            onChange={(e) => form.setData('required_trucks', e.target.value)}
                            error={form.errors.required_trucks}
                        />
                    </div>

                    <FormSelect
                        label="Priorité"
                        options={Object.entries(priorities).map(([v, label]) => ({ value: Number(v), label: `${v} — ${label}` }))}
                        value={Number(form.data.priority)}
                        onChange={(v) => form.setData('priority', String(v ?? 3))}
                        error={form.errors.priority}
                        required
                    />

                    <FormTextarea
                        label="Notes"
                        name="notes"
                        value={form.data.notes}
                        onChange={(e) => form.setData('notes', e.target.value)}
                        error={form.errors.notes}
                    />

                    {objectiveChanged && (
                        <div className="border-t border-[var(--color-border)] mt-4 pt-4">
                            <p className="text-xs uppercase tracking-wider font-semibold text-[var(--color-text-muted)] mb-2">
                                Justification de l'objectif client
                            </p>
                            <textarea
                                className="w-full px-3 py-2 text-sm rounded border border-[var(--color-border)] bg-[var(--color-surface)] text-[var(--color-text)]"
                                rows={3}
                                placeholder="Ex : email du client du 17/05/2026 — relance échéance pont SN-12."
                                value={form.data.change_note}
                                onChange={(e) => form.setData('change_note', e.target.value)}
                                required
                            />
                            {form.errors.change_note && (
                                <p className="text-xs text-red-500 mt-1">{form.errors.change_note}</p>
                            )}
                            <p className="text-xs text-[var(--color-text-muted)] mt-1">
                                Cette note est archivée comme preuve d'audit (tonnage / camions demandés).
                            </p>
                        </div>
                    )}

                    <div className="flex gap-2 pt-4">
                        <Button variant="secondary" onClick={() => window.history.back()}>Annuler</Button>
                        <Button type="submit" loading={form.processing}>{isEdit ? 'Mettre à jour' : 'Créer'}</Button>
                    </div>
                </form>
            </Card>
        </AuthenticatedLayout>
    );
}
