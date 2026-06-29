import { useEffect, useState } from 'react';
import { useForm } from '@inertiajs/react';
import Drawer from '@/components/ui/Drawer';
import FormActions from '@/components/ui/FormActions';
import FormInput from '@/components/ui/FormInput';
import FormSelect from '@/components/ui/FormSelect';
import FormTextarea from '@/components/ui/FormTextarea';
import SectionTitle from '@/components/ui/SectionTitle';
import CameraCapture from '@/components/inspection/CameraCapture';
import { ShieldCheck, Camera } from 'lucide-react';
import { apiFetch } from '@/utils/csrf';
import type { InspectionFormRefs } from '../types';

interface Props {
    mode: 'create' | 'edit';
    inspectionId?: number;
    onClose: () => void;
}

/**
 * Create / edit an inspection inside the workspace — replaces the legacy
 * Create and Edit full pages. Fetches its form refs (trucks/drivers/projects +
 * the data-driven checklist sections) on open, then posts to the existing
 * store/update endpoints (validation unchanged). Truck + category are immutable
 * once an inspection exists (compliance record).
 */
export default function InspectionFormDrawer({ mode, inspectionId, onClose }: Props) {
    const [refs, setRefs] = useState<InspectionFormRefs | null>(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        let alive = true;
        const url = mode === 'create' ? '/logistics/inspections/create' : `/logistics/inspections/${inspectionId}/edit`;
        apiFetch(url)
            .then((r) => (r.ok ? r.json() : null))
            .then((j) => { if (alive) { setRefs(j); setLoading(false); } })
            .catch(() => { if (alive) setLoading(false); });
        return () => { alive = false; };
    }, [mode, inspectionId]);

    if (loading || !refs) {
        return (
            <Drawer open onClose={onClose} size="lg" icon={<ShieldCheck size={18} className="text-[var(--color-primary)]" />} title={mode === 'create' ? 'Nouvelle inspection' : 'Modifier l\'inspection'}>
                <p className="text-sm text-[var(--color-text-muted)]">Chargement…</p>
            </Drawer>
        );
    }

    return <InspectionFormBody mode={mode} refs={refs} onClose={onClose} />;
}

function InspectionFormBody({ mode, refs, onClose }: { mode: 'create' | 'edit'; refs: InspectionFormRefs; onClose: () => void }) {
    const { trucks, drivers, projects, defaultProjectId, truckDrivers, options } = refs;
    const record = refs.inspection;

    const initial: Record<string, any> = mode === 'edit' && record
        ? {
            truck_id: record.truck ? String(record.truck.id) : '',
            driver_id: record.driver_id != null ? String(record.driver_id) : '',
            project_id: record.project_id != null ? String(record.project_id) : '',
            activity: record.activity ?? '',
            client_name: record.client_name ?? '',
            inspection_date: record.inspection_date ?? new Date().toISOString().split('T')[0],
            category: record.category ?? 'comprehensive',
            findings_summary: record.findings_summary ?? '',
            recommendations: record.recommendations ?? '',
            vehicle_photo: null as File | null,
            field_remarks: { ...(record.field_remarks ?? {}) } as Record<string, string>,
        }
        : {
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
    options.fields.forEach((f) => { initial[f] = mode === 'edit' && record ? (record[f] ?? 'ok') : 'ok'; });

    const form = useForm<Record<string, any>>(initial);

    const allowedDriverIds = truckDrivers[String(form.data.truck_id ?? '')] ?? [];
    const visibleDrivers = !form.data.truck_id || allowedDriverIds.length === 0
        ? drivers
        : drivers.filter((d) => allowedDriverIds.includes(d.id));

    const onTruckChange = (truckId: string) => {
        const allowed = truckId ? (truckDrivers[truckId] ?? []) : [];
        const current = String(form.data.driver_id ?? '');
        const keep = !current || allowed.length === 0 || allowed.includes(Number(current));
        form.setData({ ...form.data, truck_id: truckId, driver_id: keep ? current : '' });
    };

    const submit = () => {
        const opts = { forceFormData: true, preserveScroll: true, onSuccess: onClose };
        if (mode === 'create') {
            form.post('/logistics/inspections', opts);
        } else if (record) {
            form.transform((d) => ({ ...d, _method: 'put' }));
            form.post(`/logistics/inspections/${record.id}`, opts);
        }
    };

    return (
        <Drawer
            open
            onClose={onClose}
            size="lg"
            icon={<ShieldCheck size={18} className="text-[var(--color-primary)]" />}
            title={mode === 'create' ? 'Nouvelle inspection' : `Modifier — ${record?.truck?.matricule ?? ''}`}
            footer={<FormActions onCancel={onClose} onSubmit={submit} submitLabel={mode === 'create' ? 'Enregistrer l\'inspection' : 'Enregistrer les modifications'} loading={form.processing} disabled={mode === 'create' && !form.data.truck_id} />}
        >
            <SectionTitle>Informations générales</SectionTitle>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                {mode === 'create' ? (
                    <FormSelect
                        label="Camion"
                        value={String(form.data.truck_id)}
                        onChange={(v) => onTruckChange(String(v ?? ''))}
                        options={[{ value: '', label: '— sélectionner —' }, ...trucks.map((t) => ({ value: String(t.id), label: t.matricule }))]}
                        error={form.errors.truck_id as string}
                        required
                    />
                ) : (
                    <FormInput label="Camion" value={record?.truck?.matricule ?? '—'} onChange={() => {}} disabled />
                )}
                <FormSelect
                    label="Conducteur"
                    value={String(form.data.driver_id ?? '')}
                    onChange={(v) => form.setData('driver_id', v)}
                    options={[{ value: '', label: '—' }, ...visibleDrivers.map((d) => ({ value: String(d.id), label: d.name }))]}
                    error={form.errors.driver_id as string}
                />
                <FormSelect
                    label="Projet / Chantier"
                    value={String(form.data.project_id ?? '')}
                    onChange={(v) => form.setData('project_id', v)}
                    options={[{ value: '', label: '—' }, ...projects.map((p) => ({ value: String(p.id), label: p.name }))]}
                    error={form.errors.project_id as string}
                />
                <FormInput label="Activité" value={form.data.activity ?? ''} onChange={(e) => form.setData('activity', e.target.value)} error={form.errors.activity as string} />
            </div>

            <SectionTitle>Photo du véhicule</SectionTitle>
            <div>
                <label className="block text-xs font-medium text-[var(--color-text-secondary)] mb-1.5 flex items-center gap-1"><Camera size={14} /> Capture en direct</label>
                <CameraCapture onCapture={(file) => form.setData('vehicle_photo', file)} existingPhotoUrl={record?.vehicle_photo_url ?? null} error={form.errors.vehicle_photo as string} />
            </div>

            {Object.entries(options.sections).map(([sectionKey, section]) => (
                <div key={sectionKey}>
                    <SectionTitle>{section.label}</SectionTitle>
                    <div className="space-y-3">
                        {Object.entries(section.fields).map(([field, label]) => (
                            <div key={field} className="grid grid-cols-1 md:grid-cols-2 gap-3 items-end">
                                <FormSelect
                                    label={label}
                                    wrapperClass="mb-0"
                                    value={String(form.data[field] ?? 'ok')}
                                    onChange={(v) => form.setData(field, v)}
                                    options={Object.entries(options.conditions).map(([k, l]) => ({ value: k, label: l }))}
                                />
                                <FormInput
                                    label="Remarque"
                                    wrapperClass="mb-0"
                                    placeholder="Ex : fuite côté droit, marque de gouttière…"
                                    value={(form.data.field_remarks as Record<string, string>)[field] ?? ''}
                                    onChange={(e) => form.setData('field_remarks', { ...(form.data.field_remarks as Record<string, string>), [field]: e.target.value })}
                                />
                            </div>
                        ))}
                    </div>
                </div>
            ))}

            <SectionTitle>Notes</SectionTitle>
            <FormTextarea label="Résumé des constatations" wrapperClass="mb-0" value={form.data.findings_summary ?? ''} onChange={(e) => form.setData('findings_summary', e.target.value)} error={form.errors.findings_summary as string} rows={3} />
            <FormTextarea label="Recommandations" wrapperClass="mb-0" value={form.data.recommendations ?? ''} onChange={(e) => form.setData('recommendations', e.target.value)} error={form.errors.recommendations as string} rows={3} />
        </Drawer>
    );
}
