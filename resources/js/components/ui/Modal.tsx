import { useEffect, type ReactNode } from 'react';
import { X } from 'lucide-react';
import { clsx } from 'clsx';

interface ModalProps {
    open: boolean;
    onClose: () => void;
    title?: string;
    children: ReactNode;
    size?: 'sm' | 'md' | 'lg' | 'xl';
}

const sizeMap = {
    sm: 'max-w-md',
    md: 'max-w-lg',
    lg: 'max-w-2xl',
    xl: 'max-w-4xl',
};

export default function Modal({ open, onClose, title, children, size = 'md' }: ModalProps) {
    useEffect(() => {
        if (open) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
        return () => { document.body.style.overflow = ''; };
    }, [open]);

    if (!open) return null;

    return (
        <div className="fixed inset-0 z-[60] flex items-center justify-center p-2 sm:p-4">
            <div className="absolute inset-0 bg-black/50" onClick={onClose} />
            <div
                className={clsx(
                    'relative w-full bg-[var(--color-surface)] rounded-xl sm:rounded-2xl shadow-2xl animate-slide-up max-h-[95vh] sm:max-h-[90vh] flex flex-col',
                    sizeMap[size],
                )}
            >
                {title && (
                    <div className="flex items-center justify-between px-4 sm:px-6 py-3 sm:py-4 border-b border-[var(--color-border)] shrink-0">
                        <h2 className="text-base sm:text-lg font-semibold text-[var(--color-text)] truncate pr-2">{title}</h2>
                        <button onClick={onClose} className="p-1.5 rounded-lg hover:bg-[var(--color-surface-hover)] text-[var(--color-text-muted)] shrink-0">
                            <X size={18} />
                        </button>
                    </div>
                )}
                <div className="p-4 sm:p-6 overflow-y-auto">{children}</div>
            </div>
        </div>
    );
}
