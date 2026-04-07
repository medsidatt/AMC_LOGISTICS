import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import Modal from '@/components/ui/Modal';
import FormInput from '@/components/ui/FormInput';
import FormSelect from '@/components/ui/FormSelect';
import ConfirmDialog from '@/components/ui/ConfirmDialog';
import Pagination from '@/components/ui/Pagination';
import { Plus, Lock, Ban } from 'lucide-react';
import { clsx } from 'clsx';

interface Rule {
    id: number;
    truck_id: number;
    truck: string;
    maintenance_type: string;
    interval_km: number;
    warning_threshold_km: number;
    status: string;
    is_active: boolean;
    deactivated_at: string | null;
    created_at: string | null;
}

interface Props {
    profiles: { data: Rule[]; current_page: number; last_page: number; per_page: number; total: number; from: number | null; to: number | null };
    trucks: { id: number; matricule: string }[];
    maintenanceTypes: { value: string; label: string }[];
}

export default function Rules({ profiles, trucks, maintenanceTypes }: Props) {
    const [showCreate, setShowCreate] = useState(false);
    const [deactivateUrl, setDeactivateUrl] = useState<string | null>(null);

    const truckOpts = trucks.map((t) => ({ value: t.id, label: t.matricule }));

    const form = useForm({
        truck_id: '' as string | number,
        maintenance_type: 'general',
        interval_km: '',
        warning_threshold_km: '500',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/maintenance/rules', { onSuccess: () => { setShowCreate(false); form.reset(); } });
    };

    return (
        <AuthenticatedLayout title="Règles de maintenance">
            <Head title="Règles de maintenance" />

            <div className="flex justify-end mb-4">
                <Button icon={<Plus size={16} />} onClick={() => { form.reset(); setShowCreate(true); }}>Nouvelle règle</Button>
            </div>

            <Card padding={false}>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="bg-[var(--color-surface-hover)]">
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Camion</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Type</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-[var(--color-text-secondary)]">
                                    <span className="flex items-center gap-1">Intervalle (km) <Lock size={10} className="text-[var(--color-text-muted)]" /></span>
                                </th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Seuil alerte</th>
                                <th className="px-4 py-3 text-center text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Statut</th>
                                <th className="px-4 py-3 text-center text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Active</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Créée le</th>
                                <th className="px-4 py-3 text-center text-xs font-semibold uppercase text-[var(--color-text-secondary)]">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-[var(--color-border)]">
                            {profiles.data.length === 0 ? (
                                <tr><td colSpan={8} className="px-4 py-8 text-center text-[var(--color-text-muted)]">Aucune règle</td></tr>
                            ) : profiles.data.map((rule) => (
                                <tr key={rule.id} className={clsx('transition-colors', rule.is_active ? 'hover:bg-[var(--color-surface-hover)]' : 'opacity-50')}>
                                    <td className="px-4 py-3 text-[var(--color-text)] font-medium">{rule.truck}</td>
                                    <td className="px-4 py-3 text-[var(--color-text)]">{maintenanceTypes.find((t) => t.value === rule.maintenance_type)?.label ?? rule.maintenance_type}</td>
                                    <td className="px-4 py-3 text-[var(--color-text)] font-mono">{rule.interval_km?.toLocaleString('fr-FR')}</td>
                                    <td className="px-4 py-3 text-[var(--color-text)]">{rule.warning_threshold_km?.toLocaleString('fr-FR')} km</td>
                                    <td className="px-4 py-3 text-center">
                                        <Badge variant={rule.status === 'red' ? 'danger' : rule.status === 'yellow' ? 'warning' : 'success'}>
                                            {rule.status === 'red' ? 'Urgent' : rule.status === 'yellow' ? 'Bientôt' : 'OK'}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3 text-center">
                                        <Badge variant={rule.is_active ? 'success' : 'muted'}>{rule.is_active ? 'Active' : 'Inactive'}</Badge>
                                    </td>
                                    <td className="px-4 py-3 text-[var(--color-text-secondary)]">{rule.created_at ?? '-'}</td>
                                    <td className="px-4 py-3 text-center">
                                        {rule.is_active && (
                                            <button onClick={() => setDeactivateUrl(`/maintenance/rules/${rule.id}/deactivate`)}
                                                className="p-1.5 rounded-lg text-[var(--color-danger)] hover:bg-[var(--color-danger)]/10 transition" title="Désactiver">
                                                <Ban size={14} />
                                            </button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <div className="px-5 pb-5">
                    <Pagination meta={profiles} />
                </div>
            </Card>

            <Modal open={showCreate} onClose={() => setShowCreate(false)} title="Nouvelle règle de maintenance">
                <form onSubmit={submit}>
                    <FormSelect label="Camion" options={truckOpts} value={form.data.truck_id} onChange={(v) => form.setData('truck_id', v ?? '')} error={form.errors.truck_id} required />
                    <FormSelect label="Type" options={maintenanceTypes} value={form.data.maintenance_type} onChange={(v) => form.setData('maintenance_type', String(v ?? 'general'))} error={form.errors.maintenance_type} required />
                    <FormInput label="Intervalle (km)" type="number" name="interval_km" value={form.data.interval_km} onChange={(e) => form.setData('interval_km', e.target.value)} error={form.errors.interval_km} required />
                    <FormInput label="Seuil d'alerte (km)" type="number" name="warning_threshold_km" value={form.data.warning_threshold_km} onChange={(e) => form.setData('warning_threshold_km', e.target.value)} error={form.errors.warning_threshold_km} />
                    <div className="mt-2 p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800">
                        <p className="text-xs text-amber-700 dark:text-amber-300 flex items-center gap-1">
                            <Lock size={12} /> L'intervalle est immuable une fois créé. Pour changer, désactivez cette règle et créez-en une nouvelle.
                        </p>
                    </div>
                    <div className="flex justify-end gap-2 mt-6">
                        <Button variant="secondary" onClick={() => setShowCreate(false)}>Annuler</Button>
                        <Button type="submit" loading={form.processing}>Créer</Button>
                    </div>
                </form>
            </Modal>

            <ConfirmDialog
                open={!!deactivateUrl}
                onClose={() => setDeactivateUrl(null)}
                title="Désactiver la règle"
                message="Cette règle sera désactivée. Les maintenances passées resteront liées à cette règle. Vous pourrez créer une nouvelle règle avec un intervalle différent."
                confirmLabel="Désactiver"
                onConfirm={() => {
                    if (deactivateUrl) {
                        const url = deactivateUrl;
                        setDeactivateUrl(null);
                        import('@inertiajs/react').then(({ router }) => router.post(url));
                    }
                }}
            />
        </AuthenticatedLayout>
    );
}
