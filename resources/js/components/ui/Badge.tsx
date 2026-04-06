import { clsx } from 'clsx';

interface BadgeProps {
    children: React.ReactNode;
    variant?: 'primary' | 'success' | 'danger' | 'warning' | 'info' | 'muted';
    size?: 'sm' | 'md';
}

const colorMap = {
    primary: 'bg-[var(--color-primary)]/10 text-[var(--color-primary)]',
    success: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
    danger: 'bg-red-500/10 text-red-600 dark:text-red-400',
    warning: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
    info: 'bg-cyan-500/10 text-cyan-600 dark:text-cyan-400',
    muted: 'bg-[var(--color-surface-hover)] text-[var(--color-text-muted)]',
};

export default function Badge({ children, variant = 'primary', size = 'sm' }: BadgeProps) {
    return (
        <span
            className={clsx(
                'inline-flex items-center font-medium rounded-full',
                colorMap[variant],
                size === 'sm' ? 'px-2 py-0.5 text-xs' : 'px-3 py-1 text-sm',
            )}
        >
            {children}
        </span>
    );
}
