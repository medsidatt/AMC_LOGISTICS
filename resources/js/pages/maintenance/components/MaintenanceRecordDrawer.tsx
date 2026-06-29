import { useForm } from '@inertiajs/react';
import Drawer from '@/components/ui/Drawer';
import FormActions from '@/components/ui/FormActions';
import Badge from '@/components/ui/Badge';
import FormInput from '@/components/ui/FormInput';
import FormTextarea from '@/components/ui/FormTextarea';
import SectionTitle from '@/components/ui/SectionTitle';
import MaintenanceItemsField, { type LineItem } from '@/components/maintenance/MaintenanceItemsField';
import ControlChecklist from '@/components/maintenance/ControlChecklist';
import ComponentStatusList from '@/components/maintenance/ComponentStatusList';
import CameraCapture from '@/components/inspection/CameraCapture';
import { Wrench, Camera } from 'lucide-react';
import type { BoardTruck, MaintenanceRecord, MaintenanceRefs } from '../types';

interface Props {
    mode: 'create' | 'edit';
    refs: MaintenanceRefs;
    truck?: BoardTruck | null;       // create
    record?: MaintenanceRecord | null; // edit
    onClose: () => void;
}

const SEVERITY_VARIANT: Record<string, 'default' | 'warning' | 'danger'> = { minor: 'default', major: 'warning', critical: 'danger' };

/**
 * Create / edit a maintenance record inside the workspace — replaces the legacy
 * Record page + History edit modal. Reuses the existing domain field components.
 * Posts to the existing record/update endpoints (validation unchanged).
 */
export default function MaintenanceRecordDrawer({ mode, refs, truck, record, onClose }: Props) {
    const general = truck?.profiles.find((p) => p.type === 'general') ?? truck?.profiles[0];
    const truckInterval = (mode === 'edit' ? record?.truck_interval_km : general?.interval_km) ?? null;
    const currentKm = mode === 'edit' ? (record?.kilometers_at_maintenance ?? 0) : (truck?.total_kilometers ?? 0);

    const computeNextOilKm = (oilType: string, baseKm: string | number): string => {
        const base = Number(baseKm);
        if (!Number.isFinite(base) || base <= 0) return '';
        const iv = truckInterval ?? refs.oilIntervals?.[oilType] ?? 9000;
        return String(Math.round(base + iv));
    };

    const form = useForm<Record<string, any>>(
        mode === 'edit' && record
            ? {
                maintenance_date: record.maintenance_date.split('/').reverse().join('-'),
                kilometers_at_maintenance: record.kilometers_at_maintenance != null ? String(record.kilometers_at_maintenance) : '',
                notes: record.notes ?? '',
                oil_type: record.oil_type ?? '',
                oil_change_km: record.kilometers_at_maintenance != null ? String(record.kilometers_at_maintenance) : '',
                next_oil_change_km: record.next_oil_change_km != null ? String(record.next_oil_change_km) : '',
                oil_quantity_liters: record.oil_quantity_liters != null ? String(record.oil_quantity_liters) : '',
                gearbox_status: record.gearbox_status ?? 'NORMAL',
                differential_status: record.differential_status ?? 'NORMAL',
                hydraulic_status: record.hydraulic_status ?? 'NORMAL',
                greasing_status: record.greasing_status ?? 'NORMAL',
                brake_status: record.brake_status ?? 'NORMAL',
                coolant_status: record.coolant_status ?? 'NORMAL',
                battery_status: record.battery_status ?? 'NORMAL',
                filter_oil_changed: !!record.filter_oil_changed,
                filter_hydraulic_changed: !!record.filter_hydraulic_changed,
                filter_air_changed: !!record.filter_air_changed,
                filter_fuel_changed: !!record.filter_fuel_changed,
                dashboard_photo: null as File | null,
                facture: null as File | null,
                items: (record.items ?? []).map((it): LineItem => ({
                    designation: it.designation, product_id: it.product_id ?? null, reference: it.reference ?? '', category: it.category ?? 'piece',
                    unit: it.unit ?? 'piece', quantity: it.quantity != null ? String(it.quantity) : '', unit_price: it.unit_price != null ? String(it.unit_price) : '',
                })),
                control_checks: { ...(record.control_checks ?? {}) },
            }
            : {
                maintenance_date: new Date().toISOString().split('T')[0],
                maintenance_type: 'general',
                notes: '',
                kilometers_at_maintenance: String(currentKm),
                oil_type: '',
                oil_change_km: String(currentKm),
                next_oil_change_km: currentKm > 0 ? String(Math.round(currentKm + (truckInterval ?? 9000))) : '',
                oil_quantity_liters: '',
                gearbox_status: 'NORMAL', differential_status: 'NORMAL', hydraulic_status: 'NORMAL', greasing_status: 'NORMAL',
                brake_status: 'NORMAL', coolant_status: 'NORMAL', battery_status: 'NORMAL',
                filter_oil_changed: false, filter_hydraulic_changed: false, filter_air_changed: false, filter_fuel_changed: false,
                dashboard_photo: null as File | null,
                linked_inspection_issue_ids: [] as number[],
                items: [] as LineItem[],
                facture: null as File | null,
                control_checks: Object.fromEntries(Object.keys(refs.controlChecks).map((k) => [k, 'bon'])) as Record<string, string>,
            },
    );

    const onKmChange = (val: string) => {
        form.setData((d: typeof form.data) => ({ ...d, kilometers_at_maintenance: val, oil_change_km: val, next_oil_change_km: computeNextOilKm(d.oil_type, val) }));
    };

    const toggleIssueLink = (issueId: number) => {
        const list = (form.data.linked_inspection_issue_ids as number[]) ?? [];
        form.setData('linked_inspection_issue_ids', list.includes(issueId) ? list.filter((id) => id !== issueId) : [...list, issueId]);
    };

    const submit = () => {
        const opts = { forceFormData: true, preserveScroll: true, onSuccess: onClose };
        if (mode === 'create' && truck) form.post(`/maintenance/${truck.id}/record`, opts);
        else if (mode === 'edit' && record) form.post(`/maintenance/${record.id}/update`, opts);
    };

    const issues = truck?.inspection_issues ?? [];

    return (
        <Drawer
            open
            onClose={onClose}
            size="lg"
            icon={<Wrench size={18} className="text-[var(--color-primary)]" />}
            title={mode === 'create' ? `Maintenance — ${truck?.matricule ?? ''}` : `Modifier — ${record?.truck ?? ''}`}
            footer={<FormActions onCancel={onClose} onSubmit={submit} submitLabel={mode === 'create' ? 'Enregistrer la maintenance' : 'Enregistrer les modifications'} loading={form.processing} disabled={!form.data.maintenance_date || form.data.kilometers_at_maintenance === ''} />}
        >
            <SectionTitle>Informations générales</SectionTitle>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <FormInput label="Date" type="date" wrapperClass="mb-0" value={form.data.maintenance_date} onChange={(e) => form.setData('maintenance_date', e.target.value)} error={form.errors.maintenance_date as string} required />
                <FormInput label="Distance actuelle (Km au compteur)" type="number" wrapperClass="mb-0" value={form.data.kilometers_at_maintenance} onChange={(e) => onKmChange(e.target.value)} error={form.errors.kilometers_at_maintenance as string} required />
            </div>
            {truckInterval != null && (
                <p className="text-xs text-[var(--color-text-muted)]">Intervalle camion : <b>{Number(truckInterval).toLocaleString('fr-FR')} km</b>. Prochaine vidange = distance actuelle + intervalle.</p>
            )}

            <div>
                <label className="block text-xs font-medium text-[var(--color-text-secondary)] mb-1.5 flex items-center gap-1"><Camera size={14} /> Photo du tableau de bord</label>
                <CameraCapture onCapture={(file) => form.setData('dashboard_photo', file)} existingPhotoUrl={record?.dashboard_photo_url ?? null} error={(form.errors as any)?.dashboard_photo} />
            </div>

            <SectionTitle>État des organes mécaniques</SectionTitle>
            <ComponentStatusList statuses={refs.componentStatuses} value={form.data} onChange={(k, v) => form.setData(k, v)} />

            <SectionTitle>Filtres changés</SectionTitle>
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 text-sm">
                <label className="flex items-center gap-2"><input type="checkbox" checked={!!form.data.filter_oil_changed} onChange={(e) => form.setData('filter_oil_changed', e.target.checked)} /> Huile</label>
                <label className="flex items-center gap-2"><input type="checkbox" checked={!!form.data.filter_hydraulic_changed} onChange={(e) => form.setData('filter_hydraulic_changed', e.target.checked)} /> Hydraulique</label>
                <label className="flex items-center gap-2"><input type="checkbox" checked={!!form.data.filter_air_changed} onChange={(e) => form.setData('filter_air_changed', e.target.checked)} /> Air</label>
                <label className="flex items-center gap-2"><input type="checkbox" checked={!!form.data.filter_fuel_changed} onChange={(e) => form.setData('filter_fuel_changed', e.target.checked)} /> Carburant</label>
            </div>

            <MaintenanceItemsField
                items={(form.data.items as LineItem[]) ?? []}
                onChange={(items) => form.setData('items', items)}
                categories={refs.itemCategories} units={refs.itemUnits}
                errors={form.errors as Record<string, string | undefined>}
                facture={form.data.facture as File | null} onFactureChange={(f) => form.setData('facture', f)}
                factureUrl={record?.attachment_url ?? null} factureName={record?.attachment_filename ?? null}
                factureError={form.errors.facture as string | undefined}
            />

            {mode === 'create' && issues.length > 0 && (
                <div>
                    <SectionTitle>Findings d'inspection résolus par cette maintenance</SectionTitle>
                    <div className="space-y-1 max-h-52 overflow-y-auto rounded-lg border border-[var(--color-border)] p-2 mt-1.5">
                        {issues.map((issue) => (
                            <label key={issue.id} className="flex items-start gap-2 text-sm py-1 border-b border-[var(--color-border)] last:border-0">
                                <input type="checkbox" checked={((form.data.linked_inspection_issue_ids as number[]) ?? []).includes(issue.id)} onChange={() => toggleIssueLink(issue.id)} className="mt-0.5" />
                                <span className="flex-1">
                                    <span className="font-medium">{issue.category}</span>{' '}
                                    <Badge variant={SEVERITY_VARIANT[issue.severity] ?? 'default'}>{issue.severity}</Badge>
                                    {issue.issue_notes && <span className="block text-xs text-[var(--color-text-muted)]">{issue.issue_notes}</span>}
                                    <span className="block text-xs text-[var(--color-text-muted)]">Inspection du {issue.inspection_date}</span>
                                </span>
                            </label>
                        ))}
                    </div>
                </div>
            )}

            <SectionTitle>Fiche de contrôle après travaux</SectionTitle>
            <ControlChecklist items={refs.controlChecks} value={(form.data.control_checks as Record<string, string>) ?? {}} onChange={(v) => form.setData('control_checks', v)} />

            <SectionTitle>Notes / Observations</SectionTitle>
            <FormTextarea wrapperClass="mb-0" value={form.data.notes ?? ''} onChange={(e) => form.setData('notes', e.target.value)} error={form.errors.notes as string} rows={3} />
        </Drawer>
    );
}
