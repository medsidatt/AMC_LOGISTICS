import { type ReactNode } from 'react';
import { clsx } from 'clsx';

interface CardProps {
    children: ReactNode;
    className?: string;
    header?: ReactNode;
    padding?: boolean;
}

export default function Card({ children, className, header, padding = true }: CardProps) {
    return (
        <div
            className={clsx(
                'bg-[var(--color-surface)] rounded-xl border border-[var(--color-border)] shadow-[var(--shadow-sm)] transition-shadow hover:shadow-[var(--shadow-md)]',
                className,
            )}
        >
            {header && (
                <div className="px-5 py-3.5 border-b border-[var(--color-border)]">
                    {typeof header === 'string' ? (
                        <h3 className="text-sm font-semibold text-[var(--color-text)]">{header}</h3>
                    ) : header}
                </div>
            )}
            <div className={clsx(padding && 'p-5')}>{children}</div>
        </div>
    );
}
