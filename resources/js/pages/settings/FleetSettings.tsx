import { Head, useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import FormInput from '@/components/ui/FormInput';
import { formatNumber } from '@/utils/formatters';
import { Settings, Target, RotateCcw } from 'lucide-react';

interface MonthlyTarget {
    year: number;
    month: number;
    label: string;
    target: number | null;
    effective: number;
    is_default: boolean;
}

interface Props {
    setting: {
        monthly_target_tonnage: number;
        weight_gap_threshold: number;
        price_per_litre: number;
    };
    defaultTarget: number;
    monthlyTargets: MonthlyTarget[];
}

function MonthRow({ row, defaultTarget }: { row: MonthlyTarget; defaultTarget: number }) {
    const [value, setValue] = useState<string>(row.target !== null ? String(row.target) : '');
    const [saving, setSaving] = useState(false);

    const submit = () => {
        setSaving(true);
        router.post('/settings/fleet/monthly-target', {
            year: row.year,
            month: row.month,
            target_tonnage: value === '' ? null : Number(value),
        }, {
            preserveScroll: true,
            onFinish: () => setSaving(false),
        });
    };

    const reset = () => {
        setValue('');
        router.post('/settings/fleet/monthly-target', {
            year: row.year,
            month: row.month,
            target_tonnage: null,
        }, {
            preserveScroll: true,
        });
    };

    return (
        <tr className="border-b border-[var(--color-border)] last:border-0">
            <td className="py-2 pr-3 text-sm capitalize">{row.label}</td>
            <td className="py-2 pr-3">
                <input
                    type="number"
                    step="1"
                    placeholder={`Défaut: ${formatNumber(defaultTarget, 0)}`}
                    value={value}
                    onChange={(e) => setValue(e.target.value)}
                    onBlur={() => {
                        const current = row.target !== null ? String(row.target) : '';
                        if (value !== current) submit();
                    }}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                            (e.target as HTMLInputElement).blur();
                        }
                    }}
                    className="w-32 px-2 py-1 text-sm rounded border border-[var(--color-border)] bg-[var(--color-surface)] text-[var(--color-text)]"
                />
            </td>
            <td className="py-2 pr-3 text-sm font-medium text-[var(--color-text)]">
                {formatNumber(row.effective, 0)} T
            </td>
            <td className="py-2 pr-3">
                {row.is_default
                    ? <Badge variant="muted">Défaut</Badge>
                    : <Badge variant="success">Personnalisé</Badge>}
            </td>
            <td className="py-2 text-right">
                {! row.is_default && (
                    <button
                        type="button"
                        onClick={reset}
                        disabled={saving}
                        title="Revenir au défaut"
                        className="p-1.5 rounded hover:bg-[var(--color-surface-hover)] text-[var(--color-text-muted)] hover:text-[var(--color-warning)]"
                    >
                        <RotateCcw size={14} />
                    </button>
                )}
            </td>
        </tr>
    );
}

export default function FleetSettingsPage({ setting, defaultTarget, monthlyTargets }: Props) {
    const form = useForm({
        monthly_target_tonnage: String(setting.monthly_target_tonnage ?? 2000),
        weight_gap_threshold: String(setting.weight_gap_threshold ?? 0.5),
        price_per_litre: String(setting.price_per_litre ?? 730),
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.put('/settings/fleet');
    };

    return (
        <AuthenticatedLayout title="Paramètres flotte">
            <Head title="Paramètres flotte" />

            <Card
                className="mb-6"
                header={
                    <div className="flex items-center gap-2">
                        <Settings size={16} />
                        <span className="text-sm font-semibold">Paramètres globaux KPI</span>
                    </div>
                }
            >
                <form onSubmit={submit} className="max-w-lg space-y-1">
                    <FormInput
                        label="Objectif mensuel par défaut (tonnes)"
                        name="monthly_target_tonnage"
                        type="number"
                        step="0.01"
                        value={form.data.monthly_target_tonnage}
                        onChange={(e) => form.setData('monthly_target_tonnage', e.target.value)}
                        error={form.errors.monthly_target_tonnage}
                        required
                    />
                    <p className="text-xs text-[var(--color-text-muted)] mb-3 -mt-2">
                        Appliqué automatiquement aux mois sans cible personnalisée saisie ci-dessous.
                    </p>
                    <FormInput
                        label="Seuil écart de poids (tonnes)"
                        name="weight_gap_threshold"
                        type="number"
                        step="0.01"
                        value={form.data.weight_gap_threshold}
                        onChange={(e) => form.setData('weight_gap_threshold', e.target.value)}
                        error={form.errors.weight_gap_threshold}
                        required
                    />
                    <FormInput
                        label="Prix du gasoil (FCFA / litre)"
                        name="price_per_litre"
                        type="number"
                        step="0.01"
                        value={form.data.price_per_litre}
                        onChange={(e) => form.setData('price_per_litre', e.target.value)}
                        error={form.errors.price_per_litre}
                        required
                    />
                    <p className="text-xs text-[var(--color-text-muted)] mb-3 -mt-2">
                        Sert à convertir le montant FCFA des transactions EDK en litres lors de l'import.
                    </p>
                    <div className="flex gap-2 pt-4">
                        <Button type="submit" loading={form.processing}>Enregistrer</Button>
                    </div>
                </form>
            </Card>

            <Card
                header={
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <Target size={16} />
                            <span className="text-sm font-semibold">Objectif tonnage par mois</span>
                        </div>
                        <span className="text-xs text-[var(--color-text-muted)]">
                            Défaut courant : {formatNumber(defaultTarget, 0)} T
                        </span>
                    </div>
                }
            >
                <p className="text-xs text-[var(--color-text-muted)] mb-3">
                    Saisis une valeur pour personnaliser le mois (sinon le défaut s'applique). Validation au clic ailleurs ou avec Entrée.
                    Le bouton <RotateCcw size={12} className="inline" /> remet la valeur par défaut.
                </p>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm min-w-[600px]">
                        <thead>
                            <tr className="text-xs text-[var(--color-text-muted)] uppercase border-b border-[var(--color-border)]">
                                <th className="text-left py-2 pr-3">Mois</th>
                                <th className="text-left py-2 pr-3">Cible saisie (T)</th>
                                <th className="text-left py-2 pr-3">Effective</th>
                                <th className="text-left py-2 pr-3">Statut</th>
                                <th className="py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {monthlyTargets.map((row) => (
                                <MonthRow key={`${row.year}-${row.month}`} row={row} defaultTarget={defaultTarget} />
                            ))}
                        </tbody>
                    </table>
                </div>
            </Card>
        </AuthenticatedLayout>
    );
}
