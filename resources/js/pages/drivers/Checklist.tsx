import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import {
    ClipboardCheck, CheckCircle2, Gauge, Fuel,
    ChevronDown, ChevronUp, Truck as TruckIcon, Activity,
    Timer,
} from 'lucide-react';
import { clsx } from 'clsx';

interface ChecklistEntry {
    id: number;
    checklist_date: string;
    tire_condition: string;
    oil_level: string;
    brakes: string;
    lights: string;
    general_condition_notes: string;
    notes: string | null;
}

interface TruckData {
    id: number;
    matricule: string;
    tire_count: number;
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

const MOVEMENT_LABEL: Record<string, string> = {
    moving: 'En mouvement',
    idle: 'Ralenti',
    parked: 'Stationné',
};

export default function Checklist({ driver, truck, currentChecklist, history, options }: Props) {
    const [showHistory, setShowHistory] = useState(false);

    const liveOdometer = truck.fleeti_last_kilometers ?? truck.total_kilometers;

    const form = useForm({
        checklist_date: new Date().toISOString().split('T')[0],
        tire_condition: 'bon',
        oil_level: 'correct',
        brakes: 'bon',
        lights: 'tous_fonctionnels',
        general_condition_notes: 'bon',
        notes: '',
    });

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
        <AuthenticatedLayout title="Checklist hebdomadaire">
            <Head title="Checklist hebdomadaire" />

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

                    <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        {([
                            ['Pneus', currentChecklist.tire_condition, options.tire],
                            ['Huile', currentChecklist.oil_level, options.oil],
                            ['Freins', currentChecklist.brakes, options.brake],
                            ['Feux', currentChecklist.lights, options.light],
                            ['État général', currentChecklist.general_condition_notes, options.general],
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
                </Card>
            ) : (
                /* ── Form ── */
                <form onSubmit={submit}>
                    <Card className="mb-4">
                        <div className="flex items-center gap-2 mb-4">
                            <ClipboardCheck size={18} className="text-[var(--color-primary)]" />
                            <h4 className="font-semibold text-[var(--color-text)]">État du véhicule</h4>
                        </div>
                        <ChipSelect label="Pneus" value={form.data.tire_condition} options={options.tire} onChange={(v) => form.setData('tire_condition', v)} variant="condition" />
                        <ChipSelect label="Freins" value={form.data.brakes} options={options.brake} onChange={(v) => form.setData('brakes', v)} variant="condition" />
                        <ChipSelect label="Feux" value={form.data.lights} options={options.light} onChange={(v) => form.setData('lights', v)} variant="condition" />
                        <ChipSelect label="Niveau huile" value={form.data.oil_level} options={options.oil} onChange={(v) => form.setData('oil_level', v)} variant="level" />
                        <ChipSelect label="État général" value={form.data.general_condition_notes} options={options.general} onChange={(v) => form.setData('general_condition_notes', v)} variant="condition" />
                    </Card>

                    <Card className="mb-4">
                        <label className="block text-sm font-medium text-[var(--color-text)] mb-2">Notes supplémentaires</label>
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

                    <Button type="submit" loading={form.processing} className="w-full sm:w-auto">
                        <ClipboardCheck size={16} className="mr-2" />
                        Soumettre la checklist hebdomadaire
                    </Button>

                    <p className="text-xs text-[var(--color-text-muted)] mt-3">
                        Besoin de signaler une panne ou un défaut ? Utilisez la page « Signaler un problème » dans le menu.
                    </p>
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
                            {history.map((entry) => (
                                <div key={entry.id} className="rounded-xl border border-[var(--color-border)] p-3">
                                    <div className="flex items-center justify-between mb-2">
                                        <span className="text-sm font-medium text-[var(--color-text)]">{entry.checklist_date}</span>
                                    </div>
                                    <div className="flex flex-wrap gap-1.5">
                                        {([
                                            ['Pneus', entry.tire_condition],
                                            ['Freins', entry.brakes],
                                            ['Feux', entry.lights],
                                            ['Huile', entry.oil_level],
                                            ['Général', entry.general_condition_notes],
                                        ] as [string, string][]).map(([label, value]) => (
                                            <Badge key={label} variant={conditionColor(value)} size="sm">
                                                {label}: {value}
                                            </Badge>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </Card>
            )}
        </AuthenticatedLayout>
    );
}
