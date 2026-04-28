import { Head, useForm } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import FormInput from '@/components/ui/FormInput';
import Badge from '@/components/ui/Badge';
import {
    ClipboardCheck, CheckCircle2, AlertTriangle, Gauge, Fuel,
    ChevronDown, ChevronUp, Truck as TruckIcon, Activity, Droplets,
    Timer, MapPin,
} from 'lucide-react';
import { clsx } from 'clsx';

interface ChecklistIssue {
    id: number;
    category: string;
    flagged: boolean;
    issue_notes: string | null;
}

interface ChecklistEntry {
    id: number;
    checklist_date: string;
    start_km: number | null;
    end_km: number | null;
    fuel_filled: number | null;
    tire_condition: string;
    fuel_level: string;
    oil_level: string;
    brakes: string;
    lights: string;
    general_condition_notes: string;
    notes: string | null;
    issues: ChecklistIssue[];
}

interface TruckData {
    id: number;
    matricule: string;
    total_kilometers: number;
    fleeti_last_kilometers: number | null;
    fleeti_last_fuel_level: number | null;
    fleeti_last_synced_at: string | null;
    fleeti_last_speed_kmh: number | null;
    fleeti_last_movement_status: string | null;
}

interface Props {
    driver: { id: number; name: string };
    truck: TruckData;
    currentWeekStart: string;
    currentChecklist: (ChecklistEntry & { week_start_date?: string; status?: string }) | null;
    history: (ChecklistEntry & { week_start_date?: string; status?: string })[];
    options: {
        tire: Record<string, string>;
        brake: Record<string, string>;
        light: Record<string, string>;
        oil: Record<string, string>;
        fuel: Record<string, string>;
        general: Record<string, string>;
    };
}

/* ── Chip selector ── */
function ChipSelect({ label, value, options, onChange, variant }: {
    label: string;
    value: string;
    options: Record<string, string>;
    onChange: (v: string) => void;
    variant?: 'condition' | 'level';
}) {
    const getColor = (key: string) => {
        if (variant === 'condition') {
            if (['bon', 'excellent', 'tous_fonctionnels', 'plein', 'correct'].includes(key)) return 'success';
            if (['acceptable', 'use', 'trois_quarts', 'demi'].includes(key)) return 'warning';
            return 'danger';
        }
        if (variant === 'level') {
            if (['plein', 'trois_quarts'].includes(key)) return 'success';
            if (['demi', 'quart'].includes(key)) return 'warning';
            return 'danger';
        }
        return value === key ? 'primary' : 'muted';
    };

    return (
        <div className="mb-5">
            <label className="block text-sm font-medium text-[var(--color-text)] mb-2">{label}</label>
            <div className="flex flex-wrap gap-2">
                {Object.entries(options).map(([key, lbl]) => {
                    const isActive = value === key;
                    const color = getColor(key);
                    return (
                        <button
                            key={key}
                            type="button"
                            onClick={() => onChange(key)}
                            className={clsx(
                                'px-3 py-2 rounded-xl text-sm font-medium transition-all border-2',
                                isActive ? {
                                    'border-emerald-500 bg-emerald-500/15 text-emerald-700 dark:text-emerald-300': color === 'success',
                                    'border-amber-500 bg-amber-500/15 text-amber-700 dark:text-amber-300': color === 'warning',
                                    'border-red-500 bg-red-500/15 text-red-700 dark:text-red-300': color === 'danger',
                                    'border-[var(--color-primary)] bg-[var(--color-primary)]/15 text-[var(--color-primary)]': color === 'primary',
                                }[`border-${color}`] || 'border-[var(--color-primary)] bg-[var(--color-primary)]/15 text-[var(--color-primary)]'
                                : 'border-transparent bg-[var(--color-surface-hover)] text-[var(--color-text-secondary)]',
                            )}
                        >
                            {lbl}
                        </button>
                    );
                })}
            </div>
        </div>
    );
}

/* ── Issue toggle ── */
function IssueToggle({ category, label, flagged, notes, onToggle, onNotesChange }: {
    category: string;
    label: string;
    flagged: boolean;
    notes: string;
    onToggle: () => void;
    onNotesChange: (v: string) => void;
}) {
    return (
        <div className={clsx('rounded-xl border-2 p-3 transition-all', flagged ? 'border-red-400 bg-red-500/5' : 'border-[var(--color-border)]')}>
            <button type="button" onClick={onToggle} className="flex items-center justify-between w-full text-left">
                <span className="text-sm font-medium text-[var(--color-text)]">{label}</span>
                <div className={clsx('w-6 h-6 rounded-full flex items-center justify-center transition-all',
                    flagged ? 'bg-red-500 text-white' : 'bg-[var(--color-surface-hover)]')}>
                    <AlertTriangle size={12} />
                </div>
            </button>
            {flagged && (
                <input
                    type="text"
                    value={notes}
                    onChange={(e) => onNotesChange(e.target.value)}
                    placeholder="Dtails du problme..."
                    className="mt-2 w-full px-3 py-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm"
                />
            )}
        </div>
    );
}

const ISSUE_CATEGORIES = [
    { key: 'tires', label: 'Pneus' },
    { key: 'brakes', label: 'Freins' },
    { key: 'lights', label: 'Feux' },
    { key: 'oil', label: 'Huile' },
    { key: 'fuel', label: 'Carburant' },
    { key: 'general', label: 'Gnral' },
];

const MOVEMENT_LABEL: Record<string, string> = {
    moving: 'En mouvement',
    idle: 'Ralenti',
    parked: 'Stationn',
};

export default function Checklist({ driver, truck, currentChecklist, history, options }: Props) {
    const [showHistory, setShowHistory] = useState(false);

    // Best available odometer: prefer Fleeti live km, fallback to total_kilometers
    const liveOdometer = truck.fleeti_last_kilometers ?? truck.total_kilometers;

    const form = useForm({
        checklist_date: new Date().toISOString().split('T')[0],
        start_km: liveOdometer > 0 ? String(Math.round(liveOdometer)) : '',
        end_km: '',
        fuel_filled: '',
        tire_condition: 'bon',
        fuel_level: 'plein',
        oil_level: 'correct',
        brakes: 'bon',
        lights: 'tous_fonctionnels',
        general_condition_notes: 'bon',
        notes: '',
        issue_flags: [] as string[],
        issue_notes: {} as Record<string, string>,
    });

    // Distance calculation
    const startKm = parseFloat(form.data.start_km);
    const endKm = parseFloat(form.data.end_km);
    const distance = !isNaN(startKm) && !isNaN(endKm) && endKm >= startKm
        ? Math.round(endKm - startKm)
        : null;
    const hasKmError = !isNaN(startKm) && !isNaN(endKm) && endKm < startKm;

    const toggleIssue = (cat: string) => {
        const flags = form.data.issue_flags.includes(cat)
            ? form.data.issue_flags.filter((f) => f !== cat)
            : [...form.data.issue_flags, cat];
        form.setData('issue_flags', flags);
    };

    const setIssueNotes = (cat: string, value: string) => {
        form.setData('issue_notes', { ...form.data.issue_notes, [cat]: value });
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/drivers/checklist-submit');
    };

    const conditionLabel = (key: string, opts: Record<string, string>) => opts[key] ?? key;
    const conditionColor = (key: string): 'success' | 'warning' | 'danger' => {
        if (['bon', 'excellent', 'tous_fonctionnels', 'plein', 'correct', 'trois_quarts'].includes(key)) return 'success';
        if (['acceptable', 'use', 'demi', 'mou', 'quart', 'bas'].includes(key)) return 'warning';
        return 'danger';
    };

    return (
        <AuthenticatedLayout title="Checklist quotidien">
            <Head title="Checklist quotidien" />

            {/* ── Live truck status strip ── */}
            <div className="rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] p-4 mb-5">
                <div className="flex items-center gap-3 mb-3">
                    <div className="p-2 rounded-xl bg-[var(--color-primary)]/10">
                        <TruckIcon size={20} className="text-[var(--color-primary)]" />
                    </div>
                    <div>
                        <div className="text-lg font-bold text-[var(--color-text)]">{truck.matricule}</div>
                        <div className="text-xs text-[var(--color-text-muted)]">Conducteur: {driver.name}</div>
                    </div>
                    {truck.fleeti_last_movement_status && (
                        <Badge
                            variant={truck.fleeti_last_movement_status === 'moving' ? 'success'
                                : truck.fleeti_last_movement_status === 'idle' ? 'warning' : 'muted'}
                            className="ml-auto"
                        >
                            {MOVEMENT_LABEL[truck.fleeti_last_movement_status] ?? truck.fleeti_last_movement_status}
                        </Badge>
                    )}
                </div>
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div className="flex items-center gap-2 px-3 py-2 rounded-xl bg-[var(--color-surface-hover)]">
                        <Gauge size={16} className="text-blue-500 shrink-0" />
                        <div className="min-w-0">
                            <div className="text-[10px] uppercase text-[var(--color-text-muted)] font-medium">Compteur</div>
                            <div className="text-sm font-bold text-[var(--color-text)] truncate">
                                {Math.round(liveOdometer).toLocaleString('fr-FR')} km
                            </div>
                        </div>
                    </div>
                    {truck.fleeti_last_fuel_level !== null && (
                        <div className="flex items-center gap-2 px-3 py-2 rounded-xl bg-[var(--color-surface-hover)]">
                            <Fuel size={16} className="text-amber-500 shrink-0" />
                            <div className="min-w-0">
                                <div className="text-[10px] uppercase text-[var(--color-text-muted)] font-medium">Carburant</div>
                                <div className="text-sm font-bold text-[var(--color-text)]">
                                    {truck.fleeti_last_fuel_level.toFixed(0)} L
                                </div>
                            </div>
                        </div>
                    )}
                    {truck.fleeti_last_speed_kmh !== null && (
                        <div className="flex items-center gap-2 px-3 py-2 rounded-xl bg-[var(--color-surface-hover)]">
                            <Activity size={16} className="text-emerald-500 shrink-0" />
                            <div className="min-w-0">
                                <div className="text-[10px] uppercase text-[var(--color-text-muted)] font-medium">Vitesse</div>
                                <div className="text-sm font-bold text-[var(--color-text)]">
                                    {truck.fleeti_last_speed_kmh.toFixed(0)} km/h
                                </div>
                            </div>
                        </div>
                    )}
                    {truck.fleeti_last_synced_at && (
                        <div className="flex items-center gap-2 px-3 py-2 rounded-xl bg-[var(--color-surface-hover)]">
                            <Timer size={16} className="text-[var(--color-text-muted)] shrink-0" />
                            <div className="min-w-0">
                                <div className="text-[10px] uppercase text-[var(--color-text-muted)] font-medium">Sync GPS</div>
                                <div className="text-xs font-medium text-[var(--color-text)] truncate">
                                    {truck.fleeti_last_synced_at}
                                </div>
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {currentChecklist ? (
                /* ── Already submitted ── */
                <Card>
                    <div className="flex items-center gap-2 mb-4">
                        <CheckCircle2 size={22} className="text-emerald-500" />
                        <h3 className="text-lg font-semibold text-[var(--color-text)]">Checklist hebdomadaire soumise{currentChecklist.week_start_date ? ` — semaine du ${currentChecklist.week_start_date}` : ''}{currentChecklist.status ? ` (${currentChecklist.status})` : ''}</h3>
                    </div>

                    {(currentChecklist.start_km != null || currentChecklist.end_km != null) && (
                        <div className="flex gap-4 mb-4 p-4 rounded-xl bg-[var(--color-surface-hover)]">
                            {currentChecklist.start_km != null && (
                                <div>
                                    <span className="text-[10px] uppercase text-[var(--color-text-muted)] font-medium">Dpart</span>
                                    <p className="text-sm font-bold">{currentChecklist.start_km.toLocaleString('fr-FR')} km</p>
                                </div>
                            )}
                            {currentChecklist.end_km != null && (
                                <div>
                                    <span className="text-[10px] uppercase text-[var(--color-text-muted)] font-medium">Arrive</span>
                                    <p className="text-sm font-bold">{currentChecklist.end_km.toLocaleString('fr-FR')} km</p>
                                </div>
                            )}
                            {currentChecklist.start_km != null && currentChecklist.end_km != null && (
                                <div>
                                    <span className="text-[10px] uppercase text-[var(--color-text-muted)] font-medium">Distance</span>
                                    <p className="text-sm font-bold text-[var(--color-primary)]">
                                        {(currentChecklist.end_km - currentChecklist.start_km).toLocaleString('fr-FR')} km
                                    </p>
                                </div>
                            )}
                            {currentChecklist.fuel_filled != null && (
                                <div>
                                    <span className="text-[10px] uppercase text-[var(--color-text-muted)] font-medium">Carburant ajout</span>
                                    <p className="text-sm font-bold">{currentChecklist.fuel_filled} L</p>
                                </div>
                            )}
                        </div>
                    )}

                    <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        {([
                            ['Pneus', currentChecklist.tire_condition, options.tire],
                            ['Carburant', currentChecklist.fuel_level, options.fuel],
                            ['Huile', currentChecklist.oil_level, options.oil],
                            ['Freins', currentChecklist.brakes, options.brake],
                            ['Feux', currentChecklist.lights, options.light],
                            ['tat gnral', currentChecklist.general_condition_notes, options.general],
                        ] as [string, string, Record<string, string>][]).map(([label, value, opts]) => (
                            <div key={label} className="p-3 rounded-lg bg-[var(--color-surface-hover)]">
                                <p className="text-xs text-[var(--color-text-muted)]">{label}</p>
                                <Badge variant={conditionColor(value)}>
                                    {conditionLabel(value, opts)}
                                </Badge>
                            </div>
                        ))}
                    </div>

                    {currentChecklist.notes && (
                        <div className="mt-3 p-3 rounded-lg bg-[var(--color-surface-hover)]">
                            <p className="text-xs text-[var(--color-text-muted)]">Notes</p>
                            <p className="text-sm text-[var(--color-text)]">{currentChecklist.notes}</p>
                        </div>
                    )}

                    {currentChecklist.issues.filter(i => i.flagged).length > 0 && (
                        <div className="mt-4">
                            <p className="text-xs text-[var(--color-text-muted)] uppercase mb-2">Problmes signals</p>
                            <div className="flex flex-wrap gap-2">
                                {currentChecklist.issues.filter(i => i.flagged).map((issue) => (
                                    <Badge key={issue.id} variant="danger">
                                        {issue.category}{issue.issue_notes ? ` — ${issue.issue_notes}` : ''}
                                    </Badge>
                                ))}
                            </div>
                        </div>
                    )}
                </Card>
            ) : (
                /* ── Form ── */
                <form onSubmit={submit}>
                    {/* Km + fuel — redesigned professional section */}
                    <Card className="mb-4">
                        <div className="flex items-center gap-2 mb-4">
                            <Gauge size={18} className="text-[var(--color-primary)]" />
                            <h4 className="font-semibold text-[var(--color-text)]">Kilomtrage & Carburant</h4>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            {/* Start KM */}
                            <div>
                                <label className="block text-xs font-medium text-[var(--color-text-muted)] uppercase mb-1.5">
                                    Compteur dpart
                                </label>
                                <div className="relative">
                                    <input
                                        type="number"
                                        value={form.data.start_km}
                                        onChange={(e) => form.setData('start_km', e.target.value)}
                                        className="w-full pl-3 pr-12 py-3 rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] text-lg font-bold text-[var(--color-text)] focus:ring-2 focus:ring-[var(--color-primary)]/20 focus:border-[var(--color-primary)] transition"
                                        placeholder={String(Math.round(liveOdometer))}
                                    />
                                    <span className="absolute right-3 top-1/2 -translate-y-1/2 text-sm font-medium text-[var(--color-text-muted)]">
                                        km
                                    </span>
                                </div>
                                {liveOdometer > 0 && (
                                    <button
                                        type="button"
                                        onClick={() => form.setData('start_km', String(Math.round(liveOdometer)))}
                                        className="mt-1.5 text-xs text-[var(--color-primary)] hover:underline"
                                    >
                                        Utiliser le compteur GPS ({Math.round(liveOdometer).toLocaleString('fr-FR')} km)
                                    </button>
                                )}
                                {form.errors.start_km && <p className="text-xs text-red-500 mt-1">{form.errors.start_km}</p>}
                            </div>

                            {/* End KM */}
                            <div>
                                <label className="block text-xs font-medium text-[var(--color-text-muted)] uppercase mb-1.5">
                                    Compteur arrive
                                </label>
                                <div className="relative">
                                    <input
                                        type="number"
                                        value={form.data.end_km}
                                        onChange={(e) => form.setData('end_km', e.target.value)}
                                        className={clsx(
                                            'w-full pl-3 pr-12 py-3 rounded-xl border text-lg font-bold text-[var(--color-text)] focus:ring-2 focus:ring-[var(--color-primary)]/20 transition',
                                            hasKmError
                                                ? 'border-red-400 bg-red-50 dark:bg-red-900/10 focus:border-red-400'
                                                : 'border-[var(--color-border)] bg-[var(--color-surface)] focus:border-[var(--color-primary)]'
                                        )}
                                        placeholder="Fin de journe"
                                    />
                                    <span className="absolute right-3 top-1/2 -translate-y-1/2 text-sm font-medium text-[var(--color-text-muted)]">
                                        km
                                    </span>
                                </div>
                                {hasKmError && (
                                    <p className="text-xs text-red-500 mt-1">Le compteur arrive doit tre suprieur au dpart</p>
                                )}
                                {form.errors.end_km && <p className="text-xs text-red-500 mt-1">{form.errors.end_km}</p>}
                            </div>

                            {/* Fuel filled */}
                            <div>
                                <label className="block text-xs font-medium text-[var(--color-text-muted)] uppercase mb-1.5">
                                    Carburant ajout
                                </label>
                                <div className="relative">
                                    <input
                                        type="number"
                                        step="0.1"
                                        value={form.data.fuel_filled}
                                        onChange={(e) => form.setData('fuel_filled', e.target.value)}
                                        className="w-full pl-3 pr-12 py-3 rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] text-lg font-bold text-[var(--color-text)] focus:ring-2 focus:ring-[var(--color-primary)]/20 focus:border-[var(--color-primary)] transition"
                                        placeholder="0"
                                    />
                                    <span className="absolute right-3 top-1/2 -translate-y-1/2 text-sm font-medium text-[var(--color-text-muted)]">
                                        litres
                                    </span>
                                </div>
                                {form.errors.fuel_filled && <p className="text-xs text-red-500 mt-1">{form.errors.fuel_filled}</p>}
                            </div>
                        </div>

                        {/* Live distance calculation */}
                        {distance !== null && (
                            <div className="mt-4 flex items-center gap-3 p-3 rounded-xl bg-emerald-50 dark:bg-emerald-900/15 border border-emerald-200 dark:border-emerald-800">
                                <MapPin size={18} className="text-emerald-600 shrink-0" />
                                <div>
                                    <span className="text-xs text-emerald-700 dark:text-emerald-300 font-medium">Distance parcourue aujourd'hui</span>
                                    <span className="ml-2 text-lg font-bold text-emerald-700 dark:text-emerald-300">
                                        {distance.toLocaleString('fr-FR')} km
                                    </span>
                                </div>
                            </div>
                        )}
                    </Card>

                    {/* Condition checks */}
                    <Card className="mb-4">
                        <div className="flex items-center gap-2 mb-4">
                            <ClipboardCheck size={18} className="text-[var(--color-primary)]" />
                            <h4 className="font-semibold text-[var(--color-text)]">tat du vhicule</h4>
                        </div>
                        <ChipSelect label="Pneus" value={form.data.tire_condition} options={options.tire} onChange={(v) => form.setData('tire_condition', v)} variant="condition" />
                        <ChipSelect label="Freins" value={form.data.brakes} options={options.brake} onChange={(v) => form.setData('brakes', v)} variant="condition" />
                        <ChipSelect label="Feux" value={form.data.lights} options={options.light} onChange={(v) => form.setData('lights', v)} variant="condition" />
                        <ChipSelect label="Niveau huile" value={form.data.oil_level} options={options.oil} onChange={(v) => form.setData('oil_level', v)} variant="level" />
                        <ChipSelect label="Niveau carburant" value={form.data.fuel_level} options={options.fuel} onChange={(v) => form.setData('fuel_level', v)} variant="level" />
                        <ChipSelect label="tat gnral" value={form.data.general_condition_notes} options={options.general} onChange={(v) => form.setData('general_condition_notes', v)} variant="condition" />
                    </Card>

                    {/* Issues */}
                    <Card className="mb-4">
                        <div className="flex items-center gap-2 mb-4">
                            <AlertTriangle size={18} className="text-amber-500" />
                            <h4 className="font-semibold text-[var(--color-text)]">Signaler un problme</h4>
                        </div>
                        <div className="grid sm:grid-cols-2 gap-3">
                            {ISSUE_CATEGORIES.map((cat) => (
                                <IssueToggle
                                    key={cat.key}
                                    category={cat.key}
                                    label={cat.label}
                                    flagged={form.data.issue_flags.includes(cat.key)}
                                    notes={form.data.issue_notes[cat.key] ?? ''}
                                    onToggle={() => toggleIssue(cat.key)}
                                    onNotesChange={(v) => setIssueNotes(cat.key, v)}
                                />
                            ))}
                        </div>
                    </Card>

                    {/* Notes */}
                    <Card className="mb-4">
                        <label className="block text-sm font-medium text-[var(--color-text)] mb-2">Notes supplmentaires</label>
                        <textarea
                            value={form.data.notes}
                            onChange={(e) => form.setData('notes', e.target.value)}
                            rows={3}
                            maxLength={500}
                            placeholder="Observations, remarques..."
                            className="w-full px-3 py-2.5 rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] text-sm text-[var(--color-text)] focus:ring-2 focus:ring-[var(--color-primary)]/20 focus:border-[var(--color-primary)] transition resize-none"
                        />
                        <p className="text-xs text-[var(--color-text-muted)] mt-1 text-right">{form.data.notes.length}/500</p>
                    </Card>

                    <Button type="submit" loading={form.processing} className="w-full sm:w-auto" disabled={hasKmError}>
                        <ClipboardCheck size={16} className="mr-2" />
                        Soumettre la checklist hebdomadaire
                    </Button>
                </form>
            )}

            {/* History */}
            {history.length > 0 && (
                <Card className="mt-6">
                    <button onClick={() => setShowHistory(!showHistory)} className="flex items-center justify-between w-full text-left">
                        <h3 className="text-lg font-semibold text-[var(--color-text)]">Historique ({history.length})</h3>
                        {showHistory ? <ChevronUp size={18} className="text-[var(--color-text-muted)]" /> : <ChevronDown size={18} className="text-[var(--color-text-muted)]" />}
                    </button>
                    {showHistory && (
                        <div className="space-y-3 mt-4">
                            {history.map((entry) => {
                                const dist = entry.start_km != null && entry.end_km != null
                                    ? entry.end_km - entry.start_km
                                    : null;
                                const issueCount = entry.issues.filter(i => i.flagged).length;
                                return (
                                    <div key={entry.id} className="rounded-xl border border-[var(--color-border)] p-3">
                                        <div className="flex items-center justify-between mb-2">
                                            <span className="text-sm font-medium text-[var(--color-text)]">{entry.checklist_date}</span>
                                            <div className="flex items-center gap-2">
                                                {dist !== null && <Badge variant="info">{dist.toLocaleString('fr-FR')} km</Badge>}
                                                {issueCount > 0 && <Badge variant="danger">{issueCount} problme(s)</Badge>}
                                                {issueCount === 0 && dist === null && <Badge variant="muted">RAS</Badge>}
                                            </div>
                                        </div>
                                        <div className="flex flex-wrap gap-1.5">
                                            {([
                                                ['Pneus', entry.tire_condition],
                                                ['Freins', entry.brakes],
                                                ['Feux', entry.lights],
                                                ['Huile', entry.oil_level],
                                                ['Carburant', entry.fuel_level],
                                                ['Gnral', entry.general_condition_notes],
                                            ] as [string, string][]).map(([label, value]) => (
                                                <Badge key={label} variant={conditionColor(value)} size="sm">
                                                    {label}: {value}
                                                </Badge>
                                            ))}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </Card>
            )}
        </AuthenticatedLayout>
    );
}
