import { useEffect, useState } from 'react';
import { useForm } from '@inertiajs/react';
import Drawer from '@/components/ui/Drawer';
import FormActions from '@/components/ui/FormActions';
import FormInput from '@/components/ui/FormInput';
import FormSelect from '@/components/ui/FormSelect';
import DocumentManager, { type TrackingDocument } from './DocumentManager';
import { apiFetch } from '@/utils/csrf';
import { Package } from 'lucide-react';

export interface TransportFormRefs {
    transporters: { id: number; name: string }[];
    trucks: { id: number; matricule: string; last_driver_id: number | null; transporter_id: number | null }[];
    drivers: { id: number; name: string }[];
    providers: { id: number; name: string }[];
    products: { id: string; name: string }[];
    bases: { id: string; name: string }[];
}

export interface TransportEditRecord {
    id: number;
    reference: string;
    truck_id: number;
    driver_id: number;
    transporter_id: number | null;
    provider_id: number | null;
    product: string;
    base: string;
    provider_date: string | null;
    client_date: string | null;
    commune_date: string | null;
    commune_weight: number | null;
    provider_gross_weight: number | null;
    provider_tare_weight: number | null;
    provider_net_weight: number | null;
    client_gross_weight: number | null;
    client_tare_weight: number | null;
    client_net_weight: number | null;
    documents: TrackingDocument[];
}

export interface TransportPrefill {
    truck_id?: string | number;
    provider_id?: string | number;
    provider_date?: string;
}

interface Props {
    mode: 'create' | 'edit';
    refs: TransportFormRefs;
    record?: TransportEditRecord | null;
    /** Create-mode prefill (e.g. from the Réconciliation "Créer ticket" deep-link). */
    prefill?: TransportPrefill | null;
    onClose: () => void;
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
    return (
        <div>
            <h4 className="text-xs font-semibold uppercase tracking-wide text-[var(--color-text-muted)] mb-2 border-l-2 border-[var(--color-primary)] pl-2">{title}</h4>
            {children}
        </div>
    );
}

/**
 * Create / edit a transport ticket inside the workspace — no page navigation.
 * Reuses the existing store/update endpoints (validation unchanged); documents
 * staged here are saved with the form, existing docs deleted via the JSON endpoint.
 */
export default function TransportFormDrawer({ mode, refs, record, prefill, onClose }: Props) {
    const form = useForm({
        truck_id: (record?.truck_id ?? prefill?.truck_id ?? '') as string | number,
        driver_id: (record?.driver_id ?? '') as string | number,
        transporter_id: (record?.transporter_id ?? '') as string | number,
        provider_id: (record?.provider_id ?? prefill?.provider_id ?? '') as string | number,
        product: record?.product ?? '',
        base: record?.base ?? '',
        provider_date: record?.provider_date ?? prefill?.provider_date ?? '',
        client_date: record?.client_date ?? '',
        commune_date: record?.commune_date ?? '',
        provider_gross_weight: String(record?.provider_gross_weight ?? ''),
        provider_tare_weight: String(record?.provider_tare_weight ?? ''),
        provider_net_weight: String(record?.provider_net_weight ?? ''),
        client_gross_weight: String(record?.client_gross_weight ?? ''),
        client_tare_weight: String(record?.client_tare_weight ?? ''),
        client_net_weight: String(record?.client_net_weight ?? ''),
        commune_weight: String(record?.commune_weight ?? ''),
        files: [] as File[],
    });

    const [existingDocs, setExistingDocs] = useState<TrackingDocument[]>(record?.documents ?? []);
    const [deletingId, setDeletingId] = useState<number | null>(null);

    // Auto-select last driver & transporter when the truck changes (create only,
    // or when editing and switching to a different truck).
    useEffect(() => {
        const selected = refs.trucks.find((t) => t.id === Number(form.data.truck_id));
        if (!selected) return;
        if (mode === 'create' || Number(form.data.truck_id) !== record?.truck_id) {
            if (selected.last_driver_id) form.setData('driver_id', selected.last_driver_id);
            if (selected.transporter_id) form.setData('transporter_id', selected.transporter_id);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [form.data.truck_id]);

    const addFiles = (files: FileList) => form.setData('files', [...form.data.files, ...Array.from(files)]);
    const removeNew = (i: number) => form.setData('files', form.data.files.filter((_, idx) => idx !== i));

    const deleteExisting = async (docId: number) => {
        if (!record || !confirm('Supprimer ce document ?')) return;
        setDeletingId(docId);
        try {
            const res = await apiFetch(`/transport_tracking/${record.id}/document/${docId}`, { method: 'DELETE' });
            if (res.ok) setExistingDocs((d) => d.filter((x) => x.id !== docId));
        } finally {
            setDeletingId(null);
        }
    };

    const submit = () => {
        if (mode === 'create') {
            form.post('/transport_tracking/store', { forceFormData: true, preserveScroll: true, onSuccess: onClose });
        } else if (record) {
            form.transform((d) => ({ ...d, _method: 'PUT' }));
            form.post(`/transport_tracking/${record.id}/update`, { forceFormData: true, preserveScroll: true, onSuccess: onClose });
        }
    };

    const truckOpts = refs.trucks.map((t) => ({ value: t.id, label: t.matricule }));
    const opts = (items: { id: number | string; name: string }[]) => items.map((i) => ({ value: i.id, label: i.name }));
    const canSubmit = String(form.data.truck_id) !== '' && String(form.data.driver_id) !== '' && form.data.product !== '' && form.data.base !== '';

    return (
        <Drawer
            open
            onClose={onClose}
            size="lg"
            icon={<Package size={18} className="text-[var(--color-primary)]" />}
            title={mode === 'create' ? 'Nouveau transport' : `Modifier ${record?.reference ?? ''}`}
            footer={
                <FormActions
                    onCancel={onClose}
                    onSubmit={submit}
                    submitLabel={mode === 'create' ? 'Créer' : 'Enregistrer'}
                    loading={form.processing}
                    disabled={!canSubmit}
                />
            }
        >
            <Section title="Véhicule & Conducteur">
                <FormSelect label="Camion" options={truckOpts} value={form.data.truck_id} onChange={(v) => form.setData('truck_id', v ?? '')} error={form.errors.truck_id} required />
                <FormSelect label="Conducteur" options={opts(refs.drivers)} value={form.data.driver_id} onChange={(v) => form.setData('driver_id', v ?? '')} error={form.errors.driver_id} required />
                <FormSelect label="Transporteur" options={opts(refs.transporters)} value={form.data.transporter_id} onChange={(v) => form.setData('transporter_id', v ?? '')} error={form.errors.transporter_id} />
            </Section>

            <Section title="Produit & Localisation">
                <FormSelect label="Produit" options={opts(refs.products)} value={form.data.product} onChange={(v) => form.setData('product', String(v ?? ''))} error={form.errors.product} required />
                <FormSelect label="Base" options={opts(refs.bases)} value={form.data.base} onChange={(v) => form.setData('base', String(v ?? ''))} error={form.errors.base} required />
                <FormSelect label="Fournisseur" options={opts(refs.providers)} value={form.data.provider_id} onChange={(v) => form.setData('provider_id', v ?? '')} error={form.errors.provider_id} />
            </Section>

            <Section title="Dates">
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <FormInput label="Fournisseur" type="date" value={form.data.provider_date} onChange={(e) => form.setData('provider_date', e.target.value)} error={form.errors.provider_date} wrapperClass="mb-0" />
                    <FormInput label="Client" type="date" value={form.data.client_date} onChange={(e) => form.setData('client_date', e.target.value)} error={form.errors.client_date} wrapperClass="mb-0" />
                    <FormInput label="Commune" type="date" value={form.data.commune_date} onChange={(e) => form.setData('commune_date', e.target.value)} error={form.errors.commune_date} wrapperClass="mb-0" />
                </div>
            </Section>

            <Section title="Poids Fournisseur">
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <FormInput label="Brut" type="number" step="0.01" value={form.data.provider_gross_weight} onChange={(e) => form.setData('provider_gross_weight', e.target.value)} error={form.errors.provider_gross_weight} wrapperClass="mb-0" />
                    <FormInput label="Tare" type="number" step="0.01" value={form.data.provider_tare_weight} onChange={(e) => form.setData('provider_tare_weight', e.target.value)} error={form.errors.provider_tare_weight} wrapperClass="mb-0" />
                    <FormInput label="Net" type="number" step="0.01" value={form.data.provider_net_weight} onChange={(e) => form.setData('provider_net_weight', e.target.value)} error={form.errors.provider_net_weight} wrapperClass="mb-0" />
                </div>
            </Section>

            <Section title="Poids Client">
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <FormInput label="Brut" type="number" step="0.01" value={form.data.client_gross_weight} onChange={(e) => form.setData('client_gross_weight', e.target.value)} error={form.errors.client_gross_weight} wrapperClass="mb-0" />
                    <FormInput label="Tare" type="number" step="0.01" value={form.data.client_tare_weight} onChange={(e) => form.setData('client_tare_weight', e.target.value)} error={form.errors.client_tare_weight} wrapperClass="mb-0" />
                    <FormInput label="Net" type="number" step="0.01" value={form.data.client_net_weight} onChange={(e) => form.setData('client_net_weight', e.target.value)} error={form.errors.client_net_weight} wrapperClass="mb-0" />
                </div>
            </Section>

            <Section title="Commune">
                <FormInput label="Poids commune" type="number" step="0.01" value={form.data.commune_weight} onChange={(e) => form.setData('commune_weight', e.target.value)} error={form.errors.commune_weight} />
            </Section>

            <Section title="Documents">
                <DocumentManager
                    existing={existingDocs}
                    onDeleteExisting={mode === 'edit' ? deleteExisting : undefined}
                    deletingId={deletingId}
                    newFiles={form.data.files}
                    onAddFiles={addFiles}
                    onRemoveNew={removeNew}
                    addLabel="Ajouter des fichiers"
                />
                {form.errors.files && <p className="mt-1 text-xs text-[var(--color-danger)]">{form.errors.files}</p>}
            </Section>
        </Drawer>
    );
}
