import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import FormInput from '@/components/ui/FormInput';
import FormSelect from '@/components/ui/FormSelect';
import { usePermission } from '@/hooks/usePermission';
import {
    Users, MessageCircle, ChevronLeft, ChevronRight, Check,
    Search, Truck as TruckIcon, Calendar,
} from 'lucide-react';
import { clsx } from 'clsx';

interface DriverRow {
    id: number;
    name: string;
    dispatched: boolean;
    dispatch_id: number | null;
    truck_id: number | null;
    truck_matricule: string | null;
    notes: string | null;
    notified_at: string | null;
}

interface TruckOpt { id: number; matricule: string }

interface Props {
    date: string;
    isPast: boolean;
    isTomorrow: boolean;
    drivers: DriverRow[];
    trucks: TruckOpt[];
    dispatchedCount: number;
}

type FormRow = { driver_id: number; dispatched: boolean; truck_id: number | null; notes: string };

function shiftDate(iso: string, days: number): string {
    const d = new Date(iso + 'T00:00:00');
    d.setDate(d.getDate() + days);
    return d.toISOString().slice(0, 10);
}

function formatLongDate(iso: string): string {
    const d = new Date(iso + 'T00:00:00');
    return d.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
}

function dayRelativeLabel(iso: string, isTomorrow: boolean, isPast: boolean): string | null {
    const todayIso = new Date().toISOString().slice(0, 10);
    if (iso === todayIso) return "aujourd'hui";
    if (isTomorrow) return 'demain';
    if (isPast) return 'date passée';
    return null;
}

function KpiTile({ value, label, color, icon }: { value: number | string; label: string; color: string; icon: React.ReactNode }) {
    return (
        <Card className="h-full">
            <div className="flex items-center gap-3">
                <div className={`p-2.5 rounded-lg ${color}`}>{icon}</div>
                <div className="min-w-0">
                    <div className="text-xs uppercase tracking-wide text-[var(--color-text-muted)]">{label}</div>
                    <div className="text-2xl font-bold leading-tight">{value}</div>
                </div>
            </div>
        </Card>
    );
}

export default function PlanningIndex({ date, isPast, isTomorrow, drivers, trucks, dispatchedCount }: Props) {
    const { can } = usePermission();
    const canEdit = can('daily-dispatch-edit') && !isPast;

    const [rows, setRows] = useState<FormRow[]>(
        drivers.map((d) => ({
            driver_id: d.id,
            dispatched: d.dispatched,
            truck_id: d.truck_id,
            notes: d.notes ?? '',
        })),
    );
    const [search, setSearch] = useState('');
    const [saving, setSaving] = useState(false);

    const setRow = (driverId: number, patch: Partial<FormRow>) => {
        setRows((prev) => prev.map((r) => (r.driver_id === driverId ? { ...r, ...patch } : r)));
    };

    const toggleAll = (value: boolean) => {
        setRows((prev) => prev.map((r) => ({ ...r, dispatched: value })));
    };

    const goto = (next: string) => router.get('/logistics/planning', { date: next }, { preserveState: false });

    const save = () => {
        setSaving(true);
        router.post('/logistics/planning', { date, dispatches: rows }, {
            onFinish: () => setSaving(false),
        });
    };

    const truckOptions = useMemo(
        () => [{ value: '', label: '— sans camion —' }, ...trucks.map((t) => ({ value: t.id, label: t.matricule }))],
        [trucks],
    );

    const driversById = useMemo(() => Object.fromEntries(drivers.map((d) => [d.id, d])), [drivers]);

    const filteredRows = useMemo(() => {
        if (!search) return rows;
        const q = search.toLowerCase();
        return rows.filter((r) => {
            const name = driversById[r.driver_id]?.name?.toLowerCase() ?? '';
            return name.includes(q);
        });
    }, [rows, search, driversById]);

    const scheduledRows = filteredRows.filter((r) => r.dispatched);
    const availableRows = filteredRows.filter((r) => !r.dispatched);
    const selectedCount = rows.filter((r) => r.dispatched).length;
    const withTruckCount = rows.filter((r) => r.dispatched && r.truck_id).length;

    const isDirty = useMemo(() => {
        for (const r of rows) {
            const orig = driversById[r.driver_id];
            if (!orig) continue;
            if (orig.dispatched !== r.dispatched) return true;
            if ((orig.truck_id ?? null) !== (r.truck_id ?? null)) return true;
            if ((orig.notes ?? '') !== r.notes) return true;
        }
        return false;
    }, [rows, driversById]);

    const relativeLabel = dayRelativeLabel(date, isTomorrow, isPast);
    const todayIso = new Date().toISOString().slice(0, 10);
    const tomorrowIso = shiftDate(todayIso, 1);

    return (
        <AuthenticatedLayout>
            <Head title="Programmation rotations" />
            <div className="space-y-4 pb-20">
                <div className="flex items-center gap-2 flex-wrap">
                    <Users size={22} className="text-emerald-500" />
                    <h1 className="text-xl font-semibold">Programmation des rotations</h1>
                </div>

                {/* Date hero */}
                <Card>
                    <div className="flex flex-wrap items-center gap-4 justify-between">
                        <div className="flex items-center gap-3 min-w-0">
                            <button
                                type="button"
                                onClick={() => goto(shiftDate(date, -1))}
                                className="p-2 rounded-lg hover:bg-[var(--color-surface-hover)] transition shrink-0"
                                title="Jour précédent"
                            >
                                <ChevronLeft size={18} />
                            </button>
                            <div className="min-w-0">
                                <div className="text-xs uppercase tracking-wide text-[var(--color-text-muted)] flex items-center gap-2">
                                    <Calendar size={12} /> Date de programmation
                                </div>
                                <div className="text-xl font-bold capitalize truncate">{formatLongDate(date)}</div>
                                {relativeLabel && (
                                    <div className="text-sm text-[var(--color-text-muted)] italic">{relativeLabel}</div>
                                )}
                            </div>
                            <button
                                type="button"
                                onClick={() => goto(shiftDate(date, 1))}
                                className="p-2 rounded-lg hover:bg-[var(--color-surface-hover)] transition shrink-0"
                                title="Jour suivant"
                            >
                                <ChevronRight size={18} />
                            </button>
                        </div>
                        <div className="flex items-center gap-2 flex-wrap">
                            <FormInput
                                type="date"
                                value={date}
                                onChange={(e) => goto(e.target.value)}
                                wrapperClass="mb-0"
                            />
                            <Button variant="secondary" size="sm" onClick={() => goto(todayIso)}>Aujourd'hui</Button>
                            <Button variant="secondary" size="sm" onClick={() => goto(tomorrowIso)}>Demain</Button>
                        </div>
                    </div>
                </Card>

                {/* KPI tiles */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <KpiTile
                        value={drivers.length}
                        label="Chauffeurs actifs"
                        color="bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                        icon={<Users size={18} />}
                    />
                    <KpiTile
                        value={selectedCount}
                        label="Programmés"
                        color="bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400"
                        icon={<Check size={18} />}
                    />
                    <KpiTile
                        value={withTruckCount}
                        label="Avec camion"
                        color="bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400"
                        icon={<TruckIcon size={18} />}
                    />
                    <KpiTile
                        value={selectedCount - withTruckCount}
                        label="Sans camion"
                        color="bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400"
                        icon={<TruckIcon size={18} />}
                    />
                </div>

                {/* WhatsApp banner */}
                <div className="rounded-xl border border-emerald-200 dark:border-emerald-900/40 bg-emerald-50 dark:bg-emerald-900/10 p-3 flex items-center gap-2 text-sm text-emerald-800 dark:text-emerald-300">
                    <MessageCircle size={14} />
                    <span>Les chauffeurs programmés seront notifiés par WhatsApp dans une prochaine version.</span>
                </div>

                {isPast && (
                    <div className="rounded-xl border border-amber-300 dark:border-amber-900/40 bg-amber-50 dark:bg-amber-900/10 p-3 text-sm text-amber-800 dark:text-amber-300">
                        Cette date est passée — affichage en lecture seule.
                    </div>
                )}

                {/* Search + actions */}
                <Card>
                    <div className="flex flex-wrap items-center gap-3">
                        <div className="relative flex-1 min-w-[200px]">
                            <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-[var(--color-text-muted)]" />
                            <input
                                type="text"
                                placeholder="Rechercher un chauffeur…"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="w-full pl-9 pr-3 py-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm focus:ring-2 focus:ring-[var(--color-primary)]/20 focus:border-[var(--color-primary)] transition"
                            />
                        </div>
                        {canEdit && (
                            <div className="flex items-center gap-2 text-sm">
                                <button type="button" onClick={() => toggleAll(true)} className="text-[var(--color-primary)] hover:underline">Tout cocher</button>
                                <span className="text-[var(--color-text-muted)]">·</span>
                                <button type="button" onClick={() => toggleAll(false)} className="text-[var(--color-primary)] hover:underline">Tout décocher</button>
                            </div>
                        )}
                    </div>
                </Card>

                {/* Programmés */}
                {scheduledRows.length > 0 && (
                    <div>
                        <div className="text-xs uppercase tracking-wider font-semibold text-[var(--color-text-muted)] mt-2 mb-1 flex items-center gap-2">
                            <Check size={12} className="text-emerald-500" /> Programmés ({scheduledRows.length})
                        </div>
                        <Card padding={false}>
                            <div className="divide-y divide-[var(--color-border)]">
                                {scheduledRows.map((r) => {
                                    const orig = driversById[r.driver_id];
                                    return (
                                        <div key={r.driver_id} className="p-3 flex flex-wrap items-center gap-3 bg-emerald-50/40 dark:bg-emerald-900/10">
                                            <button
                                                type="button"
                                                disabled={!canEdit}
                                                onClick={() => setRow(r.driver_id, { dispatched: false, truck_id: null, notes: '' })}
                                                className={clsx(
                                                    'w-6 h-6 rounded border-2 flex items-center justify-center transition shrink-0',
                                                    'border-emerald-500 bg-emerald-500 text-white',
                                                    !canEdit && 'opacity-50 cursor-not-allowed',
                                                )}
                                                title="Retirer de la programmation"
                                            >
                                                <Check size={14} strokeWidth={3} />
                                            </button>
                                            <div className="font-semibold min-w-0 flex-1">
                                                <div className="truncate">{orig?.name ?? '—'}</div>
                                                {orig?.notified_at ? (
                                                    <div className="text-xs text-emerald-600 dark:text-emerald-400">Notifié le {orig.notified_at}</div>
                                                ) : (
                                                    <div className="text-xs text-amber-600 dark:text-amber-400">Notification en attente</div>
                                                )}
                                            </div>
                                            <div className="w-full sm:w-44">
                                                {canEdit ? (
                                                    <FormSelect
                                                        options={truckOptions}
                                                        value={r.truck_id ?? null}
                                                        onChange={(v) => setRow(r.driver_id, { truck_id: v === '' || v == null ? null : Number(v) })}
                                                        wrapperClass=""
                                                    />
                                                ) : r.truck_id ? (
                                                    <Badge variant="muted">{trucks.find((t) => t.id === r.truck_id)?.matricule ?? '—'}</Badge>
                                                ) : (
                                                    <span className="text-[var(--color-text-muted)] text-sm">sans camion</span>
                                                )}
                                            </div>
                                            <div className="w-full sm:w-56">
                                                {canEdit ? (
                                                    <input
                                                        type="text"
                                                        value={r.notes}
                                                        onChange={(e) => setRow(r.driver_id, { notes: e.target.value })}
                                                        placeholder="Ex : carrière A, 3 rotations…"
                                                        className="w-full px-3 py-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm"
                                                        maxLength={500}
                                                    />
                                                ) : (
                                                    <span className="text-xs text-[var(--color-text-muted)] block truncate" title={r.notes}>
                                                        {r.notes || '—'}
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </Card>
                    </div>
                )}

                {/* Non programmés */}
                <div>
                    <div className="text-xs uppercase tracking-wider font-semibold text-[var(--color-text-muted)] mt-2 mb-1">
                        Chauffeurs disponibles ({availableRows.length})
                    </div>
                    <Card padding={false}>
                        {availableRows.length === 0 ? (
                            <p className="p-6 text-sm text-center text-[var(--color-text-muted)]">
                                {search ? 'Aucun chauffeur ne correspond à la recherche.' : 'Tous les chauffeurs sont déjà programmés.'}
                            </p>
                        ) : (
                            <div className="divide-y divide-[var(--color-border)]">
                                {availableRows.map((r) => {
                                    const orig = driversById[r.driver_id];
                                    return (
                                        <button
                                            key={r.driver_id}
                                            type="button"
                                            disabled={!canEdit}
                                            onClick={() => setRow(r.driver_id, { dispatched: true })}
                                            className={clsx(
                                                'w-full p-3 flex items-center gap-3 text-left transition',
                                                canEdit ? 'hover:bg-[var(--color-surface-hover)] cursor-pointer' : 'opacity-70 cursor-not-allowed',
                                            )}
                                        >
                                            <div className="w-6 h-6 rounded border-2 border-[var(--color-border)] flex items-center justify-center shrink-0">
                                                {/* empty */}
                                            </div>
                                            <span className="text-sm">{orig?.name ?? '—'}</span>
                                        </button>
                                    );
                                })}
                            </div>
                        )}
                    </Card>
                </div>
            </div>

            {/* Sticky save bar */}
            {canEdit && isDirty && (
                <div className="fixed bottom-4 left-4 right-4 lg:left-auto lg:right-8 lg:bottom-8 z-30">
                    <div className="rounded-2xl bg-[var(--color-surface)] border border-[var(--color-border)] shadow-lg p-3 flex items-center gap-3 max-w-md mx-auto lg:ml-auto">
                        <div className="text-sm flex-1">
                            <span className="font-medium">Modifications non enregistrées</span>
                            <span className="text-[var(--color-text-muted)] ml-2">{selectedCount} programmé{selectedCount > 1 ? 's' : ''}</span>
                        </div>
                        <Button onClick={save} loading={saving}>
                            Enregistrer
                        </Button>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
