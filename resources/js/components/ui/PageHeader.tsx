import { type ReactNode } from 'react';
import { clsx } from 'clsx';

interface PageHeaderProps {
    title: ReactNode;
    icon?: ReactNode;
    subtitle?: ReactNode;
    /** Right-aligned actions (typically buttons). */
    actions?: ReactNode;
    className?: string;
}

/**
 * Consistent page header — title + optional icon/subtitle on the left, actions on
 * the right. The standard top of every module workspace (replaces hand-rolled
 * `<h1>` + button-row markup). Responsive: actions wrap below the title on mobile.
 */
export default function PageHeader({ title, icon, subtitle, actions, className }: PageHeaderProps) {
    return (
        <div className={clsx('flex items-start justify-between gap-3 flex-wrap mb-4', className)}>
            <div className="flex items-center gap-2 min-w-0">
                {icon}
                <div className="min-w-0">
                    <h1 className="text-xl font-semibold text-[var(--color-text)] truncate">{title}</h1>
                    {subtitle && <p className="text-sm text-[var(--color-text-muted)]">{subtitle}</p>}
                </div>
            </div>
            {actions && <div className="flex items-center gap-2 shrink-0">{actions}</div>}
        </div>
    );
}
