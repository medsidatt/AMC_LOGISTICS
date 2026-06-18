import { useState, type ReactNode } from 'react';
import { Info, X } from 'lucide-react';

/** A one-time, dismissible plain-language hint (remembered per browser). */
export default function HelpHint({ id, children }: { id: string; children: ReactNode }) {
    const key = `hint_dismissed_${id}`;
    const [open, setOpen] = useState(() => {
        try { return localStorage.getItem(key) !== '1'; } catch { return true; }
    });

    if (!open) return null;

    const dismiss = () => {
        try { localStorage.setItem(key, '1'); } catch { /* ignore */ }
        setOpen(false);
    };

    return (
        <div className="flex items-start gap-2 rounded-xl border border-[var(--color-primary)]/30 bg-[var(--color-primary)]/5 p-3 text-sm text-[var(--color-text-secondary)] mb-4">
            <Info size={16} className="text-[var(--color-primary)] mt-0.5 shrink-0" />
            <div className="flex-1">{children}</div>
            <button type="button" onClick={dismiss} aria-label="Fermer" className="text-[var(--color-text-muted)] hover:text-[var(--color-text)] shrink-0">
                <X size={16} />
            </button>
        </div>
    );
}
