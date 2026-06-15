import type { ReactNode } from 'react';

/** A simple section separator heading for forms. */
export default function SectionTitle({ children }: { children: ReactNode }) {
    return (
        <h3 className="text-sm font-semibold text-[var(--color-text)] border-b border-[var(--color-border)] pb-1.5">
            {children}
        </h3>
    );
}
