import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import FormInput from '@/components/ui/FormInput';
import FormSelect from '@/components/ui/FormSelect';
import { usePermission } from '@/hooks/usePermission';
import {
    Users, ChevronLeft, ChevronRight, Check,
    Search, Calendar, RotateCcw,
    PhoneOff, ShieldOff, AlertTriangle, Clock,
} from 'lucide-react';
import { clsx } from 'clsx';

type NotificationStatus = 'pending' | 'sent' | 'delivered' | 'read' | 'failed' | 'skipped' | null;

interface DriverRow {
    id: number;
    name: string;
    has_phone: boolean;
    opted_in: boolean;
    dispatched: boolean;
    dispatch_id: number | null;
    wish_provider_id: number | null;
    notified_at: string | null;
    notification_status: NotificationStatus;
    notification_error: string | null;
    current_status: string | null;
    truck: string | null;
    done_today: number;
    ticket_manquant: boolean;
}

interface ProviderOpt { id: number; name: string }

interface Props {
    date: string;
    isPast: boolean;
    isTomorrow: boolean;
    drivers: DriverRow[];
    providers: ProviderOpt[];
    dispatchedCount: number;
}

type FormRow = { driver_id: number; dispatched: boolean; wish_provider_id: number | null };

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

function NotificationStatusLine({ driver, isPast, canEdit }: { driver: DriverRow; isPast: boolean; canEdit: boolean }) {
    const status = driver.notification_status;
    const showRenotify = canEdit && !isPast && driver.dispatch_id !== null;

    const renotify = () => {
        if (!driver.dispatch_id) return;
        router.post(
            `/logistics/planning/${driver.dispatch_id}/renotify`,
            {},
            { preserveScroll: true, preserveState: false },
        );
    };

    // No phone — clearest possible signal (status would also be 'skipped'
    // but we want the action to be "go fix the driver record")
    if (!driver.has_phone) {
        return (
            <div className="text-xs flex items-center gap-1 flex-wrap mt-0.5">
                <span className="inline-flex items-center gap-1 rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400 px-2 py-0.5 font-medium">
                    <PhoneOff size={10} /> Pas de téléphone
                </span>
                <Link href={`/drivers/${driver.id}/edit`} className="text-[var(--color-primary)] hover:underline">
                    Modifier
                </Link>
            </div>
        );
    }

    if (!driver.opted_in) {
        return (
            <div className="text-xs flex items-center gap-1 flex-wrap mt-0.5">
                <span className="inline-flex items-center gap-1 rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400 px-2 py-0.5 font-medium">
                    <ShieldOff size={10} /> Sans consentement
                </span>
                <Link href={`/drivers/${driver.id}/edit`} className="text-[var(--color-primary)] hover:underline">
                    Modifier
                </Link>
            </div>
        );
    }

    if (status === 'failed') {
        return (
            <div className="text-xs flex items-center gap-1 flex-wrap mt-0.5">
                <span
                    className="inline-flex items-center gap-1 rounded-full bg-red-500/10 text-red-600 dark:text-red-400 px-2 py-0.5 font-medium max-w-full"
                    title={driver.notification_error ?? undefined}
                >
                    <AlertTriangle size={10} />
                    <span className="truncate max-w-[12rem]">
                        Échec{driver.notification_error ? ` — ${driver.notification_error}` : ''}
                    </span>
                </span>
                {showRenotify && (
                    <button type="button" onClick={renotify} className="text-[var(--color-primary)] hover:underline inline-flex items-center gap-1">
                        <RotateCcw size={10} /> Renvoyer
                    </button>
                )}
            </div>
        );
    }

    if (status === 'sent' || status === 'delivered' || status === 'read') {
        const label = status === 'read'
            ? `Lu${driver.notified_at ? ` · envoyé le ${driver.notified_at}` : ''}`
            : status === 'delivered'
                ? `Livré${driver.notified_at ? ` à ${driver.notified_at}` : ''}`
                : `Notifié${driver.notified_at ? ` à ${driver.notified_at}` : ''}`;
        return (
            <div className="text-xs flex items-center gap-2 flex-wrap mt-0.5 text-emerald-600 dark:text-emerald-400">
                <span>{label}</span>
                {showRenotify && (
                    <button type="button" onClick={renotify} className="text-[var(--color-primary)] hover:underline inline-flex items-center gap-1" title="Renvoyer la notification">
                        <RotateCcw size={10} /> Renvoyer
                    </button>
                )}
            </div>
        );
    }

    if (status === 'skipped') {
        return (
            <div className="text-xs text-[var(--color-text-muted)] mt-0.5">
                {driver.notification_error ?? 'Notification non envoyée'}
            </div>
        );
    }

    // pending / null
    return (
        <div className="text-xs text-amber-600 dark:text-amber-400 mt-0.5 inline-flex items-center gap-1">
            <Clock size={10} /> Notification en attente
        </div>
    );
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

export default function PlanningIndex({ date, isPast, isTomorrow, drivers, providers, dispatchedCount }: Props) {
    const { can } = usePermission();
    const canEdit = can('daily-dispatch-edit') && !isPast;

    const [rows, setRows] = useState<FormRow[]>(
        drivers.map((d) => ({
            driver_id: d.id,
            dispatched: d.dispatched,
            wish_provider_id: d.wish_provider_id,
        })),
    );

    const providerOptions = useMemo(
        () => [{ value: '', label: '— sans préférence —' }, ...providers.map((p) => ({ value: p.id, label: p.name }))],
        [providers],
    );
    const providersById = useMemo(() => Object.fromEntries(providers.map((p) => [p.id, p.name])), [providers]);
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

    const isDirty = useMemo(() => {
        for (const r of rows) {
            const orig = driversById[r.driver_id];
            if (!orig) continue;
            if (orig.dispatched !== r.dispatched) return true;
            if ((orig.wish_provider_id ?? null) !== (r.wish_provider_id ?? null)) return true;
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
                <div className="flex items-center justify-between gap-2 flex-wrap">
                    <div className="flex items-center gap-2">
                        <Users size={22} className="text-emerald-500" />
                        <h1 className="text-xl font-semibold">Programmation des rotations</h1>
                    </div>
                    <Button variant="secondary" size="sm" onClick={() => router.visit('/logistics/planning/weekly')}>
                        <Calendar size={14} className="mr-1" /> Tableau hebdomadaire
                    </Button>
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
                <div className="grid grid-cols-2 gap-3">
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
                                                onClick={() => setRow(r.driver_id, { dispatched: false, wish_provider_id: null })}
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
                                                <div className="flex items-center gap-2 flex-wrap">
                                                    <span className="truncate">{orig?.name ?? '—'}</span>
                                                    {orig?.truck && <span className="text-xs font-normal text-[var(--color-text-muted)]">· {orig.truck}</span>}
                                                    {orig && (orig.done_today ?? 0) > 0 ? (
                                                        <span className="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 px-2 py-0.5 text-xs font-medium">
                                                            <Check size={10} /> {orig.done_today} rot. aujourd'hui
                                                        </span>
                                                    ) : (
                                                        <span className="rounded-full bg-[var(--color-surface-hover)] text-[var(--color-text-muted)] px-2 py-0.5 text-xs font-medium">Pas encore</span>
                                                    )}
                                                    {orig?.ticket_manquant && (
                                                        <span className="inline-flex items-center gap-1 rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400 px-2 py-0.5 text-xs font-medium">
                                                            <AlertTriangle size={10} /> ticket manquant
                                                        </span>
                                                    )}
                                                </div>
                                                {orig && (
                                                    <NotificationStatusLine
                                                        driver={orig}
                                                        isPast={isPast}
                                                        canEdit={canEdit}
                                                    />
                                                )}
                                            </div>
                                            <div className="w-full sm:w-52">
                                                {canEdit ? (
                                                    <FormSelect
                                                        options={providerOptions}
                                                        value={r.wish_provider_id ?? null}
                                                        onChange={(v) => setRow(r.driver_id, { wish_provider_id: v === '' || v == null ? null : Number(v) })}
                                                        wrapperClass=""
                                                    />
                                                ) : (
                                                    <span className="text-xs text-[var(--color-text-muted)] block truncate">
                                                        {r.wish_provider_id ? `Souhait : ${providersById[r.wish_provider_id] ?? '—'}` : 'sans préférence'}
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
