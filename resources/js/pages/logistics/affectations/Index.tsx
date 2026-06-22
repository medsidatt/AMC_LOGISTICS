import { Head, useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import Modal from '@/components/ui/Modal';
import FormSelect from '@/components/ui/FormSelect';
import { Truck as TruckIcon, UserPlus, X, History as HistoryIcon, ParkingCircle, Users } from 'lucide-react';
import { clsx } from 'clsx';

interface Slot { assignment_id: number; driver_id: number; name: string | null; since: string | null }
interface TruckRow { id: number; matricule: string; titulaire: Slot | null; assistant: Slot | null; parking: boolean }
interface DriverOpt { id: number; name: string }
interface HistoryRow { id: number; truck: string | null; driver: string | null; role: string; started_at: string | null; ended_at: string | null }

interface Props {
    trucks: TruckRow[];
    availableDrivers: DriverOpt[];
    history: HistoryRow[];
    roles: Record<string, string>;
}

export default function AffectationsIndex({ trucks, availableDrivers, history, roles }: Props) {
    const [modal, setModal] = useState<{ truckId: number; matricule: string; role: string } | null>(null);
    const [showHistory, setShowHistory] = useState(false);
    const form = useForm<Record<string, any>>({ truck_id: 0, driver_id: '', role: 'titulaire' });

    const driverOptions = [
        { value: '', label: '— Choisir un chauffeur —' },
        ...availableDrivers.map((d) => ({ value: d.id, label: d.name })),
    ];

    const openAssign = (truckId: number, matricule: string, role: string) => {
        setModal({ truckId, matricule, role });
        form.setData({ truck_id: truckId, driver_id: '', role });
        form.clearErrors();
    };

    const submitAssign = (e: React.FormEvent) => {
        e.preventDefault();
        if (!form.data.driver_id) return;
        form.post('/logistics/affectations/assign', {
            preserveScroll: true,
            onSuccess: () => setModal(null),
        });
    };

    const release = (assignmentId: number) => {
        router.post('/logistics/affectations/release', { assignment_id: assignmentId }, { preserveScroll: true });
    };

    const parkingCount = trucks.filter((t) => t.parking).length;

    const Slot = ({ truck, role, slot }: { truck: TruckRow; role: string; slot: Slot | null }) => {
        if (!slot) {
            return (
                <button
                    type="button"
                    onClick={() => openAssign(truck.id, truck.matricule, role)}
                    className="inline-flex items-center gap-1.5 text-sm text-[var(--color-primary)] hover:underline"
                >
                    <UserPlus size={14} /> Assigner
                </button>
            );
        }
        return (
            <div className="flex items-center justify-between gap-2">
                <div className="min-w-0">
                    <div className="font-medium truncate">{slot.name ?? '—'}</div>
                    {slot.since && <div className="text-xs text-[var(--color-text-muted)]">depuis le {slot.since}</div>}
                </div>
                <div className="flex items-center gap-1 shrink-0">
                    <button type="button" onClick={() => openAssign(truck.id, truck.matricule, role)} title="Remplacer" className="text-xs text-[var(--color-primary)] hover:underline">Remplacer</button>
                    <button type="button" onClick={() => release(slot.assignment_id)} title="Libérer" className="p-1 rounded text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20"><X size={14} /></button>
                </div>
            </div>
        );
    };

    return (
        <AuthenticatedLayout>
            <Head title="Affectations" />
            <div className="space-y-5">
                <div className="flex items-start justify-between gap-3 flex-wrap">
                    <div>
                        <div className="flex items-center gap-2">
                            <Users size={22} className="text-[var(--color-primary)]" />
                            <h1 className="text-xl font-semibold">Affectations chauffeurs — camions</h1>
                        </div>
                        <p className="text-sm text-[var(--color-text-muted)] mt-1">
                            Un chauffeur ne peut conduire qu'un seul camion. Chaque camion a un titulaire et un assistant.
                        </p>
                    </div>
                    <Button variant="secondary" onClick={() => setShowHistory((v) => !v)}>
                        <HistoryIcon size={14} className="mr-1" /> Historique
                    </Button>
                </div>

                <div className="grid grid-cols-3 gap-3">
                    <Card><div className="text-xs uppercase text-[var(--color-text-muted)]">Camions</div><div className="text-2xl font-bold">{trucks.length}</div></Card>
                    <Card><div className="text-xs uppercase text-[var(--color-text-muted)]">Au parking</div><div className={clsx('text-2xl font-bold', parkingCount > 0 && 'text-amber-600 dark:text-amber-400')}>{parkingCount}</div></Card>
                    <Card><div className="text-xs uppercase text-[var(--color-text-muted)]">Chauffeurs dispo.</div><div className="text-2xl font-bold">{availableDrivers.length}</div></Card>
                </div>

                {showHistory && (
                    <Card padding={false}>
                        <div className="px-4 pt-4 pb-2 font-semibold">Historique des affectations</div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="bg-[var(--color-surface-hover)] text-[11px] uppercase tracking-wide text-[var(--color-text-secondary)]">
                                        <th className="px-4 py-2 text-left font-semibold">Camion</th>
                                        <th className="px-4 py-2 text-left font-semibold">Chauffeur</th>
                                        <th className="px-4 py-2 text-left font-semibold">Rôle</th>
                                        <th className="px-4 py-2 text-left font-semibold">Du</th>
                                        <th className="px-4 py-2 text-left font-semibold">Au</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-[var(--color-border)]">
                                    {history.length === 0 ? (
                                        <tr><td colSpan={5} className="px-4 py-6 text-center text-[var(--color-text-muted)]">Aucun historique.</td></tr>
                                    ) : history.map((h) => (
                                        <tr key={h.id}>
                                            <td className="px-4 py-2 font-medium">{h.truck ?? '—'}</td>
                                            <td className="px-4 py-2">{h.driver ?? '—'}</td>
                                            <td className="px-4 py-2">{h.role}</td>
                                            <td className="px-4 py-2 text-[var(--color-text-muted)]">{h.started_at ?? '—'}</td>
                                            <td className="px-4 py-2 text-[var(--color-text-muted)]">{h.ended_at ?? '—'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </Card>
                )}

                <Card padding={false}>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="bg-[var(--color-surface-hover)] text-[11px] uppercase tracking-wide text-[var(--color-text-secondary)]">
                                    <th className="px-4 py-3 text-left font-semibold">Camion</th>
                                    <th className="px-4 py-3 text-left font-semibold w-1/3">Titulaire</th>
                                    <th className="px-4 py-3 text-left font-semibold w-1/3">Assistant</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[var(--color-border)]">
                                {trucks.map((t) => (
                                    <tr key={t.id} className={clsx('align-top', t.parking && 'bg-amber-50/50 dark:bg-amber-900/10')}>
                                        <td className="px-4 py-3 whitespace-nowrap">
                                            <span className="inline-flex items-center gap-1.5 font-semibold">
                                                <TruckIcon size={14} className="text-[var(--color-text-muted)]" /> {t.matricule}
                                            </span>
                                            {t.parking && (
                                                <span className="ml-2 inline-flex items-center gap-1 rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400 px-2 py-0.5 text-xs font-medium">
                                                    <ParkingCircle size={10} /> au parking
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3"><Slot truck={t} role="titulaire" slot={t.titulaire} /></td>
                                        <td className="px-4 py-3"><Slot truck={t} role="assistant" slot={t.assistant} /></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>
            </div>

            <Modal open={modal !== null} onClose={() => setModal(null)} title={modal ? `Affecter un ${roles[modal.role] ?? modal.role} — ${modal.matricule}` : ''}>
                <form onSubmit={submitAssign} className="space-y-4">
                    <FormSelect
                        label="Chauffeur disponible"
                        options={driverOptions}
                        value={form.data.driver_id}
                        onChange={(v) => form.setData('driver_id', v === '' || v == null ? '' : Number(v))}
                        error={form.errors.driver_id as string | undefined}
                    />
                    {availableDrivers.length === 0 && (
                        <p className="text-xs text-amber-600 dark:text-amber-400">Aucun chauffeur disponible — tous sont déjà affectés.</p>
                    )}
                    <div className="flex justify-end gap-2 pt-1">
                        <Button variant="secondary" type="button" onClick={() => setModal(null)}>Annuler</Button>
                        <Button type="submit" loading={form.processing} disabled={!form.data.driver_id}>Affecter</Button>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}
