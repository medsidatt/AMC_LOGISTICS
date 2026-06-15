const ORGANS: { key: string; label: string }[] = [
    { key: 'gearbox_status', label: 'Boîte de vitesse' },
    { key: 'differential_status', label: 'Différentiel (pont)' },
    { key: 'hydraulic_status', label: 'Circuit hydraulique' },
    { key: 'greasing_status', label: 'Graissage' },
    { key: 'brake_status', label: 'Freins' },
    { key: 'coolant_status', label: 'Liquide de refroidissement' },
    { key: 'battery_status', label: 'Batterie' },
];

interface Props {
    statuses: Record<string, string>;
    value: Record<string, any>;
    onChange: (key: string, val: string) => void;
    disabled?: boolean;
}

/**
 * "État des organes mécaniques" as a row list (label + status selector per
 * line), matching the layout of the post-work control checklist.
 */
export default function ComponentStatusList({ statuses, value, onChange, disabled }: Props) {
    return (
        <div className="rounded-lg border border-[var(--color-border)] divide-y divide-[var(--color-border)]">
            {ORGANS.map((organ) => (
                <div key={organ.key} className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 px-3 py-2">
                    <span className="text-sm text-[var(--color-text)]">{organ.label}</span>
                    <select
                        value={value[organ.key] ?? 'NORMAL'}
                        disabled={disabled}
                        onChange={(e) => onChange(organ.key, e.target.value)}
                        className="w-full sm:w-48 px-2 py-1.5 rounded-md border border-[var(--color-border)] bg-[var(--color-surface)] text-sm text-[var(--color-text)] focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]/20 focus:border-[var(--color-primary)]"
                    >
                        {Object.entries(statuses).map(([k, l]) => (
                            <option key={k} value={k}>
                                {l}
                            </option>
                        ))}
                    </select>
                </div>
            ))}
        </div>
    );
}
