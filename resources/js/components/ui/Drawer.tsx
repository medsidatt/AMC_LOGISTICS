import { useEffect, type ReactNode } from 'react';
import { X } from 'lucide-react';
import { clsx } from 'clsx';

interface DrawerProps {
    open: boolean;
    onClose: () => void;
    title?: ReactNode;
    icon?: ReactNode;
    /** Footer content (typically <FormActions />). Rendered in a sticky bottom bar. */
    footer?: ReactNode;
    size?: 'sm' | 'md' | 'lg';
    children: ReactNode;
}

const sizeMap = {
    sm: 'max-w-sm',
    md: 'max-w-md',
    lg: 'max-w-xl',
};

/**
 * Generic right-side drawer — the canonical overlay for create / edit / detail
 * workflows across every module (keeps the user inside the workspace, no page
 * navigation). Extracted from the original ObjectiveDrawer shell so every module
 * shares one implementation. Locks body scroll and closes on backdrop click / Escape.
 */
export default function Drawer({ open, onClose, title, icon, footer, size = 'md', children }: DrawerProps) {
    useEffect(() => {
        if (!open) return;
        document.body.style.overflow = 'hidden';
        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') onClose();
        };
        window.addEventListener('keydown', onKey);
        return () => {
            document.body.style.overflow = '';
            window.removeEventListener('keydown', onKey);
        };
    }, [open, onClose]);

    if (!open) return null;

    return (
        <>
            <div className="fixed inset-0 bg-black/40 z-40" onClick={onClose} />
            <aside
                role="dialog"
                aria-modal="true"
                className={clsx(
                    'fixed top-0 right-0 h-full w-full bg-[var(--color-surface)] z-50 shadow-xl flex flex-col',
                    sizeMap[size],
                )}
            >
                {(title || icon) && (
                    <header className="flex items-center justify-between px-5 h-16 border-b border-[var(--color-border)] shrink-0">
                        <div className="flex items-center gap-2 min-w-0">
                            {icon}
                            <h2 className="font-semibold truncate">{title}</h2>
                        </div>
                        <button
                            onClick={onClose}
                            aria-label="Fermer"
                            className="p-1.5 rounded-lg hover:bg-[var(--color-surface-hover)] shrink-0"
                        >
                            <X size={18} />
                        </button>
                    </header>
                )}

                <div className="flex-1 overflow-y-auto p-5 space-y-4">{children}</div>

                {footer && (
                    <footer className="flex items-center justify-end gap-2 px-5 h-16 border-t border-[var(--color-border)] shrink-0">
                        {footer}
                    </footer>
                )}
            </aside>
        </>
    );
}
