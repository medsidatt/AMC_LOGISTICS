const OPTIONS: { value: string; label: string }[] = [
    { value: '', label: '—' },
    { value: 'bon', label: 'Bon' },
    { value: 'mauvais', label: 'Mauvais' },
    { value: 'na', label: 'N/A' },
];

interface Props {
    items: Record<string, string>;
    value: Record<string, string>;
    onChange: (value: Record<string, string>) => void;
    disabled?: boolean;
}

/**
 * "Fiche de contrôle après travaux" — each verification line has a single
 * dropdown (Bon / Mauvais / N/A), matching the organ-status list layout.
 */
export default function ControlChecklist({ items, value, onChange, disabled }: Props) {
    const set = (key: string, state: string) => {
        const next = { ...value };
        if (state) next[key] = state;
        else delete next[key];
        onChange(next);
    };

    return (
        <div className="rounded-lg border border-[var(--color-border)] divide-y divide-[var(--color-border)]">
            {Object.entries(items).map(([key, label]) => (
                <div key={key} className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 px-3 py-2">
                    <span className="text-sm text-[var(--color-text)]">{label}</span>
                    <select
                        value={value[key] ?? ''}
                        disabled={disabled}
                        onChange={(e) => set(key, e.target.value)}
                        className="w-full sm:w-40 px-2 py-1.5 rounded-md border border-[var(--color-border)] bg-[var(--color-surface)] text-sm text-[var(--color-text)] focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]/20 focus:border-[var(--color-primary)]"
                    >
                        {OPTIONS.map((opt) => (
                            <option key={opt.value} value={opt.value}>
                                {opt.label}
                            </option>
                        ))}
                    </select>
                </div>
            ))}
        </div>
    );
}
