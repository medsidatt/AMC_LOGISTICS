import { type ReactNode } from 'react';
import { clsx } from 'clsx';

export interface TabItem {
    key: string;
    label: string;
    icon?: ReactNode;
}

interface TabsProps {
    tabs: TabItem[];
    active: string;
    onChange: (key: string) => void;
    className?: string;
}

/**
 * Generic tab strip — underlined active tab, used to organise a single surface
 * (e.g. a details drawer: Détails / Documents / IA) instead of stacking drawers.
 * The standard tab pattern for every SPA workspace.
 */
export default function Tabs({ tabs, active, onChange, className }: TabsProps) {
    return (
        <div role="tablist" className={clsx('flex gap-1 border-b border-[var(--color-border)]', className)}>
            {tabs.map((t) => (
                <button
                    key={t.key}
                    role="tab"
                    aria-selected={active === t.key}
                    onClick={() => onChange(t.key)}
                    className={clsx(
                        'inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium border-b-2 -mb-px transition-colors',
                        active === t.key
                            ? 'border-[var(--color-primary)] text-[var(--color-primary)]'
                            : 'border-transparent text-[var(--color-text-secondary)] hover:text-[var(--color-text)]',
                    )}
                >
                    {t.icon}
                    {t.label}
                </button>
            ))}
        </div>
    );
}
