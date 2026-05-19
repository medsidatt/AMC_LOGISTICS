import { Head, useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import FormInput from '@/components/ui/FormInput';
import FormSelect from '@/components/ui/FormSelect';
import FormTextarea from '@/components/ui/FormTextarea';
import { ShieldCheck, Camera, ClipboardList, FileText } from 'lucide-react';
import CameraCapture from '@/components/inspection/CameraCapture';

interface Truck { id: number; matricule: string; }
interface Driver { id: number; name: string; }
interface Project { id: number; name: string; code?: string | null; }

interface Section {
    label: string;
    fields: Record<string, string>;
}

interface Props {
    trucks: Truck[];
    drivers: Driver[];
    projects: Project[];
    defaultProjectId: number | null;
    truckDrivers: Record<string, number[]>;
    options: {
        categories: Record<string, string>;
        conditions: Record<string, string>;
        fields: string[];
        sections: Record<string, Section>;
    };
}

export default function InspectionCreate({ trucks, drivers, projects, defaultProjectId, truckDrivers, options }: Props) {
    const initial: Record<string, any> = {
        truck_id: '',
        driver_id: '',
        project_id: defaultProjectId != null ? String(defaultProjectId) : '',
        activity: 'Livraison de Basalte',
        client_name: 'AMC Travaux SN',
        inspection_date: new Date().toISOString().split('T')[0],
        category: 'comprehensive',
        findings_summary: '',
        recommendations: '',
        vehicle_photo: null as File | null,
        field_remarks: {} as Record<string, string>,
    };
    options.fields.forEach((f) => { initial[f] = 'ok'; });

    const form = useForm(initial);

    const visibleDrivers = useMemo(() => {
        const tid = String(form.data.truck_id ?? '');
        if (!tid) return drivers;
        const allowed = truckDrivers[tid];
        if (!allowed || allowed.length === 0) return drivers;
        return drivers.filter((d) => allowed.includes(d.id));
    }, [form.data.truck_id, drivers, truckDrivers]);

    const onTruckChange = (truckId: string) => {
        const allowedDrivers = truckId ? (truckDrivers[truckId] ?? []) : [];
        const currentDriver = String(form.data.driver_id ?? '');
        const keepDriver = !currentDriver || allowedDrivers.length === 0 || allowedDrivers.includes(Number(currentDriver));
        form.setData({
            ...form.data,
            truck_id: truckId,
            driver_id: keepDriver ? currentDriver : '',
        });
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/logistics/inspections', { forceFormData: true });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Nouvelle inspection" />
            <form onSubmit={submit} className="space-y-4 max-w-4xl">
                <div className="flex items-center gap-2">
                    <ShieldCheck size={22} className="text-emerald-500" />
                    <h1 className="text-xl font-semibold">Nouvelle inspection</h1>
                </div>

                {/* Informations générales */}
                <Card>
                    <h2 className="text-base font-semibold mb-3 flex items-center gap-2">
                        <ClipboardList size={16} className="text-emerald-500" /> Informations générales
                    </h2>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <FormSelect
                            label="Camion"
                            value={String(form.data.truck_id)}
                            onChange={(v) => onTruckChange(String(v ?? ''))}
                            options={[{ value: '', label: '— sélectionner —' }, ...trucks.map((t) => ({ value: String(t.id), label: t.matricule }))]}
                            error={form.errors.truck_id as any}
                            required
                        />
                        <FormSelect
                            label="Conducteur"
                            value={String(form.data.driver_id ?? '')}
                            onChange={(v) => form.setData('driver_id', v)}
                            options={[{ value: '', label: '—' }, ...visibleDrivers.map((d) => ({ value: String(d.id), label: d.name }))]}
                            error={form.errors.driver_id as any}
                        />
                        <FormSelect
                            label="Projet / Chantier"
                            value={String(form.data.project_id ?? '')}
                            onChange={(v) => form.setData('project_id', v)}
                            options={[{ value: '', label: '—' }, ...projects.map((p) => ({ value: String(p.id), label: p.name }))]}
                            error={form.errors.project_id as any}
                        />
                        <FormInput
                            label="Activité"
                            value={form.data.activity}
                            onChange={(e) => form.setData('activity', e.target.value)}
                            error={form.errors.activity as any}
                        />
                    </div>
                </Card>

                {/* Photo véhicule */}
                <Card>
                    <h2 className="text-base font-semibold mb-3 flex items-center gap-2">
                        <Camera size={16} className="text-emerald-500" /> Photo du véhicule (capture en direct)
                    </h2>
                    <CameraCapture
                        onCapture={(file) => form.setData('vehicle_photo', file)}
                        error={form.errors.vehicle_photo as any}
                    />
                </Card>

                {/* Points de contrôle */}
                {Object.entries(options.sections).map(([sectionKey, section]) => (
                    <Card key={sectionKey}>
                        <h2 className="text-base font-semibold mb-3">{section.label}</h2>
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

                {/* Notes globales */}
                <Card>
                    <h2 className="text-base font-semibold mb-3 flex items-center gap-2">
                        <FileText size={16} className="text-emerald-500" /> Notes
                    </h2>
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
                    <Button type="submit" disabled={form.processing}>
                        Enregistrer
                    </Button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
