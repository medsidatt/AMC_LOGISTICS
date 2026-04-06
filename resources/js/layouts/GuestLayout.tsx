import type { ReactNode } from 'react';

interface GuestLayoutProps {
    children: ReactNode;
    title?: string;
}

export default function GuestLayout({ children, title }: GuestLayoutProps) {
    return (
        <div className="min-h-screen flex items-center justify-center bg-[var(--color-bg)] px-4 py-8">
            <div className="w-full max-w-md">
                <div className="text-center mb-8">
                    <h1 className="text-2xl font-bold text-[var(--color-text)]">
                        AMC <span className="text-[var(--color-primary)]">Logistics</span>
                    </h1>
                    {title && <p className="text-sm text-[var(--color-text-muted)] mt-2">{title}</p>}
                </div>
                <div className="bg-[var(--color-surface)] rounded-2xl border border-[var(--color-border)] shadow-[var(--shadow-md)] p-8">
                    {children}
                </div>
                <p className="text-center text-xs text-[var(--color-text-muted)] mt-6">
                    AMC Travaux SN SARL
                </p>
            </div>
        </div>
    );
}
