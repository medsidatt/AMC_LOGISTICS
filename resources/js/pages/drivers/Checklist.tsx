import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import FormInput from '@/components/ui/FormInput';
import Badge from '@/components/ui/Badge';
import { ClipboardCheck, CheckCircle2, AlertTriangle, Gauge, Fuel, ChevronDown, ChevronUp } from 'lucide-react';
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

interface Props {
    driver: { id: number; name: string };
    truck: { id: number; matricule: string; total_kilometers: number };
    todayChecklist: ChecklistEntry | null;
    history: ChecklistEntry[];
    options: {
        tire: Record<string, string>;
        brake: Record<string, string>;
        light: Record<string, string>;
        oil: Record<string, string>;
        fuel: Record<string, string>;
        general: Record<string, string>;
    };
}

// Chip selector — mobile-friendly tappable buttons
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

// Issue toggle
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
                    placeholder="Détails du problème..."
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
    { key: 'general', label: 'Général' },
];

export default function Checklist({ driver, truck, todayChecklist, history, options }: Props) {
    const [showHistory, setShowHistory] = useState(false);

    const form = useForm({
        checklist_date: new Date().toISOString().split('T')[0],
        start_km: '',
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
        <AuthenticatedLayout title="Checklist">
            <Head title="Checklist" />

            {/* Header info strip */}
            <div className="flex flex-wrap items-center gap-3 mb-4">
                <div className="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-[var(--color-surface)]">
                    <span className="text-xs text-[var(--color-text-muted)]">Conducteur</span>
                    <span className="text-sm font-medium text-[var(--color-text)]">{driver.name}</span>
                </div>
                <div className="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-[var(--color-surface)]">
                    <span className="text-xs text-[var(--color-text-muted)]">Camion</span>
                    <span className="text-sm font-medium text-[var(--color-text)]">{truck.matricule}</span>
                </div>
                <div className="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-[var(--color-surface)]">
                    <Gauge size={14} className="text-[var(--color-text-muted)]" />
                    <span className="text-sm font-medium text-[var(--color-text)]">{truck.total_kilometers?.toLocaleString('fr-FR')} km</span>
                </div>
            </div>

            {todayChecklist ? (
                /* ── Already submitted ── */
                <Card>
                    <div className="flex items-center gap-2 mb-4">
                        <CheckCircle2 size={22} className="text-emerald-500" />
                        <h3 className="text-lg font-semibold text-[var(--color-text)]">Checklist soumise</h3>
                    </div>

                    {(todayChecklist.start_km || todayChecklist.end_km) && (
                        <div className="flex gap-4 mb-4 p-3 rounded-lg bg-[var(--color-surface-hover)]">
                            {todayChecklist.start_km != null && <div><span className="text-xs text-[var(--color-text-muted)]">Départ</span><p className="text-sm font-medium">{todayChecklist.start_km.toLocaleString('fr-FR')} km</p></div>}
                            {todayChecklist.end_km != null && <div><span className="text-xs text-[var(--color-text-muted)]">Arrivée</span><p className="text-sm font-medium">{todayChecklist.end_km.toLocaleString('fr-FR')} km</p></div>}
                            {todayChecklist.start_km != null && todayChecklist.end_km != null && (
                                <div><span className="text-xs text-[var(--color-text-muted)]">Distance</span><p className="text-sm font-medium text-[var(--color-primary)]">{(todayChecklist.end_km - todayChecklist.start_km).toLocaleString('fr-FR')} km</p></div>
                            )}
                            {todayChecklist.fuel_filled != null && <div><span className="text-xs text-[var(--color-text-muted)]">Carburant ajouté</span><p className="text-sm font-medium">{todayChecklist.fuel_filled} L</p></div>}
                        </div>
                    )}

                    <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        {[
                            ['Pneus', todayChecklist.tire_condition, options.tire],
                            ['Carburant', todayChecklist.fuel_level, options.fuel],
                            ['Huile', todayChecklist.oil_level, options.oil],
                            ['Freins', todayChecklist.brakes, options.brake],
                            ['Feux', todayChecklist.lights, options.light],
                            ['État général', todayChecklist.general_condition_notes, options.general],
                        ].map(([label, value, opts]) => (
                            <div key={label as string} className="p-3 rounded-lg bg-[var(--color-surface-hover)]">
                                <p className="text-xs text-[var(--color-text-muted)]">{label as string}</p>
                                <Badge variant={conditionColor(value as string)}>
                                    {conditionLabel(value as string, opts as Record<string, string>)}
                                </Badge>
                            </div>
                        ))}
                    </div>

                    {todayChecklist.notes && (
                        <div className="mt-3 p-3 rounded-lg bg-[var(--color-surface-hover)]">
                            <p className="text-xs text-[var(--color-text-muted)]">Notes</p>
                            <p className="text-sm text-[var(--color-text)]">{todayChecklist.notes}</p>
                        </div>
                    )}

                    {todayChecklist.issues.filter(i => i.flagged).length > 0 && (
                        <div className="mt-4">
                            <p className="text-xs text-[var(--color-text-muted)] uppercase mb-2">Problèmes signalés</p>
                            <div className="flex flex-wrap gap-2">
                                {todayChecklist.issues.filter(i => i.flagged).map((issue) => (
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
                    {/* Km section */}
                    <Card className="mb-4">
                        <div className="flex items-center gap-2 mb-4">
                            <Gauge size={18} className="text-[var(--color-primary)]" />
                            <h4 className="font-semibold text-[var(--color-text)]">Kilométrage</h4>
                        </div>
                        <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            <FormInput label="Km départ" type="number" name="start_km" value={form.data.start_km}
                                onChange={(e) => form.setData('start_km', e.target.value)} error={form.errors.start_km}
                                placeholder={String(Math.round(truck.total_kilometers))} />
                            <FormInput label="Km arrivée" type="number" name="end_km" value={form.data.end_km}
                                onChange={(e) => form.setData('end_km', e.target.value)} error={form.errors.end_km} />
                            <FormInput label="Carburant (L)" type="number" step="0.1" name="fuel_filled" value={form.data.fuel_filled}
                                onChange={(e) => form.setData('fuel_filled', e.target.value)} error={form.errors.fuel_filled} />
                        </div>
                    </Card>

                    {/* Condition checks */}
                    <Card className="mb-4">
                        <div className="flex items-center gap-2 mb-4">
                            <ClipboardCheck size={18} className="text-[var(--color-primary)]" />
                            <h4 className="font-semibold text-[var(--color-text)]">État du véhicule</h4>
                        </div>
                        <ChipSelect label="Pneus" value={form.data.tire_condition} options={options.tire} onChange={(v) => form.setData('tire_condition', v)} variant="condition" />
                        <ChipSelect label="Freins" value={form.data.brakes} options={options.brake} onChange={(v) => form.setData('brakes', v)} variant="condition" />
                        <ChipSelect label="Feux" value={form.data.lights} options={options.light} onChange={(v) => form.setData('lights', v)} variant="condition" />
                        <ChipSelect label="Niveau huile" value={form.data.oil_level} options={options.oil} onChange={(v) => form.setData('oil_level', v)} variant="level" />
                        <ChipSelect label="Niveau carburant" value={form.data.fuel_level} options={options.fuel} onChange={(v) => form.setData('fuel_level', v)} variant="level" />
                        <ChipSelect label="État général" value={form.data.general_condition_notes} options={options.general} onChange={(v) => form.setData('general_condition_notes', v)} variant="condition" />
                    </Card>

                    {/* Issues */}
                    <Card className="mb-4">
                        <div className="flex items-center gap-2 mb-4">
                            <AlertTriangle size={18} className="text-[var(--color-warning)]" />
                            <h4 className="font-semibold text-[var(--color-text)]">Signaler un problème</h4>
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
                        <FormInput label="Notes supplémentaires" name="notes" value={form.data.notes}
                            onChange={(e) => form.setData('notes', e.target.value)} error={form.errors.notes} />
                    </Card>

                    <Button type="submit" loading={form.processing} className="w-full sm:w-auto">
                        Soumettre la checklist
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
                            {history.map((entry) => (
                                <div key={entry.id} className="rounded-xl border border-[var(--color-border)] p-3">
                                    <div className="flex items-center justify-between mb-2">
                                        <span className="text-sm font-medium text-[var(--color-text)]">{entry.checklist_date}</span>
                                        <div className="flex items-center gap-2">
                                            {entry.start_km != null && entry.end_km != null && (
                                                <Badge variant="info">{(entry.end_km - entry.start_km).toLocaleString('fr-FR')} km</Badge>
                                            )}
                                            {entry.issues.filter(i => i.flagged).length > 0 && (
                                                <Badge variant="danger">{entry.issues.filter(i => i.flagged).length} problème(s)</Badge>
                                            )}
                                        </div>
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        {[
                                            ['Pneus', entry.tire_condition],
                                            ['Freins', entry.brakes],
                                            ['Feux', entry.lights],
                                            ['Huile', entry.oil_level],
                                            ['Carburant', entry.fuel_level],
                                            ['Général', entry.general_condition_notes],
                                        ].map(([label, value]) => (
                                            <Badge key={label as string} variant={conditionColor(value as string)} size="sm">
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
