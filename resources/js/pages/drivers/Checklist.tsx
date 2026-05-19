import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import {
    ClipboardCheck, CheckCircle2,
    ChevronDown, ChevronUp, Truck as TruckIcon,
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
    week_start_date?: string;
    status?: string;
}

interface TruckData {
    id: number;
    matricule: string;
    tire_count: number;
    total_kilometers: number;
}

interface Props {
    driver: { id: number; name: string };
    truck: TruckData;
    currentWeekStart: string;
    currentChecklist: ChecklistEntry | null;
    history: ChecklistEntry[];
    options: {
        tire: Record<string, string>;
        brake: Record<string, string>;
        light: Record<string, string>;
        oil: Record<string, string>;
        general: Record<string, string>;
    };
}

function getColor(key: string): 'success' | 'warning' | 'danger' {
    if (['bon', 'excellent', 'tous_fonctionnels', 'plein', 'correct', 'trois_quarts'].includes(key)) return 'success';
    if (['acceptable', 'use', 'demi', 'mou', 'quart', 'bas'].includes(key)) return 'warning';
    return 'danger';
}

function ChipSelect({ label, value, options, onChange }: {
    label: string;
    value: string;
    options: Record<string, string>;
    onChange: (v: string) => void;
}) {
    return (
        <div className="mb-4">
            <label className="block text-sm font-medium mb-2">{label}</label>
            <div className="flex flex-wrap gap-2">
                {Object.entries(options).map(([key, lbl]) => {
                    const isActive = value === key;
                    const color = getColor(key);
                    const classes = isActive
                        ? color === 'success'
                            ? 'border-emerald-500 bg-emerald-500/15 text-emerald-700 dark:text-emerald-300'
                            : color === 'warning'
                                ? 'border-amber-500 bg-amber-500/15 text-amber-700 dark:text-amber-300'
                                : 'border-red-500 bg-red-500/15 text-red-700 dark:text-red-300'
                        : 'border-transparent bg-[var(--color-surface-hover)] text-[var(--color-text-secondary)]';
                    return (
                        <button
                            key={key}
                            type="button"
                            onClick={() => onChange(key)}
                            className={clsx(
                                'px-3 py-2 rounded-xl text-sm font-medium transition-all border-2 min-h-[40px]',
                                classes,
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

export default function Checklist({ driver, truck, currentWeekStart, currentChecklist, history, options }: Props) {
    const [showHistory, setShowHistory] = useState(false);

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

    return (
        <AuthenticatedLayout title="Checklist hebdomadaire">
            <Head title="Checklist hebdomadaire" />

            <div className="space-y-4 max-w-3xl">
                {/* Compact header */}
                <Card>
                    <div className="flex flex-wrap items-center gap-3">
                        <div className="p-2 rounded-xl bg-[var(--color-primary)]/10 shrink-0">
                            <TruckIcon size={20} className="text-[var(--color-primary)]" />
                        </div>
                        <div className="flex-1 min-w-0">
                            <div className="text-lg font-bold truncate">{truck.matricule}</div>
                            <div className="text-xs text-[var(--color-text-muted)]">
                                Conducteur : {driver.name} · Semaine du {currentWeekStart}
                            </div>
                        </div>
                        {currentChecklist && (
                            <Badge variant="success">
                                <CheckCircle2 size={12} className="mr-1" /> Faite
                            </Badge>
                        )}
                    </div>
                </Card>

                {currentChecklist ? (
                    <Card>
                        <div className="flex items-center gap-2 mb-3">
                            <CheckCircle2 size={20} className="text-emerald-500" />
                            <h3 className="text-base font-semibold">
                                Checklist soumise pour la semaine du {currentChecklist.week_start_date ?? currentWeekStart}
                            </h3>
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
                                    <p className="text-xs text-[var(--color-text-muted)] mb-1">{label}</p>
                                    <Badge variant={getColor(value)}>{opts[value] ?? value}</Badge>
                                </div>
                            ))}
                        </div>

                        {currentChecklist.notes && (
                            <div className="mt-3 p-3 rounded-lg bg-[var(--color-surface-hover)]">
                                <p className="text-xs text-[var(--color-text-muted)] mb-1">Notes</p>
                                <p className="text-sm whitespace-pre-wrap">{currentChecklist.notes}</p>
                            </div>
                        )}

                        <p className="text-xs text-[var(--color-text-muted)] mt-4">
                            Pour signaler une panne en cours de semaine, utilisez « Signaler un problème » dans le menu.
                        </p>
                    </Card>
                ) : (
                    <form onSubmit={submit}>
                        <Card className="mb-4">
                            <div className="flex items-center gap-2 mb-4">
                                <ClipboardCheck size={18} className="text-[var(--color-primary)]" />
                                <h4 className="font-semibold">État du véhicule</h4>
                            </div>
                            <ChipSelect label="Pneus" value={form.data.tire_condition} options={options.tire} onChange={(v) => form.setData('tire_condition', v)} />
                            <ChipSelect label="Freins" value={form.data.brakes} options={options.brake} onChange={(v) => form.setData('brakes', v)} />
                            <ChipSelect label="Feux" value={form.data.lights} options={options.light} onChange={(v) => form.setData('lights', v)} />
                            <ChipSelect label="Niveau huile" value={form.data.oil_level} options={options.oil} onChange={(v) => form.setData('oil_level', v)} />
                            <ChipSelect label="État général" value={form.data.general_condition_notes} options={options.general} onChange={(v) => form.setData('general_condition_notes', v)} />
                        </Card>

                        <Card className="mb-4">
                            <label className="block text-sm font-medium mb-2">Notes supplémentaires</label>
                            <textarea
                                value={form.data.notes}
                                onChange={(e) => form.setData('notes', e.target.value)}
                                rows={3}
                                maxLength={500}
                                placeholder="Observations, remarques..."
                                className="w-full px-3 py-2.5 rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] text-sm focus:ring-2 focus:ring-[var(--color-primary)]/20 focus:border-[var(--color-primary)] transition resize-none"
                            />
                            <p className="text-xs text-[var(--color-text-muted)] mt-1 text-right">{form.data.notes.length}/500</p>
                        </Card>

                        <Button type="submit" loading={form.processing} className="w-full sm:w-auto">
                            <ClipboardCheck size={16} className="mr-2" />
                            Soumettre la checklist
                        </Button>

                        <p className="text-xs text-[var(--color-text-muted)] mt-3">
                            Une seule checklist par semaine. Pour signaler une panne, utilisez « Signaler un problème ».
                        </p>
                    </form>
                )}

                {history.length > 0 && (
                    <Card>
                        <button onClick={() => setShowHistory(!showHistory)} className="flex items-center justify-between w-full text-left">
                            <h3 className="text-base font-semibold">Historique ({history.length})</h3>
                            {showHistory ? <ChevronUp size={18} className="text-[var(--color-text-muted)]" /> : <ChevronDown size={18} className="text-[var(--color-text-muted)]" />}
                        </button>
                        {showHistory && (
                            <div className="space-y-2 mt-4">
                                {history.map((entry) => (
                                    <div key={entry.id} className="rounded-xl border border-[var(--color-border)] p-3">
                                        <div className="flex items-center justify-between mb-2">
                                            <span className="text-sm font-medium">{entry.week_start_date ?? entry.checklist_date}</span>
                                        </div>
                                        <div className="flex flex-wrap gap-1.5">
                                            {([
                                                ['Pneus', entry.tire_condition, options.tire],
                                                ['Freins', entry.brakes, options.brake],
                                                ['Feux', entry.lights, options.light],
                                                ['Huile', entry.oil_level, options.oil],
                                                ['Général', entry.general_condition_notes, options.general],
                                            ] as [string, string, Record<string, string>][]).map(([label, value, opts]) => (
                                                <Badge key={label} variant={getColor(value)} size="sm">
                                                    {label}: {opts[value] ?? value}
                                                </Badge>
                                            ))}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </Card>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
