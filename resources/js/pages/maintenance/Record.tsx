import { Head, useForm, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import FormInput from '@/components/ui/FormInput';
import FormTextarea from '@/components/ui/FormTextarea';
import MaintenanceItemsField, { LineItem } from '@/components/maintenance/MaintenanceItemsField';
import ControlChecklist from '@/components/maintenance/ControlChecklist';
import ComponentStatusList from '@/components/maintenance/ComponentStatusList';
import SectionTitle from '@/components/ui/SectionTitle';
import CameraCapture from '@/components/inspection/CameraCapture';
import { Camera } from 'lucide-react';

interface Profile {
    type: string;
    interval_km: number;
}

interface InspectionIssue {
    id: number;
    category: string;
    severity: string;
    issue_notes: string | null;
    inspection_date: string | null;
}

interface TruckRow {
    id: number;
    matricule: string;
    total_kilometers: number;
    profiles: Profile[];
    inspection_issues: InspectionIssue[];
}

interface Props {
    truck: TruckRow;
    oilTypes: Record<string, string>;
    oilIntervals: Record<string, number>;
    componentStatuses: Record<string, string>;
    itemCategories: Record<string, string>;
    itemUnits: Record<string, string>;
    controlChecks: Record<string, string>;
}

const SEVERITY_VARIANT: Record<string, 'default' | 'warning' | 'danger'> = {
    minor: 'default',
    major: 'warning',
    critical: 'danger',
};

export default function MaintenanceRecord({ truck, oilIntervals, componentStatuses, itemCategories, itemUnits, controlChecks }: Props) {
    const currentKm = truck.total_kilometers ?? 0;
    const general = truck.profiles.find((p) => p.type === 'general') ?? truck.profiles[0];
    const interval = general?.interval_km ?? 9000;

    const form = useForm<Record<string, any>>({
        maintenance_date: new Date().toISOString().split('T')[0],
        maintenance_type: 'general',
        notes: '',
        kilometers_at_maintenance: String(currentKm),
        oil_type: '',
        oil_change_km: String(currentKm),
        next_oil_change_km: currentKm > 0 ? String(Math.round(currentKm + interval)) : '',
        oil_quantity_liters: '',
        gearbox_status: 'NORMAL',
        differential_status: 'NORMAL',
        hydraulic_status: 'NORMAL',
        greasing_status: 'NORMAL',
        brake_status: 'NORMAL',
        coolant_status: 'NORMAL',
        battery_status: 'NORMAL',
        filter_oil_changed: false,
        filter_hydraulic_changed: false,
        filter_air_changed: false,
        filter_fuel_changed: false,
        dashboard_photo: null as File | null,
        linked_inspection_issue_ids: [] as number[],
        items: [] as LineItem[],
        facture: null as File | null,
        // Default every post-work control line to "Bon"; the operator flags exceptions.
        control_checks: Object.fromEntries(Object.keys(controlChecks).map((k) => [k, 'bon'])) as Record<string, string>,
    });

    const truckInterval = general?.interval_km ?? null;

    const computeNextOilKm = (oilType: string, baseKm: string | number): string => {
        const base = Number(baseKm);
        if (!Number.isFinite(base) || base <= 0) return '';
        const iv = truckInterval ?? oilIntervals?.[oilType] ?? 9000;
        return String(Math.round(base + iv));
    };

    const onKmChange = (val: string) => {
        form.setData((data) => ({
            ...data,
            kilometers_at_maintenance: val,
            oil_change_km: val,
            next_oil_change_km: computeNextOilKm(data.oil_type, val),
        }));
    };

    const toggleIssueLink = (issueId: number) => {
        const list = form.data.linked_inspection_issue_ids as number[];
        const next = list.includes(issueId) ? list.filter((id) => id !== issueId) : [...list, issueId];
        form.setData('linked_inspection_issue_ids', next);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(`/maintenance/${truck.id}/record`, { forceFormData: true });
    };

    return (
        <AuthenticatedLayout title={`Maintenance — ${truck.matricule}`}>
            <Head title={`Maintenance — ${truck.matricule}`} />

            <div>
                <Card>
                    <form onSubmit={submit} className="space-y-5">
                        <SectionTitle>Informations générales</SectionTitle>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <FormInput
                                label="Date"
                                type="date"
                                name="maintenance_date"
                                wrapperClass="mb-0"
                                value={form.data.maintenance_date}
                                onChange={(e) => form.setData('maintenance_date', e.target.value)}
                                error={form.errors.maintenance_date as string | undefined}
                                required
                            />
                            <FormInput
                                label="Distance actuelle (Km au compteur)"
                                type="number"
                                name="kilometers_at_maintenance"
                                wrapperClass="mb-0"
                                value={form.data.kilometers_at_maintenance}
                                onChange={(e) => onKmChange(e.target.value)}
                                error={form.errors.kilometers_at_maintenance as string | undefined}
                                required
                            />
                        </div>

                        <div>
                            <label className="block text-xs font-medium text-[var(--color-text-secondary)] mb-1.5 flex items-center gap-1">
                                <Camera size={14} /> Photo du tableau de bord (preuve du kilométrage)
                            </label>
                            <CameraCapture onCapture={(file) => form.setData('dashboard_photo', file)} error={(form.errors as any)?.dashboard_photo} />
                        </div>

                        <SectionTitle>État des organes mécaniques</SectionTitle>
                        <ComponentStatusList
                            statuses={componentStatuses}
                            value={form.data}
                            onChange={(k, v) => form.setData(k, v)}
                        />

                        <SectionTitle>Filtres changés</SectionTitle>
                        <div>
                            <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 text-sm">
                                <label className="flex items-center gap-2"><input type="checkbox" checked={form.data.filter_oil_changed} onChange={(e) => form.setData('filter_oil_changed', e.target.checked)} /> Huile</label>
                                <label className="flex items-center gap-2"><input type="checkbox" checked={form.data.filter_hydraulic_changed} onChange={(e) => form.setData('filter_hydraulic_changed', e.target.checked)} /> Hydraulique</label>
                                <label className="flex items-center gap-2"><input type="checkbox" checked={form.data.filter_air_changed} onChange={(e) => form.setData('filter_air_changed', e.target.checked)} /> Air</label>
                                <label className="flex items-center gap-2"><input type="checkbox" checked={form.data.filter_fuel_changed} onChange={(e) => form.setData('filter_fuel_changed', e.target.checked)} /> Carburant</label>
                            </div>
                        </div>

                        <MaintenanceItemsField
                            items={form.data.items as LineItem[]}
                            onChange={(items) => form.setData('items', items)}
                            categories={itemCategories}
                            units={itemUnits}
                            errors={form.errors as Record<string, string | undefined>}
                            facture={form.data.facture as File | null}
                            onFactureChange={(f) => form.setData('facture', f)}
                            factureError={form.errors.facture as string | undefined}
                        />

                        {truck.inspection_issues.length > 0 && (
                            <div>
                                <SectionTitle>Findings d'inspection résolus par cette maintenance</SectionTitle>
                                <div className="space-y-1 max-h-52 overflow-y-auto rounded-lg border border-[var(--color-border)] p-2 mt-1.5">
                                    {truck.inspection_issues.map((issue) => (
                                        <label key={issue.id} className="flex items-start gap-2 text-sm py-1 border-b border-[var(--color-border)] last:border-0">
                                            <input
                                                type="checkbox"
                                                checked={(form.data.linked_inspection_issue_ids as number[]).includes(issue.id)}
                                                onChange={() => toggleIssueLink(issue.id)}
                                                className="mt-0.5"
                                            />
                                            <span className="flex-1">
                                                <span className="font-medium">{issue.category}</span>
                                                {' '}<Badge variant={SEVERITY_VARIANT[issue.severity] ?? 'default'}>{issue.severity}</Badge>
                                                {issue.issue_notes && <span className="block text-xs text-[var(--color-text-muted)]">{issue.issue_notes}</span>}
                                                <span className="block text-xs text-[var(--color-text-muted)]">Inspection du {issue.inspection_date}</span>
                                            </span>
                                        </label>
                                    ))}
                                </div>
                            </div>
                        )}

                        <SectionTitle>Fiche de contrôle après travaux</SectionTitle>
                        <ControlChecklist
                            items={controlChecks}
                            value={form.data.control_checks as Record<string, string>}
                            onChange={(v) => form.setData('control_checks', v)}
                        />

                        <SectionTitle>Notes / Observations</SectionTitle>
                        <FormTextarea
                            wrapperClass="mb-0"
                            value={form.data.notes}
                            onChange={(e) => form.setData('notes', e.target.value)}
                            rows={3}
                        />

                        <div className="flex flex-col-reverse sm:flex-row justify-end gap-2 pt-2 border-t border-[var(--color-border)]">
                            <Button variant="secondary" type="button" onClick={() => router.visit('/maintenance')}>Annuler</Button>
                            <Button type="submit" loading={form.processing}>Enregistrer la maintenance</Button>
                        </div>
                    </form>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
