import { Head, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import FormInput from '@/components/ui/FormInput';
import FormSelect from '@/components/ui/FormSelect';
import FormTextarea from '@/components/ui/FormTextarea';
import { ShieldCheck, Paperclip, Camera } from 'lucide-react';
import CameraCapture from '@/components/inspection/CameraCapture';

interface Driver { id: number; name: string; }
interface Project { id: number; name: string; code?: string | null; }

interface Inspection {
    id: number;
    inspection_date: string;
    category: string;
    findings_summary: string | null;
    recommendations: string | null;
    status: string;
    attachment_url?: string | null;
    attachment_filename?: string | null;
    vehicle_photo_url?: string | null;
    vehicle_photo_filename?: string | null;
    driver_id?: number | null;
    project_id?: number | null;
    activity?: string | null;
    client_name?: string | null;
    field_remarks?: Record<string, string> | null;
    [key: string]: any;
}

interface Section {
    label: string;
    fields: Record<string, string>;
}

interface Props {
    inspection: Inspection;
    trucks: { id: number; matricule: string }[];
    drivers: Driver[];
    projects: Project[];
    options: {
        categories: Record<string, string>;
        conditions: Record<string, string>;
        fields: string[];
        sections: Record<string, Section>;
    };
}

export default function InspectionEdit({ inspection, drivers, projects, options }: Props) {
    const initial: Record<string, any> = {
        inspection_date: inspection.inspection_date,
        category: inspection.category,
        findings_summary: inspection.findings_summary ?? '',
        recommendations: inspection.recommendations ?? '',
        driver_id: inspection.driver_id != null ? String(inspection.driver_id) : '',
        project_id: inspection.project_id != null ? String(inspection.project_id) : '',
        activity: inspection.activity ?? 'Livraison de Basalte',
        client_name: inspection.client_name ?? 'AMC Travaux SN',
        attachment: null as File | null,
        vehicle_photo: null as File | null,
        field_remarks: (inspection.field_remarks ?? {}) as Record<string, string>,
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
                        {/* Date d'inspection désactivée — conservée telle qu'enregistrée à la création.
                        <FormInput
                            label="Date d'inspection"
                            type="date"
                            value={form.data.inspection_date}
                            onChange={(e) => form.setData('inspection_date', e.target.value)}
                        />
                        */}
                        {/* Catégorie désactivée — une seule catégorie utilisée pour l'instant.
                        <FormSelect
                            label="Catégorie"
                            value={form.data.category}
                            onChange={(v) => form.setData('category', v)}
                            options={Object.entries(options.categories).map(([k, l]) => ({ value: k, label: l }))}
                        />
                        */}
                        <FormSelect
                            label="Conducteur"
                            value={String(form.data.driver_id ?? '')}
                            onChange={(v) => form.setData('driver_id', v)}
                            options={[{ value: '', label: '—' }, ...drivers.map((d) => ({ value: String(d.id), label: d.name }))]}
                        />
                        <FormSelect
                            label="Projet / Chantier"
                            value={String(form.data.project_id ?? '')}
                            onChange={(v) => form.setData('project_id', v)}
                            options={[{ value: '', label: '—' }, ...projects.map((p) => ({ value: String(p.id), label: p.name }))]}
                        />
                        <FormInput
                            label="Activité"
                            value={form.data.activity}
                            onChange={(e) => form.setData('activity', e.target.value)}
                        />
                        {/* Client désactivé — l'inspection est interne (AMC Travaux SN).
                        <FormInput
                            label="Client"
                            value={form.data.client_name}
                            onChange={(e) => form.setData('client_name', e.target.value)}
                        />
                        */}
                    </div>
                </Card>

                <Card>
                    <h2 className="text-lg font-semibold mb-3 flex items-center gap-2">
                        <Camera size={18} /> Photo du véhicule (capture en direct)
                    </h2>
                    <CameraCapture
                        onCapture={(file) => form.setData('vehicle_photo', file)}
                        existingPhotoUrl={inspection.vehicle_photo_url ?? null}
                        existingPhotoFilename={inspection.vehicle_photo_filename ?? null}
                        error={form.errors.vehicle_photo as any}
                    />
                </Card>

                {Object.entries(options.sections).map(([sectionKey, section]) => (
                    <Card key={sectionKey}>
                        <h2 className="text-lg font-semibold mb-3">{section.label}</h2>
                        <div className="space-y-3">
                            {Object.entries(section.fields).map(([field, label]) => (
                                <div key={field} className="grid grid-cols-1 md:grid-cols-2 gap-3 items-end">
                                    <FormSelect
                                        label={label}
                                        value={form.data[field] as string}
                                        onChange={(v) => form.setData(field, v)}
                                        options={Object.entries(options.conditions).map(([k, l]) => ({ value: k, label: l }))}
                                    />
                                    <FormInput
                                        label="Remarque"
                                        placeholder="Ex : fuite côté droit, marque de gouttière…"
                                        value={(form.data.field_remarks as Record<string, string>)[field] ?? ''}
                                        onChange={(e) => form.setData('field_remarks', { ...(form.data.field_remarks as Record<string, string>), [field]: e.target.value })}
                                    />
                                </div>
                            ))}
                        </div>
                    </Card>
                ))}

                <Card>
                    <FormTextarea label="Constatations" value={form.data.findings_summary} onChange={(e) => form.setData('findings_summary', e.target.value)} rows={3} />
                    <FormTextarea label="Recommandations" value={form.data.recommendations} onChange={(e) => form.setData('recommendations', e.target.value)} rows={3} />
                </Card>

                {/* Upload de fiche scannée désactivé — le PDF généré + photo caméra remplacent le scan papier.
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
                */}

                <div className="flex gap-3 justify-end">
                    <Button type="submit" disabled={form.processing}>Enregistrer</Button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
