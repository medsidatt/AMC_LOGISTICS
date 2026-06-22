import { Head, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import FormInput from '@/components/ui/FormInput';
import { Settings, SlidersHorizontal } from 'lucide-react';

interface Props {
    setting: {
        default_capacity_tonnage: number;
        target_rotations_per_week: number;
        weight_gap_threshold: number;
        price_per_litre: number;
    };
}

/**
 * Spinner-free numeric field: text input with a numeric keyboard hint and
 * value sanitisation — no mouse-wheel / arrow-key / spinner value changes
 * (unsafe for capacity, rotation and threshold settings).
 */
function NumberField({
    label, value, onChange, error, integer = false,
}: {
    label: string;
    value: string;
    onChange: (v: string) => void;
    error?: string;
    integer?: boolean;
}) {
    const sanitize = (raw: string) => {
        let v = raw.replace(integer ? /[^\d]/g : /[^\d.]/g, '');
        if (!integer) {
            const i = v.indexOf('.');
            if (i !== -1) v = v.slice(0, i + 1) + v.slice(i + 1).replace(/\./g, '');
        }
        onChange(v);
    };

    return (
        <FormInput
            label={label}
            type="text"
            inputMode={integer ? 'numeric' : 'decimal'}
            autoComplete="off"
            value={value}
            onChange={(e) => sanitize(e.target.value)}
            onWheel={(e) => (e.target as HTMLInputElement).blur()}
            error={error}
            required
        />
    );
}

export default function FleetSettingsPage({ setting }: Props) {
    const form = useForm({
        default_capacity_tonnage: String(setting.default_capacity_tonnage ?? 45),
        target_rotations_per_week: String(setting.target_rotations_per_week ?? 3),
        weight_gap_threshold: String(setting.weight_gap_threshold ?? 0.5),
        price_per_litre: String(setting.price_per_litre ?? 730),
        change_note: '',
    });

    // A change to fleet configuration (capacity / rotation rules) propagates to
    // every truck and re-plans open objectives, so it must be justified.
    const configChanged =
        Number(form.data.default_capacity_tonnage) !== Number(setting.default_capacity_tonnage) ||
        Number(form.data.target_rotations_per_week) !== Number(setting.target_rotations_per_week);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.put('/settings/fleet');
    };

    return (
        <AuthenticatedLayout title="Configuration flotte">
            <Head title="Configuration flotte" />

            <form onSubmit={submit} className="max-w-lg space-y-6">
                <Card
                    header={
                        <div className="flex items-center gap-2">
                            <Settings size={16} />
                            <span className="text-sm font-semibold">Configuration flotte</span>
                        </div>
                    }
                >
                    <NumberField
                        label="Capacité par défaut d'un camion (tonnes)"
                        value={form.data.default_capacity_tonnage}
                        onChange={(v) => form.setData('default_capacity_tonnage', v)}
                        error={form.errors.default_capacity_tonnage}
                    />
                    <NumberField
                        label="Rotations cibles par semaine"
                        value={form.data.target_rotations_per_week}
                        onChange={(v) => form.setData('target_rotations_per_week', v)}
                        error={form.errors.target_rotations_per_week}
                        integer
                    />
                </Card>

                <Card
                    header={
                        <div className="flex items-center gap-2">
                            <SlidersHorizontal size={16} />
                            <span className="text-sm font-semibold">Paramètres opérationnels</span>
                        </div>
                    }
                >
                    <NumberField
                        label="Seuil d'écart de poids (tonnes)"
                        value={form.data.weight_gap_threshold}
                        onChange={(v) => form.setData('weight_gap_threshold', v)}
                        error={form.errors.weight_gap_threshold}
                    />
                    <NumberField
                        label="Prix du gasoil (FCFA / litre)"
                        value={form.data.price_per_litre}
                        onChange={(v) => form.setData('price_per_litre', v)}
                        error={form.errors.price_per_litre}
                    />
                </Card>

                {configChanged && (
                    <Card
                        header={<span className="text-sm font-semibold">Justification de la modification</span>}
                    >
                        <textarea
                            className="w-full px-3 py-2 text-sm rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-[var(--color-text)] focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]/20"
                            rows={3}
                            value={form.data.change_note}
                            onChange={(e) => form.setData('change_note', e.target.value)}
                            required
                        />
                        {form.errors.change_note && (
                            <p className="text-xs text-[var(--color-danger)] mt-1">{form.errors.change_note}</p>
                        )}
                    </Card>
                )}

                <div className="flex gap-2">
                    <Button type="submit" loading={form.processing}>Enregistrer</Button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
